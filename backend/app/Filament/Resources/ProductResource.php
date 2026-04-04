<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = '供应商及商品';

    protected static ?string $navigationLabel = '商品档案';

    protected static ?string $modelLabel = '商品';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('商品名称')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\Select::make('supplier_id')
                            ->label('供应商')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('category_id')
                            ->label('分类')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('code')
                            ->label('SKU编码')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('barcode')
                            ->label('条形码')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('brand')
                            ->label('品牌')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('unit')
                            ->label('基本单位')
                            ->required()
                            ->default('斤')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('spec')
                            ->label('规格描述')
                            ->maxLength(200),
                        Forms\Components\Toggle::make('is_fresh')
                            ->label('生鲜品')
                            ->default(false),
                    ]),

                Forms\Components\Section::make('库存与采购')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('shelf_life_days')
                            ->label('保质期（天）')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\Select::make('storage_condition')
                            ->label('储存条件')
                            ->options([1 => '常温', 2 => '冷藏', 3 => '冷冻'])
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('min_order_qty')
                            ->label('最小采购量')
                            ->numeric()
                            ->default(1),
                        Forms\Components\TextInput::make('purchase_unit')
                            ->label('采购单位')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('purchase_unit_qty')
                            ->label('采购单位含基本单位数')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([0 => '下架', 1 => '正常', 2 => '待审核'])
                            ->default(1)
                            ->required(),
                    ]),

                Forms\Components\Textarea::make('description')
                    ->label('描述')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('商品名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('供应商')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('分类')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('code')
                    ->label('SKU')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit')
                    ->label('单位'),
                Tables\Columns\IconColumn::make('is_fresh')
                    ->label('生鲜')
                    ->boolean(),
                Tables\Columns\TextColumn::make('storage_condition')
                    ->label('储存')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '常温', 2 => '冷藏', 3 => '冷冻', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'gray', 2 => 'info', 3 => 'primary', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        0 => '下架', 1 => '正常', 2 => '待审核', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        0 => 'danger', 1 => 'success', 2 => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('供应商')
                    ->relationship('supplier', 'name'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_fresh')
                    ->label('生鲜品'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([0 => '下架', 1 => '正常', 2 => '待审核']),
                Tables\Filters\SelectFilter::make('storage_condition')
                    ->label('储存条件')
                    ->options([1 => '常温', 2 => '冷藏', 3 => '冷冻']),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
