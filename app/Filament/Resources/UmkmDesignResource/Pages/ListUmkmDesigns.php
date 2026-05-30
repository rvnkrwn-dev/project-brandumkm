<?php

namespace App\Filament\Resources\UmkmDesignResource\Pages;

use App\Filament\Resources\UmkmDesignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUmkmDesigns extends ListRecords {
    protected static string $resource = UmkmDesignResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\CreateAction::make()->label('Add New Design'),
        ];
    }
}
