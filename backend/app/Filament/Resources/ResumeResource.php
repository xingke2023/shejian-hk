<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResumeResource\Pages;
use App\Models\Resume;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResumeResource extends Resource
{
    protected static ?string $model = Resume::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = '人才库';

    protected static ?string $navigationLabel = '简历档案';

    protected static ?string $modelLabel = '简历';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('姓名')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('phone')
                            ->label('电话')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('gender')
                            ->label('性别')
                            ->options([0 => '未知', 1 => '男', 2 => '女'])
                            ->default(0),
                        Forms\Components\TextInput::make('age')
                            ->label('年龄')
                            ->numeric()
                            ->minValue(15)
                            ->maxValue(99),
                        Forms\Components\Select::make('education')
                            ->label('学历')
                            ->options([1 => '初中', 2 => '高中', 3 => '大专', 4 => '本科'])
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([0 => '无效', 1 => '求职中', 2 => '已入职', 3 => '暂不求职'])
                            ->default(1)
                            ->required(),
                    ]),

                Forms\Components\Section::make('求职意向')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TagsInput::make('districts')
                            ->label('意向区域')
                            ->placeholder('添加区域，如筲箕湾')
                            ->suggestions(['筲箕湾', '柴湾', '西湾河', '杏花邨', '鲗鱼涌', '天后', '铜锣湾', '旺角', '元朗', '屯门']),
                        Forms\Components\TagsInput::make('work_types')
                            ->label('工作类型')
                            ->suggestions(['全职', '兼职', '小时工']),
                        Forms\Components\TagsInput::make('positions')
                            ->label('意向岗位')
                            ->placeholder('添加岗位')
                            ->suggestions(['收银员', '理货员', '生鲜切配', '生鲜销售', '清洁员', '仓务员', '店长', '副店长']),
                        Forms\Components\TagsInput::make('languages')
                            ->label('语言能力')
                            ->suggestions(['粤语', '普通话', '英语']),
                        Forms\Components\TagsInput::make('skills')
                            ->label('技能标签')
                            ->placeholder('添加技能')
                            ->suggestions(['生鲜处理', '收银', '陈列', '库存管理', '驾驶', '食品安全证书']),
                    ]),

                Forms\Components\Section::make('薪资与档期')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('experience_years')
                            ->label('工作经验（年）')
                            ->numeric()
                            ->step(0.5),
                        Forms\Components\TextInput::make('salary_min')
                            ->label('期望薪资（最低）')
                            ->numeric()
                            ->prefix('¥'),
                        Forms\Components\TextInput::make('salary_max')
                            ->label('期望薪资（最高）')
                            ->numeric()
                            ->prefix('¥'),
                        Forms\Components\Select::make('salary_unit')
                            ->label('薪资单位')
                            ->options([1 => '元/月', 2 => '元/日', 3 => '元/小时'])
                            ->default(1),
                        Forms\Components\DatePicker::make('availability_date')
                            ->label('最早到岗日期'),
                        Forms\Components\Select::make('source')
                            ->label('来源')
                            ->options([1 => '手动录入', 2 => 'AI解析', 3 => '文件上传'])
                            ->default(1),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('备注')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('raw_text')
                    ->label('原始文本')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('姓名')
                    ->searchable()
                    ->placeholder('未知'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('电话')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('positions')
                    ->label('意向岗位')
                    ->formatStateUsing(function ($state): string {
                        $arr = is_array($state) ? $state : json_decode((string) $state, true);
                        return $arr ? implode(' / ', array_slice($arr, 0, 3)) : '—';
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('districts')
                    ->label('意向区域')
                    ->formatStateUsing(function ($state): string {
                        $arr = is_array($state) ? $state : json_decode((string) $state, true);
                        return $arr ? implode(' · ', $arr) : '—';
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('work_types')
                    ->label('工作类型')
                    ->formatStateUsing(function ($state): string {
                        $arr = is_array($state) ? $state : json_decode((string) $state, true);
                        return $arr ? implode(' · ', $arr) : '—';
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('experience_years')
                    ->label('经验')
                    ->formatStateUsing(fn (?string $state): string => $state ? "{$state}年" : '—'),
                Tables\Columns\TextColumn::make('salary_min')
                    ->label('薪资')
                    ->formatStateUsing(function ($record): string {
                        if (! $record->salary_min && ! $record->salary_max) {
                            return '面议';
                        }
                        $unit = match ($record->salary_unit) {
                            1 => '/月', 2 => '/日', 3 => '/时', default => '',
                        };
                        if ($record->salary_min && $record->salary_max) {
                            return "{$record->salary_min}~{$record->salary_max}{$unit}";
                        }

                        return ($record->salary_min ?? $record->salary_max).$unit;
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        0 => '无效', 1 => '求职中', 2 => '已入职', 3 => '暂不求职', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success', 2 => 'info', 3 => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('source')
                    ->label('来源')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '手动', 2 => 'AI', 3 => '上传', default => '—',
                    })
                    ->badge()
                    ->color(fn (int $state): string => $state === 2 ? 'primary' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('录入时间')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([0 => '无效', 1 => '求职中', 2 => '已入职', 3 => '暂不求职']),
                Tables\Filters\SelectFilter::make('source')
                    ->label('来源')
                    ->options([1 => '手动录入', 2 => 'AI解析', 3 => '文件上传']),
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
            'index'  => Pages\ListResumes::route('/'),
            'create' => Pages\CreateResume::route('/create'),
            'edit'   => Pages\EditResume::route('/{record}/edit'),
        ];
    }
}
