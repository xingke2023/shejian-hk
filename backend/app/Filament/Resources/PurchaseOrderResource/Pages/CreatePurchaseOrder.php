<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_no'] = PurchaseOrder::generateOrderNo($data['store_id']);
        $data['order_type'] = 2; // 手动创建
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $total = $this->record->items()->sum('total_price');
        $this->record->update(['total_amount' => $total]);

        if ($this->record->status === 5) {
            PurchaseOrderResource::processReceiving($this->record);
        }
    }
}
