<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Models\Store;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInventory extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected string $view = 'filament.resources.inventory.list';

    public string $date = '';

    public string $storeId = '';

    public function mount(): void
    {
        parent::mount();
        $this->date = today()->toDateString();
    }

    public function getStoreOptionsProperty(): array
    {
        return Store::query()->where('status', 1)->orderBy('id')->pluck('name', 'id')->all();
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
