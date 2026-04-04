<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesDailySummaryResource\Pages;
use App\Models\SalesDailySummary;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesDailySummaryResource extends Resource
{
    protected static ?string $model = SalesDailySummary::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = '销售管理';

    protected static ?string $navigationLabel = '每日销售留档';

    protected static ?string $modelLabel = '每日销售';

    protected static ?string $pluralModelLabel = '每日销售留档';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sale_date')
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
                Tables\Columns\TextColumn::make('sales_qty')
                    ->label('售出量')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_amount')
                    ->label('销售额')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_selling_price')
                    ->label('均价')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction_count')
                    ->label('笔数')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pos_qty')
                    ->label('收银台')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('supplement_qty')
                    ->label('补录')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ai_qty')
                    ->label('AI录入')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('门店')
                    ->relationship('store', 'name'),
                Tables\Filters\Filter::make('sale_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('开始日期'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('结束日期'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('sale_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('sale_date', '<=', $data['until']));
                    }),
            ])
            ->defaultSort('sale_date', 'desc')
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
            'index' => Pages\ListSalesDailySummaries::route('/'),
        ];
    }
}
