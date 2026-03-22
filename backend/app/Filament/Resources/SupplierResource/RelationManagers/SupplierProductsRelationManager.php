<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierProducts';

    protected static ?string $title = '供货商品';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('商品')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('supplier_product_code')
                    ->label('供应商商品编码')
                    ->maxLength(100),
                Forms\Components\TextInput::make('purchase_price')
                    ->label('采购单价（元）')
                    ->numeric()
                    ->required()
                    ->prefix('¥'),
                Forms\Components\TextInput::make('min_order_qty')
                    ->label('最小订购量')
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('delivery_lead_days')
                    ->label('交货周期（天）')
                    ->numeric()
                    ->nullable(),
                Forms\Components\Toggle::make('is_primary')
                    ->label('首选供应商')
                    ->default(false),
                Forms\Components\DatePicker::make('price_effective_date')
                    ->label('价格生效日期'),
                Forms\Components\DatePicker::make('price_expired_date')
                    ->label('价格失效日期'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('商品名称')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier_product_code')
                    ->label('供应商编码')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('采购单价')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_order_qty')
                    ->label('最小订购量'),
                Tables\Columns\TextColumn::make('delivery_lead_days')
                    ->label('交货天数')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('首选')
                    ->boolean(),
                Tables\Columns\TextColumn::make('price_effective_date')
                    ->label('生效日期')
                    ->date('Y-m-d')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
