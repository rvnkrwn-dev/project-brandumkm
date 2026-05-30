<?php

namespace App\Filament\Resources\UmkmDesignResource\Pages;

use App\Filament\Resources\UmkmDesignResource;
use App\Models\UmkmDesign;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUmkmDesign extends CreateRecord
{
    protected static string $resource = UmkmDesignResource::class;

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Submit');
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Cancel');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Jika UMKM sudah punya design dengan status revision_needed, arahkan ke edit
        if (!empty($data['umkm_id'])) {
            $existing = UmkmDesign::where('umkm_id', $data['umkm_id'])
                ->where('status', 'revision_needed')
                ->latest()
                ->first();

            if ($existing) {
                Notification::make()
                    ->title('This UMKM already has a design that needs revision')
                    ->body('Please edit the existing design.')
                    ->warning()
                    ->persistent()
                    ->send();

                $this->redirect(UmkmDesignResource::getUrl('edit', ['record' => $existing->id]));
                $this->halt();
            }
        }

        return $data;
    }
}
