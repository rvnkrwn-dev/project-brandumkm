<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UmkmDesignResource\Pages;
use App\Filament\Resources\UmkmResource;
use App\Models\Kota;
use App\Models\Umkm;
use App\Models\UmkmDesign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UmkmDesignResource extends Resource
{
    protected static ?string $model = UmkmDesign::class;
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationGroup = 'UMKM Data';
    protected static ?string $label = 'Design History';
    protected static ?string $pluralLabel = 'Design History';
    protected static ?string $navigationLabel = 'Design History';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['design', 'client', 'admin']);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->role === 'design') {
            $query->where('designer_id', $user->id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['design', 'client', 'admin'])) return null;

        if ($user->role === 'design') {
            $count = UmkmDesign::where('status', 'revision_needed')
                ->where('designer_id', $user->id)
                ->count();
        } else {
            $count = UmkmDesign::whereIn('status', ['pending', 'revised'])->count();
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['design', 'client', 'admin'])) return null;

        if ($user->role === 'design') {
            $count = UmkmDesign::where('status', 'revision_needed')
                ->where('designer_id', $user->id)
                ->count();
            return $count > 0 ? 'danger' : null;
        } else {
            $hasRevised = UmkmDesign::where('status', 'revised')->exists();
            if ($hasRevised) return 'warning';
            $hasPending = UmkmDesign::where('status', 'pending')->exists();
            if ($hasPending) return 'warning';
            return null;
        }
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Design menunggu review';
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->role === 'design';
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'design';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload Design')
                    ->schema([
                        Forms\Components\Select::make('kota_id')
                            ->label('Filter Kota')
                            ->placeholder('Pilih kota terlebih dahulu')
                            ->options(\App\Models\Kota::orderBy('nama')->pluck('nama', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('umkm_id', null))
                            ->afterStateHydrated(function (Forms\Set $set, ?\App\Models\UmkmDesign $record) {
                                if ($record && $record->umkm) {
                                    $set('kota_id', $record->umkm->kota_id);
                                    return;
                                }

                                $umkmIdFromUrl = request()->query('umkm');
                                if ($umkmIdFromUrl) {
                                    $umkm = \App\Models\Umkm::find($umkmIdFromUrl);
                                    if ($umkm && $umkm->kota_id) {
                                        $set('kota_id', $umkm->kota_id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('umkm_id')
                            ->label('UMKM')
                            ->relationship('umkm', 'nama_usaha')
                            ->placeholder(fn (Forms\Get $get) =>
                                $get('kota_id') ? 'Pilih UMKM' : 'Pilih UMKM terlebih dahulu'
                            )
                            ->options(function (Forms\Get $get) {
                                $kotaId = $get('kota_id');
                                if (!$kotaId) return [];

                                return Umkm::whereIn('status', ['approved', 'menunggu_didesain'])
                                    ->where('kota_id', $kotaId)
                                    ->get()
                                    ->mapWithKeys(fn ($umkm) => [
                                        $umkm->id => $umkm->nama_usaha . ' - ' . $umkm->nama_pemilik,
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(
                                table: 'umkm_designs',
                                column: 'umkm_id',
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, Forms\Get $get) {
                                    return $rule->where(function ($query) {
                                        $query->where('status', '!=', 'revision_needed');
                                    });
                                }
                            )
                            ->validationMessages([
                                'unique' => 'UMKM ini sudah memiliki data desain. Mohon pilih UMKM lain atau edit data yang sudah ada.',
                            ])
                            ->default(request()->query('umkm'))
                            ->disabled(fn (Forms\Get $get) => !$get('kota_id'))
                            ->live(),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Upload Flat Mockup / Final Artwork Design (Wajib 1 Gambar)')
                            ->directory(fn (Forms\Get $get) => 'umkm/' . (Umkm::find($get('umkm_id'))?->kota_id ?: 'temp') . '/design')
                            ->required(),

                        Forms\Components\TextInput::make('nama_desainer')
                            ->label('Designer Name')
                            ->placeholder('Nama desainer untuk identifikasi client')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('gerobak_depan')
                            ->label('Mockup Design Gerobak Depan (Wajib)')
                            ->directory(fn (Forms\Get $get) => 'umkm/' . (Umkm::find($get('umkm_id'))?->kota_id ?: 'temp') . '/design')
                            ->image()
                            ->required(),

                        Forms\Components\FileUpload::make('gerobak_kiri')
                            ->label('Mockup Design Gerobak Kiri (Wajib)')
                            ->directory(fn (Forms\Get $get) => 'umkm/' . (Umkm::find($get('umkm_id'))?->kota_id ?: 'temp') . '/design')
                            ->image()
                            ->required(),

                        Forms\Components\FileUpload::make('gerobak_kanan')
                            ->label('Mockup Design Gerobak Kanan (Wajib)')
                            ->directory(fn (Forms\Get $get) => 'umkm/' . (Umkm::find($get('umkm_id'))?->kota_id ?: 'temp') . '/design')
                            ->image()
                            ->required(),

                        Forms\Components\Hidden::make('designer_id')
                            ->default(auth()->id()),

                        Forms\Components\Hidden::make('versi')
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('umkm.nama_usaha')
                    ->label('UMKM')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('umkm.kota.nama')
                    ->label('Kota')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('versi')
                    ->label('Revision')
                    ->badge(),
                Tables\Columns\TextColumn::make('designer.name')
                    ->label('Designer'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'revision_needed' => 'danger',
                        'revised' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'revision_needed' => 'Perlu Revisi',
                        'revised' => 'Sudah Direvisi',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('catatan_revisi')
                    ->label('Catatan')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->catatan_revisi),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Upload')
                    ->dateTime('d M Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kota_id')
                    ->label('Kota')
                    ->options(Kota::orderBy('nama')->pluck('nama', 'id'))
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('umkm', fn ($q) => $q->where('kota_id', $data['value']));
                        }
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'review' => 'Review (Pending + Revised)',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'revision_needed' => 'Perlu Revisi',
                        'revised' => 'Sudah Direvisi',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) return;
                        if ($data['value'] === 'review') {
                            $query->whereIn('status', ['pending', 'revised']);
                        } else {
                            $query->where('status', $data['value']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()?->isDesign())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = 'revised';
                        return $data;
                    })
                    ->after(function (UmkmDesign $record) {
                        if ($record->umkm) {
                            $record->umkm->update(['status' => Umkm::STATUS_DESIGN_REVIEW]);
                        }
                    })
                    ->successNotificationTitle('Design berhasil direvisi & dikirim ke client untuk review ulang'),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()?->isDesign() && in_array($record->status, ['pending', 'revision_needed'])),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (UmkmDesign $record) =>
                        in_array($record->status, ['pending', 'revised']) &&
                        auth()->user()->isClient()
                    )
                    ->action(function (UmkmDesign $record) {
                        // Observer UmkmDesignObserver::updated() handles UMKM status + notifications
                        $record->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Design disetujui ✅')->success()->send();
                    }),

                Tables\Actions\Action::make('revisi')
                    ->label('Minta Revisi')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('catatan_revisi')
                            ->label('Revision Notes')
                            ->required(),
                    ])
                    ->visible(fn (UmkmDesign $record) =>
                        in_array($record->status, ['pending', 'revised']) &&
                        auth()->user()->isClient()
                    )
                    ->action(function (UmkmDesign $record, array $data) {
                        $record->update([
                            'status' => 'revision_needed',
                            'catatan_revisi' => $data['catatan_revisi'],
                            'versi' => $record->versi + 1,
                        ]);

                        if ($record->umkm) {
                            $record->umkm->update([
                                'status' => Umkm::STATUS_REVISION_NEEDED,
                            ]);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\ViewEntry::make('image_lightbox')
                    ->view('filament.infolists.components.image-lightbox')
                    ->columnSpanFull(),

                \Filament\Infolists\Components\Section::make('Informasi UMKM & Versi Design')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('umkm.nama_usaha')->label('Nama Usaha'),
                        \Filament\Infolists\Components\TextEntry::make('versi')->label('Revision')->badge(),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'revision_needed' => 'danger',
                                'revised' => 'info',
                                default => 'gray',
                            }),
                        \Filament\Infolists\Components\TextEntry::make('catatan_revisi')
                            ->label('Revision Notes')
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->catatan_revisi)),
                    ])->columns(3),

                \Filament\Infolists\Components\Section::make('Berkas Gambar Hasil Design')
                    ->schema([
                        \Filament\Infolists\Components\ImageEntry::make('file_path')
                            ->label('File Design Final')
                            ->height(200)
                            ->extraAttributes(fn ($record) => [
                                'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700',
                                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->file_path) . '" })',
                            ]),

                        \Filament\Infolists\Components\ImageEntry::make('gerobak_depan')
                            ->label('Cart Mockup Front')
                            ->height(200)
                            ->extraAttributes(fn ($record) => [
                                'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700',
                                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->gerobak_depan) . '" })',
                            ]),

                        \Filament\Infolists\Components\ImageEntry::make('gerobak_kiri')
                            ->label('Cart Mockup Left')
                            ->height(200)
                            ->extraAttributes(fn ($record) => [
                                'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700',
                                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->gerobak_kiri) . '" })',
                            ]),

                        \Filament\Infolists\Components\ImageEntry::make('gerobak_kanan')
                            ->label('Cart Mockup Right')
                            ->height(200)
                            ->extraAttributes(fn ($record) => [
                                'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700',
                                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->gerobak_kanan) . '" })',
                            ]),
                    ])->columns(2),

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUmkmDesigns::route('/'),
            'create' => Pages\CreateUmkmDesign::route('/create'),
            'view' => Pages\ViewUmkmDesign::route('/{record}'),
            'edit' => Pages\EditUmkmDesign::route('/{record}/edit'),
        ];
    }
}
