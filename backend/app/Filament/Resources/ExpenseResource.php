<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static string | \UnitEnum | null $navigationGroup = '财务收支';

    protected static ?string $navigationLabel = '支出记录';

    protected static ?string $modelLabel = '支出';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('支出信息')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('门店')
                            ->relationship('store', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('category_id')
                            ->label('支出分类')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('expense_no')
                            ->label('支出单号')
                            ->default(fn () => 'EXP-'.strtoupper(Str::random(8)))
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('amount')
                            ->label('金额（元）')
                            ->numeric()
                            ->required()
                            ->prefix('¥'),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('支出日期')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('payment_method')
                            ->label('支付方式')
                            ->options([
                                1 => '现金', 2 => '转账', 3 => '微信支付',
                                4 => '支付宝', 5 => '企业网银',
                            ])
                            ->default(1)
                            ->required(),
                        Forms\Components\Select::make('payment_status')
                            ->label('支付状态')
                            ->options([1 => '待支付', 2 => '已支付', 3 => '已报销'])
                            ->default(1)
                            ->required(),
                        Forms\Components\Select::make('supplier_id')
                            ->label('关联供应商')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('vendor_name')
                            ->label('供应商/商家名称')
                            ->maxLength(200),
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
                Tables\Columns\TextColumn::make('store.name')
                    ->label('门店')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('分类')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_no')
                    ->label('单号')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('金额')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('支出日期')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('支付方式')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '现金', 2 => '转账', 3 => '微信支付',
                        4 => '支付宝', 5 => '企业网银', default => '—',
                    })
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('支付状态')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '待支付', 2 => '已支付', 3 => '已报销', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'warning', 2 => 'success', 3 => 'info', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('input_method')
                    ->label('录入方式')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '手动', 2 => 'AI', 3 => '系统', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        2 => 'primary', default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vendor_name')
                    ->label('商家')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('门店')
                    ->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('支付状态')
                    ->options([1 => '待支付', 2 => '已支付', 3 => '已报销']),
                Tables\Filters\SelectFilter::make('input_method')
                    ->label('录入方式')
                    ->options([1 => '手动', 2 => 'AI', 3 => '系统']),
                Tables\Filters\Filter::make('expense_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('开始日期'),
                        Forms\Components\DatePicker::make('until')->label('结束日期'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('expense_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('expense_date', '<=', $date));
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expense_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
