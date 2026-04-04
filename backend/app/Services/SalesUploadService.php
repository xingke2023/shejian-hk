<?php

namespace App\Services;

use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesDailySummary;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesUpload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SalesUploadService
{
    /**
     * 从 Excel 文件解析出原始行数据（二维数组）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseExcel(string $storagePath): array
    {
        $absolutePath = Storage::path($storagePath);
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        $headerRow = [];
        $maxCol = $sheet->getHighestColumn();
        $maxRow = (int) $sheet->getHighestRow();

        for ($col = 'A'; $col <= $maxCol; $col++) {
            $headerRow[] = (string) $sheet->getCell($col.'1')->getValue();
        }

        for ($row = 2; $row <= $maxRow; $row++) {
            $record = [];
            $colIdx = 0;
            for ($col = 'A'; $col <= $maxCol; $col++) {
                $header = $headerRow[$colIdx] ?? "col_{$colIdx}";
                $record[$header] = $sheet->getCell($col.$row)->getFormattedValue();
                $colIdx++;
            }

            // 跳过全空行
            $allEmpty = collect($record)->every(fn ($v) => trim((string) $v) === '');
            if (! $allEmpty) {
                $rows[] = $record;
            }
        }

        return $rows;
    }

    /**
     * 调用 AI 将原始 Excel 行映射为标准销售明细。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{items: array, summary: string}
     */
    public function analyzeWithAi(array $rows): array
    {
        $sampleRows = array_slice($rows, 0, 50);
        $rowsText = json_encode($sampleRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $systemPrompt = <<<'PROMPT'
你是生鲜门店AI数据分析助手（舌尖香港）。
用户会提供从收银系统导出的Excel销售明细数据（JSON格式，每行是一个对象）。
你需要识别每行中的：品名/商品名、销售数量、销售金额，并返回标准格式。

要求：
1. 识别代表"商品名称"的字段（可能是"品名"、"商品名"、"名称"、"货品名"等）
2. 识别代表"销售数量"的字段（可能是"数量"、"销售量"、"售出数量"、"重量"等）
3. 识别代表"销售金额"的字段（可能是"金额"、"销售额"、"实收"、"小计"等）
4. 如果某行数据不完整或为汇总行（品名为空、或是"合计"、"总计"等），跳过该行

严格返回以下JSON格式，不要输出任何其他文字：
{
  "items": [
    {
      "product_name": "商品名称（原始）",
      "qty": 数字（销售数量，正数）,
      "amount": 数字（销售金额，正数，若无则填0）,
      "unit": "单位（如有，默认斤）"
    }
  ],
  "summary": "简短说明：共识别X行有效销售数据，字段映射情况"
}

注意：qty 和 amount 必须为数字，不能为字符串。若原始数据中数量为负数或0，跳过该行。
PROMPT;

        $response = Http::baseUrl(config('ai.base_url'))
            ->withToken(config('ai.api_key'))
            ->timeout(60)
            ->post('/chat/completions', [
                'model' => config('ai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "以下是Excel导出的销售明细数据，请解析：\n\n{$rowsText}"],
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            Log::error('SalesUpload AI analysis failed', ['status' => $response->status()]);

            return ['items' => [], 'summary' => 'AI分析失败'];
        }

        $content = $response->json('choices.0.message.content', '{}');
        $parsed = json_decode($content, true);

        if (! is_array($parsed) || empty($parsed['items'])) {
            return ['items' => [], 'summary' => '无法从Excel中识别有效销售数据'];
        }

        return $parsed;
    }

    /**
     * 处理一条上传记录：解析 Excel → AI 分析 → 写入数据库。
     */
    public function processUpload(SalesUpload $upload, int $operatorId): void
    {
        $upload->update(['status' => SalesUpload::STATUS_PROCESSING]);

        try {
            // 1. 解析 Excel
            $rows = $this->parseExcel($upload->file_path);
            $upload->update(['raw_rows' => $rows, 'total_items' => count($rows)]);

            if (empty($rows)) {
                $upload->update([
                    'status' => SalesUpload::STATUS_FAILED,
                    'error_message' => 'Excel文件为空或格式不正确',
                ]);

                return;
            }

            // 2. AI 分析
            $aiResult = $this->analyzeWithAi($rows);
            $upload->update(['ai_result' => $aiResult]);

            $items = $aiResult['items'] ?? [];
            if (empty($items)) {
                $upload->update([
                    'status' => SalesUpload::STATUS_FAILED,
                    'error_message' => 'AI未能识别有效销售数据：'.($aiResult['summary'] ?? ''),
                ]);

                return;
            }

            // 3. 写入数据库
            $storeId = $upload->store_id;
            $saleDate = $upload->sale_date->toDateString();
            $occurredAt = Carbon::parse($saleDate.' '.now()->format('H:i:s'));

            $processed = 0;
            $failed = 0;

            foreach ($items as $item) {
                $productName = trim((string) ($item['product_name'] ?? ''));
                $qty = (float) ($item['qty'] ?? 0);
                $amount = (float) ($item['amount'] ?? 0);
                $unit = trim((string) ($item['unit'] ?? '斤')) ?: '斤';

                if ($productName === '' || $qty <= 0) {
                    $failed++;

                    continue;
                }

                try {
                    DB::transaction(function () use (
                        $storeId, $productName, $qty, $amount, $unit,
                        $saleDate, $occurredAt, $operatorId, $upload
                    ): void {
                        // 找到或创建商品
                        $product = Product::findOrCreateByName($productName);
                        if ($product->unit === '斤' && $unit !== '斤') {
                            $product->update(['unit' => $unit]);
                        }

                        $productId = $product->id;
                        $unitPrice = $qty > 0 ? round($amount / $qty, 4) : 0;

                        // 创建销售单（补录）
                        $salesOrder = SalesOrder::create([
                            'store_id' => $storeId,
                            'order_no' => 'UPL-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                            'cashier_id' => null,
                            'total_amount' => $amount,
                            'discount_amount' => 0,
                            'paid_amount' => $amount,
                            'payment_method' => 1,
                            'status' => 1,
                            'sold_at' => $occurredAt,
                            'notes' => '[汇总上传] '.$upload->original_filename,
                        ]);

                        SalesOrderItem::create([
                            'sales_order_id' => $salesOrder->id,
                            'product_id' => $productId,
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'discount_amount' => 0,
                            'subtotal' => $amount,
                            'cost_price' => null,
                        ]);

                        // 更新库存
                        $inventory = Inventory::firstOrCreate(
                            ['store_id' => $storeId, 'product_id' => $productId],
                            ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
                        );

                        $qtyBefore = (float) $inventory->current_qty;
                        $qtyAfter = max(0, $qtyBefore - $qty);
                        $qtyChange = $qtyAfter - $qtyBefore;

                        InventoryTransaction::create([
                            'store_id' => $storeId,
                            'product_id' => $productId,
                            'transaction_type' => 2,
                            'qty_change' => $qtyChange,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'operator_id' => $operatorId,
                            'notes' => '[汇总上传] '.$upload->original_filename,
                            'created_at' => $occurredAt,
                        ]);

                        $inventory->update([
                            'current_qty' => $qtyAfter,
                            'available_qty' => $qtyAfter,
                            'last_out_at' => $occurredAt,
                            'last_sold_at' => $occurredAt,
                        ]);

                        // 更新每日快照
                        InventoryDailySnapshot::record(
                            storeId: $storeId,
                            productId: $productId,
                            qtyBefore: $qtyBefore,
                            qtyChange: $qtyChange,
                            qtyAfter: $qtyAfter,
                            transactionType: 2,
                            date: $saleDate,
                            occurredAt: $occurredAt,
                        );

                        // 更新每日销售汇总
                        $summary = SalesDailySummary::firstOrCreate(
                            ['store_id' => $storeId, 'product_id' => $productId, 'sale_date' => $saleDate],
                            ['sales_qty' => 0, 'sales_amount' => 0, 'transaction_count' => 0],
                        );
                        $newQty = (float) $summary->sales_qty + $qty;
                        $newAmount = (float) $summary->sales_amount + $amount;
                        $summary->update([
                            'sales_qty' => $newQty,
                            'sales_amount' => $newAmount,
                            'transaction_count' => $summary->transaction_count + 1,
                            'avg_selling_price' => $newQty > 0 ? round($newAmount / $newQty, 4) : null,
                        ]);
                    });

                    $processed++;
                } catch (\Throwable $e) {
                    Log::warning('SalesUpload item failed', [
                        'upload_id' => $upload->id,
                        'product_name' => $productName,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }

            // 4. 写操作日志
            DailyOperationLog::write(
                storeId: $storeId,
                content: "Excel汇总上传：{$upload->original_filename}，处理 {$processed} 条，失败 {$failed} 条",
                intent: 'sale_report',
                source: 2,
                isOperational: true,
                operatorId: $operatorId,
                occurredAt: $occurredAt,
            );

            $upload->update([
                'status' => $processed > 0 ? SalesUpload::STATUS_COMPLETED : SalesUpload::STATUS_FAILED,
                'processed_items' => $processed,
                'failed_items' => $failed,
                'error_message' => $failed > 0 ? "共 {$failed} 条未能处理" : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesUpload processUpload failed', ['upload_id' => $upload->id, 'error' => $e->getMessage()]);
            $upload->update([
                'status' => SalesUpload::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
