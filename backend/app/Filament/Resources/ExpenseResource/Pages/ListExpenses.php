<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\Store;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('全部门店'),
        ];

        $stores = Store::query()->where('status', 1)->orderBy('id')->get();

        foreach ($stores as $store) {
            $tabs[(string) $store->id] = Tab::make($store->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('store_id', $store->id));
        }

        return $tabs;
    }
}
