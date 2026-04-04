<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\Store;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;

    protected static string $view = 'filament.resources.sales-orders.list';

    protected ?string $heading = '每日营运情况';

    public function getBreadcrumbs(): array
    {
        return [];
    }

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
            ->when($this->date, fn ($q) => $q->whereDate('sold_at', $this->date))
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
