<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = '库存管理';

    protected static ?string $navigationLabel = '门店库存';

    protected static ?string $modelLabel = '库存';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->label('门店')
                    ->relationship('store', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('product_id')
                    ->label('商品')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('current_qty')
                    ->label('当前库存')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('available_qty')
                    ->label('可用库存')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('locked_qty')
                    ->label('锁定库存')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('avg_cost')
                    ->label('平均成本')
                    ->numeric()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('门店')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('商品名称')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.unit')
                    ->label('单位'),
                Tables\Columns\TextColumn::make('current_qty')
                    ->label('当前库存')
                    ->sortable()
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('available_qty')
                    ->label('可用库存')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('avg_cost')
                    ->label('平均成本')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_in_at')
                    ->label('最近入库')
                    ->dateTime('m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_out_at')
                    ->label('最近出库')
                    ->dateTime('m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('门店')
                    ->relationship('store', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('低库存（≤10）')
                    ->query(fn ($query) => $query->where('current_qty', '<=', 10)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('store_id');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventory::route('/'),
            'edit'  => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
