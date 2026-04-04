<?php

namespace App\Filament\Resources\InventoryDailySnapshotResource\Pages;

use App\Filament\Resources\InventoryDailySnapshotResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryDailySnapshots extends ListRecords
{
    protected static string $resource = InventoryDailySnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
