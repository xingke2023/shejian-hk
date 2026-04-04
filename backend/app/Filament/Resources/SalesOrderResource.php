<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = '销售管理';

    protected static ?string $navigationLabel = '零售流水';

    protected static ?string $modelLabel = '销售单';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('收银信息')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('门店')
                            ->options(Store::query()->where('status', 1)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('order_no')
                            ->label('流水号')
                            ->default(fn () => 'SO-'.date('Ymd').'-'.strtoupper(Str::random(6)))
                            ->required()
                            ->maxLength(50),

                        Forms\Components\Select::make('cashier_id')
                            ->label('收银员')
                            ->options(User::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('sold_at')
                            ->label('交易时间')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('payment_method')
                            ->label('支付方式')
                            ->options([
                                1 => '现金',
                                2 => '微信',
                                3 => '支付宝',
                                4 => '银行卡',
                                5 => '混合',
                            ])
                            ->default(1)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                1 => '已完成',
                                2 => '已退款',
                                3 => '部分退款',
                            ])
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('整单折扣（元）')
                            ->numeric()
                            ->default(0)
                            ->prefix('¥'),

                        Forms\Components\Textarea::make('notes')
                            ->label('备注')
                            ->columnSpanFull()
                            ->rows(2),
                    ]),

                Forms\Components\Section::make('销售明细')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship()
                            ->schema([
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
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('unit')
                                    ->label('单位')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('qty')
                                    ->label('数量')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcSubtotal($get, $set))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('单价（元）')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix('¥')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcSubtotal($get, $set))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('行级折扣')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('¥')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcSubtotal($get, $set))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('小计')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('¥')
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
                            ->label('应收金额（元）')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->prefix('¥'),

                        Forms\Components\TextInput::make('paid_amount')
                            ->label('实收金额（元）')
                            ->numeric()
                            ->required()
                            ->prefix('¥'),
                    ])
                    ->columns(2),
            ]);
    }

    protected static function recalcSubtotal(Get $get, Set $set): void
    {
        $qty = (float) ($get('qty') ?? 0);
        $price = (float) ($get('unit_price') ?? 0);
        $discount = (float) ($get('discount_amount') ?? 0);
        $set('subtotal', round($qty * $price - $discount, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('流水号')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('门店')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cashier.name')
                    ->label('收银员')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sold_at')
                    ->label('交易时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('商品种数')
                    ->counts('items')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('应收')
                    ->money('CNY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('实收')
                    ->money('CNY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('支付方式')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '现金', 2 => '微信', 3 => '支付宝', 4 => '银行卡', 5 => '混合', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        2 => 'success', 3 => 'info', default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '已完成', 2 => '已退款', 3 => '部分退款', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success', 2 => 'danger', 3 => 'warning', default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('支付方式')
                    ->options([1 => '现金', 2 => '微信', 3 => '支付宝', 4 => '银行卡', 5 => '混合']),

                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([1 => '已完成', 2 => '已退款', 3 => '部分退款']),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('refund')
                    ->label('退款')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('确认退款')
                    ->modalDescription('确认后将恢复库存，此操作不可撤销。')
                    ->visible(fn (SalesOrder $record) => $record->status === 1)
                    ->action(fn (SalesOrder $record) => static::processRefund($record)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sold_at', 'desc');
    }

    protected static function processRefund(SalesOrder $record): void
    {
        DB::transaction(function () use ($record) {
            $now = now();

            foreach ($record->items as $item) {
                $inventory = Inventory::firstOrCreate(
                    ['store_id' => $record->store_id, 'product_id' => $item->product_id],
                    ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
                );

                $qtyBefore = (float) $inventory->current_qty;
                $qtyAfter = $qtyBefore + (float) $item->qty;

                InventoryTransaction::create([
                    'store_id' => $record->store_id,
                    'product_id' => $item->product_id,
                    'transaction_type' => 8, // 退货入库
                    'qty_change' => (float) $item->qty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'unit_cost' => $item->cost_price,
                    'reference_type' => 'sales_order',
                    'reference_id' => $record->id,
                    'notes' => "销售退款 {$record->order_no}",
                    'created_at' => $now,
                ]);

                $inventory->update([
                    'current_qty' => $qtyAfter,
                    'available_qty' => $qtyAfter,
                    'last_in_at' => $now,
                ]);
            }

            $record->update(['status' => 2]);
        });

        Notification::make()
            ->title('退款完成')
            ->body("流水单 {$record->order_no} 已退款，库存已恢复。")
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
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
