<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = '供应商及商品管理';

    protected static ?string $navigationLabel = '供应商档案';

    protected static ?string $modelLabel = '供应商';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('供应商名称')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\TextInput::make('code')
                            ->label('编码')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('contact_name')
                            ->label('联系人')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('联系电话')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('contact_wechat')
                            ->label('微信')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('address')
                            ->label('地址')
                            ->maxLength(300)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('business_license')
                            ->label('营业执照')
                            ->maxLength(200),
                    ]),

                Forms\Components\Section::make('结算与交货')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('payment_terms')
                            ->label('结算方式')
                            ->options([1 => '现款', 2 => '月结', 3 => '季结'])
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('payment_days')
                            ->label('账期天数')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('delivery_lead_days')
                            ->label('交货周期（天）')
                            ->numeric()
                            ->default(1),
                        Forms\Components\Select::make('rating')
                            ->label('评级')
                            ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([0 => '停用', 1 => '正常'])
                            ->default(1)
                            ->required(),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('备注')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('供应商名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('编码')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('联系人')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('联系电话')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('payment_terms')
                    ->label('结算方式')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '现款', 2 => '月结', 3 => '季结', default => '—',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('delivery_lead_days')
                    ->label('交货周期')
                    ->suffix(' 天'),
                Tables\Columns\TextColumn::make('rating')
                    ->label('评级')
                    ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('★', $state) : '—')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        0 => '停用', 1 => '正常', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => $state === 1 ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_terms')
                    ->label('结算方式')
                    ->options([1 => '现款', 2 => '月结', 3 => '季结']),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([0 => '停用', 1 => '正常']),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SupplierProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
