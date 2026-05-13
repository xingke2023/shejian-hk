<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = '销售管理';

    protected static ?string $navigationLabel = '到货单';

    protected static ?string $modelLabel = '到货单';

    protected static ?string $pluralModelLabel = '到货单';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('门店')
                            ->options(Store::query()->where('status', 1)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('到货日期')
                            ->required()
                            ->default(today()),

                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                1 => '草稿',
                                2 => '待审核',
                                3 => '已确认',
                                4 => '配送中',
                                5 => '已收货',
                                6 => '已取消',
                            ])
                            ->default(5)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('备注')
                            ->columnSpanFull()
                            ->rows(2),
                    ]),

                Forms\Components\Section::make('采购明细')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->label('供应商')
                                    ->options(Supplier::query()->where('status', 1)->pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder('不限')
                                    ->columnSpan(3),

                                Forms\Components\Select::make('product_id')
                                    ->label('商品')
                                    ->options(Product::query()->where('status', 1)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                        if (! $state) {
                                            return;
                                        }
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit', $product->unit ?? '');
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('unit')
                                    ->label('单位')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('ordered_qty')
                                    ->label('采购数量')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcItemTotal($get, $set))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('单价')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcItemTotal($get, $set))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('total_price')
                                    ->label('小计')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                            ])
                            ->columns(12)
                            ->addActionLabel('+ 添加商品')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make('金额汇总')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('合计金额（元）')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->prefix('¥'),
                    ]),
            ]);
    }

    protected static function recalcItemTotal(Get $get, Set $set): void
    {
        $qty = (float) ($get('ordered_qty') ?? 0);
        $price = (float) ($get('unit_price') ?? 0);
        $set('total_price', round($qty * $price, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('单号')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('门店')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->label('到货日期')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('商品种数')
                    ->counts('items')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('金额')
                    ->money('CNY')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (int $state) => match ($state) {
                        1 => '草稿',
                        2 => '待审核',
                        3 => '已确认',
                        4 => '配送中',
                        5 => '已收货',
                        6 => '已取消',
                        default => '未知',
                    })
                    ->color(fn (int $state) => match ($state) {
                        1 => 'gray',
                        2 => 'warning',
                        3 => 'info',
                        4 => 'primary',
                        5 => 'success',
                        6 => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('actual_delivery_date')
                    ->label('实际到货')
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        1 => '草稿',
                        2 => '待审核',
                        3 => '已确认',
                        4 => '配送中',
                        5 => '已收货',
                        6 => '已取消',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('receive')
                    ->label('确认收货')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('确认收货')
                    ->modalDescription('确认后将自动更新库存，此操作不可撤销。')
                    ->visible(fn (PurchaseOrder $record) => in_array($record->status, [1, 2, 3, 4]))
                    ->action(function (PurchaseOrder $record): void {
                        static::processReceiving($record);
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('cancel')
                    ->label('取消')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record) => in_array($record->status, [1, 2, 3]))
                    ->action(fn (PurchaseOrder $record) => $record->update(['status' => 6])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function processReceiving(PurchaseOrder $record): void
    {
        DB::transaction(function () use ($record) {
            $now = now();

            foreach ($record->items as $item) {
                $inventory = Inventory::firstOrCreate(
                    ['store_id' => $record->store_id, 'product_id' => $item->product_id],
                    ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
                );

                $qtyBefore = (float) $inventory->current_qty;
                $qtyChange = (float) $item->ordered_qty;
                $qtyAfter = $qtyBefore + $qtyChange;

                InventoryTransaction::create([
                    'store_id' => $record->store_id,
                    'product_id' => $item->product_id,
                    'transaction_type' => 1, // purchase_in
                    'qty_change' => $qtyChange,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'unit_cost' => $item->unit_price,
                    'total_cost' => $item->total_price,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $record->id,
                    'notes' => "进货单 {$record->order_no}",
                    'created_at' => $now,
                ]);

                $inventory->update([
                    'current_qty' => $qtyAfter,
                    'available_qty' => $qtyAfter,
                    'last_in_at' => $now,
                ]);

                InventoryDailySnapshot::record(
                    storeId: $record->store_id,
                    productId: $item->product_id,
                    qtyBefore: $qtyBefore,
                    qtyChange: $qtyChange,
                    qtyAfter: $qtyAfter,
                    transactionType: 1,
                    date: today()->toDateString(),
                    occurredAt: $now,
                );

                $item->update(['received_qty' => $item->ordered_qty]);
            }

            $record->update([
                'status' => 5,
                'actual_delivery_date' => today(),
            ]);

            DailyOperationLog::write(
                storeId: $record->store_id,
                content: '确认收货: 进货单 '.$record->order_no.'，共 '.$record->items->count().' 种商品',
                intent: 'stock_in',
                source: 3,
                isOperational: true,
                referenceType: 'purchase_order',
                referenceId: $record->id,
            );
        });

        Notification::make()
            ->title('收货完成')
            ->body("进货单 {$record->order_no} 已收货，库存已更新。")
            ->success()
            ->send();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
