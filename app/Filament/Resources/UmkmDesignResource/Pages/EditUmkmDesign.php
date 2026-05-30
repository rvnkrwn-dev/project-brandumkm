<?php

namespace App\Filament\Resources\UmkmDesignResource\Pages;

use App\Filament\Resources\UmkmDesignResource;
use App\Models\Umkm;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUmkmDesign extends EditRecord
{
    protected static string $resource = UmkmDesignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => in_array($this->record->status, ['pending', 'revision_needed'])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isDesign() && $this->record->status !== 'approved') {
            $data['status'] = 'revised';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (auth()->user()?->isDesign() && $this->record->status === 'revised' && $this->record->umkm) {
            $this->record->umkm->update(['status' => Umkm::STATUS_DESIGN_REVIEW]);
        }
    }
}



