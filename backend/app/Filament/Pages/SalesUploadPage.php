<?php

namespace App\Filament\Pages;

use App\Models\SalesUpload;
use App\Models\Store;
use App\Services\SalesUploadService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class SalesUploadPage extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.sales-upload';

    protected static string | \UnitEnum | null $navigationGroup = '销售管理';

    protected static ?string $navigationLabel = '销售流水导入';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 5;

    /** 当前选中的门店 ID */
    public string $activeStoreId = '';

    /** 上传的文件（Livewire 临时文件） */
    public $uploadFile = null;

    /** 销售日期 */
    public string $saleDate = '';

    /** 是否正在处理 */
    public bool $processing = false;

    public function mount(): void
    {
        $this->saleDate = today()->toDateString();

        $stores = Store::orderBy('id')->get(['id']);
        if ($stores->isNotEmpty()) {
            $this->activeStoreId = (string) $stores->first()->id;
        }
    }

    /** 返回所有门店列表 */
    public function getStores(): \Illuminate\Database\Eloquent\Collection
    {
        return Store::orderBy('id')->get(['id', 'name']);
    }

    /** 切换当前门店 Tab */
    public function setStore(string $storeId): void
    {
        $this->activeStoreId = $storeId;
        $this->uploadFile = null;
    }

    /** 返回当前门店的历史上传列表 */
    public function getUploads(): \Illuminate\Pagination\LengthAwarePaginator
    {
        if (! $this->activeStoreId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return SalesUpload::with('uploader:id,name')
            ->where('store_id', $this->activeStoreId)
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    /** 提交上传并触发 AI 分析入库 */
    public function submitUpload(): void
    {
        $this->validate([
            'uploadFile' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'saleDate' => 'required|date_format:Y-m-d',
            'activeStoreId' => 'required|exists:stores,id',
        ], [
            'uploadFile.required' => '请选择 Excel 文件',
            'uploadFile.mimes' => '只支持 xlsx / xls / csv 格式',
            'uploadFile.max' => '文件大小不能超过 10MB',
            'saleDate.required' => '请选择销售日期',
            'activeStoreId.required' => '请选择门店',
        ]);

        $this->processing = true;

        try {
            $file = $this->uploadFile;
            $filename = $file->getClientOriginalName();
            $path = $file->storeAs(
                'sales_uploads/'.$this->activeStoreId,
                now()->format('Ymd_His').'_'.$filename,
                'local'
            );

            /** @var \App\Models\SalesUpload $upload */
            $upload = SalesUpload::create([
                'store_id' => (int) $this->activeStoreId,
                'uploaded_by' => Auth::id(),
                'original_filename' => $filename,
                'file_path' => $path,
                'sale_date' => $this->saleDate,
                'status' => SalesUpload::STATUS_PENDING,
            ]);

            /** @var \App\Services\SalesUploadService $service */
            $service = app(SalesUploadService::class);
            $service->processUpload($upload, (int) Auth::id());

            $upload->refresh();

            if ($upload->status === SalesUpload::STATUS_COMPLETED) {
                Notification::make()
                    ->title('上传成功')
                    ->body("已处理 {$upload->processed_items} 条销售数据".($upload->failed_items > 0 ? "，{$upload->failed_items} 条失败" : ''))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('处理失败')
                    ->body($upload->error_message ?? 'AI解析失败，请检查文件格式')
                    ->danger()
                    ->send();
            }

            $this->uploadFile = null;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('上传出错')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->processing = false;
        }
    }

    /** 删除一条上传记录（同时删除文件） */
    public function deleteUpload(int $uploadId): void
    {
        $upload = SalesUpload::find($uploadId);
        if (! $upload || (int) $upload->store_id !== (int) $this->activeStoreId) {
            return;
        }

        Storage::disk('local')->delete($upload->file_path);
        $upload->delete();

        Notification::make()
            ->title('已删除')
            ->success()
            ->send();
    }
}
