<?php

namespace App\Filament\Resources\SalesDailySummaryResource\Pages;

use App\Filament\Resources\SalesDailySummaryResource;
use Filament\Resources\Pages\ListRecords;

class ListSalesDailySummaries extends ListRecords
{
    protected static string $resource = SalesDailySummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
