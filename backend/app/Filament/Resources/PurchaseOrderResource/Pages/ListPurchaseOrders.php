<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Store;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'filament.resources.purchase-orders.list';

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
            ->when($this->date, fn ($q) => $q->whereDate('created_at', $this->date))
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
