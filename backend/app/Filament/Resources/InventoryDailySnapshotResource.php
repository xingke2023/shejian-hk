<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryDailySnapshotResource\Pages;
use App\Models\InventoryDailySnapshot;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryDailySnapshotResource extends Resource
{
    protected static ?string $model = InventoryDailySnapshot::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = '库存管理';

    protected static ?string $navigationLabel = '每日库存留档';

    protected static ?string $modelLabel = '库存日档';

    protected static ?string $pluralModelLabel = '每日库存留档';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('日期')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('门店')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('商品')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.unit')
                    ->label('单位'),
                Tables\Columns\TextColumn::make('opening_qty')
                    ->label('开盘库存')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_qty')
                    ->label('今日进货')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sold_qty')
                    ->label('今日销售')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('damage_qty')
                    ->label('损耗')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('adjustment_qty')
                    ->label('盘点调整')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('closing_qty')
                    ->label('收档库存')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->color(fn ($record) => $record->closing_qty <= 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('sold_out_at')
                    ->label('售罄时间')
                    ->dateTime('H:i')
                    ->placeholder('—')
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('门店')
                    ->relationship('store', 'name'),
                Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('开始日期')->default(today()->subDays(7)),
                        \Filament\Forms\Components\DatePicker::make('until')->label('结束日期')->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('date', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $data) {
                        if ($data['from'] && $data['until']) {
                            return "{$data['from']} 至 {$data['until']}";
                        }

                        return null;
                    }),
                Tables\Filters\Filter::make('has_sales')
                    ->label('有销售记录')
                    ->query(fn ($query) => $query->where('sold_qty', '>', 0)),
                Tables\Filters\Filter::make('sold_out')
                    ->label('当日售罄')
                    ->query(fn ($query) => $query->where('closing_qty', '<=', 0)),
            ])
            ->defaultSort('date', 'desc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryDailySnapshots::route('/'),
        ];
    }
}
