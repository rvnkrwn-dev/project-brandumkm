<?php

namespace App\Filament\Resources\UmkmResource\Widgets;

use App\Filament\Resources\UmkmDesignResource;
use App\Filament\Resources\UmkmResource;
use App\Models\Kota;
use App\Models\Umkm;
use App\Models\UmkmDesign;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

class UmkmPerluDesignTableWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable
    {
        return new \Illuminate\Support\HtmlString('
            <div id="tabel-antrean-design" style="scroll-margin-top: 20px;">
                UMKM Design Queue
            </div>
        ');
    }

    public function table(Table $table): Table
    {
       $user = auth()->user();

    return $table
     ->query(
            Umkm::query()
                ->whereIn('umkms.status', ['menunggu_didesain', 'approved'])
                ->leftJoin('umkm_designs', 'umkm_designs.umkm_id', '=', 'umkms.id')
                ->where(function ($query) use ($user) {
                    $query->whereNull('umkm_designs.id')
                          ->orWhere(function ($q) use ($user) {
                              $q->where('umkm_designs.status', 'revision_needed')
                                ->where('umkm_designs.designer_id', $user->id);
                          });
                })
                ->select('umkms.*')
        )
        ->columns([
            Tables\Columns\TextColumn::make('nama_usaha')
                ->label('UMKM Name')
                ->searchable()
                ->sortable(),

            // Kolom Status Desain (Mengambil dari relasi)
           Tables\Columns\TextColumn::make('status_design')
    ->label('Status Design')
    ->badge()
    // Paksa ambil state dari relasi, jika null berikan string 'waiting'
    ->state(fn ($record) => $record->umkmDesign?->status ?? 'waiting')
    ->color(fn (string $state): string => match ($state) {
        'revision_needed' => 'danger',
        'pending'         => 'warning',
        'waiting'         => 'gray', 
        default           => 'gray',
    })
    ->formatStateUsing(fn (string $state): string => match ($state) {
        'revision_needed' => 'Needs Revision',
        'pending'         => 'Awaiting Review',
        'waiting'         => 'Awaiting Design',
        default           => ucfirst($state),
    }),

            Tables\Columns\TextColumn::make('kota.nama')
                ->label('Kota')
                ->searchable()
                ->sortable(),
            
            Tables\Columns\TextColumn::make('nama_pemilik')
                ->label('Owner')
                ->searchable(),
            
            Tables\Columns\TextColumn::make('created_at')
                ->label('Submission Date')
                ->dateTime('d M Y H:i')
                ->sortable(),
        ])
        ->filters( [
            Tables\Filters\SelectFilter::make('kota_id')
                ->label( 'Filter by City' )
                ->options( Kota::pluck( 'nama', 'id' ) )    
                ->searchable(),
        ] )
                // popup view
        ->actions( [
            Tables\Actions\Action::make('proses_design')
    ->label(fn (Umkm $record): string => 
        ($record->umkmDesign?->status === 'revision_needed') ? 'Revise Now' : 'Design Now'
    )
    ->icon(fn (Umkm $record): string => 
        ($record->umkmDesign?->status === 'revision_needed') ? 'heroicon-m-arrow-path' : 'heroicon-m-paint-brush'
    )
    ->color(fn (Umkm $record): string => 
        ($record->umkmDesign?->status === 'revision_needed') ? 'danger' : 'warning'
    )
    ->url(fn (Umkm $record): string => 
        ($record->umkmDesign?->status === 'revision_needed') 
            // Langsung arahkan ke URL path edit
            ? '/admin/umkm-designs/' . $record->umkmDesign->id . '/edit?umkm=' . $record->id
            // Arahkan ke URL path create
            : '/admin/umkm-designs/create?umkm=' . $record->id
    ),
            Tables\Actions\ViewAction::make()
            ->label('View Details')
            ->slideOver()
            ->modalWidth( 'screen' )
            ->modalHeading( fn ( $record ) => $record->nama_usaha )
            ->infolist( [

                // 2. Menampilkan Alasan Reject dengan CSS Murni (Inline Styles)
                \Filament\Infolists\Components\TextEntry::make('alasan_reject')
                    ->label('Alasan Penolakan (Reject)')
                    ->placeholder('Tidak ada alasan tertulis.')
                    ->color('danger')
                    ->weight('bold')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->iconColor('danger')
                    
                    // KUNCI UTAMA: Menggunakan CSS Murni / Inline Styles
                    ->extraAttributes([
                        'style' => '
                            margin-top: 8px;
                            padding: 16px;
                            background-color: rgba(239, 68, 68, 0.08); /* Warna merah transparan soft */
                            border-left: 4px solid #ef4444;            /* Garis vertikal merah tegas */
                            border-top-right-radius: 12px;
                            border-bottom-right-radius: 12px;
                            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                            white-space: normal;
                            word-break: break-word;
                        '
                    ])
                    ->visible(fn ($record) => $record?->status === 'rejected'),

                // =========================================================================
                // INJEKSI LIGHTBOX POPUP COMPONENT (Stanby mendengarkan klik pada gambar)
                // =========================================================================
                \Filament\Infolists\Components\ViewEntry::make('image_lightbox')
                    ->view('filament.infolists.components.image-lightbox')
                    ->columnSpanFull(),

                // DATA PEMILIK
                \Filament\Infolists\Components\Section::make( 'Data Pemilik' )
                ->schema( [
                    \Filament\Infolists\Components\TextEntry::make( 'nama_pemilik' )
                    ->label( 'Nama Pemilik' ),

                    \Filament\Infolists\Components\TextEntry::make( 'nama_usaha' )
                    ->label( 'Nama Usaha' ),

                    \Filament\Infolists\Components\TextEntry::make( 'alamat_usaha' )
                    ->label( 'Alamat' ),

                    \Filament\Infolists\Components\TextEntry::make( 'no_wa' )
                    ->label( 'No WhatsApp' ),

                    \Filament\Infolists\Components\TextEntry::make( 'radius' )
                    ->label( 'Radius Alfamart' )
                    ->suffix(' Meter'),

                    \Filament\Infolists\Components\TextEntry::make( 'kota.nama' )
                    ->label( 'Kota' ),
                ] )
                ->columns( 2 ),

                // REKENING
                \Filament\Infolists\Components\Section::make( 'Data Rekening' )
                ->schema( [
                    \Filament\Infolists\Components\TextEntry::make( 'no_rekening' )
                    ->label( 'No Rekening' ),

                    \Filament\Infolists\Components\TextEntry::make( 'nama_bank' )
                    ->label( 'Bank' ),

                    \Filament\Infolists\Components\TextEntry::make( 'atas_nama_rekening' )
                    ->label( 'Atas Nama' ),
                ] )
                ->columns( 3 ),

                // LOKASI
                \Filament\Infolists\Components\Section::make( 'Lokasi' )
                ->schema( [
                    \Filament\Infolists\Components\TextEntry::make( 'latitude' )
                    ->label( 'Latitude' ),

                    \Filament\Infolists\Components\TextEntry::make( 'longitude' )
                    ->label( 'Longitude' ),

                    \Filament\Infolists\Components\TextEntry::make( 'sharelock_url' )
                    ->label( 'Google Maps' )
                    ->url( fn ( $record ) => $record->sharelock_url )
                    ->openUrlInNewTab(),
                ] )
                ->columns( 2 ),

                // UKURAN PANEL
                \Filament\Infolists\Components\Section::make('Ukuran Panel')
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('ukuran_panel_table')
                            ->view('filament.infolists.components.tabel-panel')
                            ->columnSpanFull(),
                    ]),
                
                // FOTO
                \Filament\Infolists\Components\Section::make( 'Foto' )
                ->schema( [
                    \Filament\Infolists\Components\ImageEntry::make( 'foto_depan' )
                    ->label( 'Foto Depan' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_depan) . '" })',
                    ]),

                    \Filament\Infolists\Components\ImageEntry::make( 'foto_kanan' )
                    ->label( 'Foto Kanan' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_kanan) . '" })',
                    ]),

                    \Filament\Infolists\Components\ImageEntry::make( 'foto_kiri' )
                    ->label( 'Foto Kiri' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_kiri) . '" })',
                    ]),
                    \Filament\Infolists\Components\ImageEntry::make( 'foto_plang_alfamart' )
                    ->label( 'Foto Plang Alfamart' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_plang_alfamart) . '" })',
                    ]),
                ] )
                ->columns( 3 ),

                // DESIGN GEROBAK
                \Filament\Infolists\Components\Section::make('Design Gerobak')
                ->description('Design final dan tampak gerobak yang sudah disetujui.')
                ->icon('heroicon-o-paint-brush')
                ->schema([
                    \Filament\Infolists\Components\ImageEntry::make('design_final')
                    ->label('Design Final')
                    ->height(220)
                    ->columnSpanFull() 
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_final) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_final)),

                    \Filament\Infolists\Components\ImageEntry::make('design_gerobak_depan')
                    ->label('Gerobak Tampak Depan')
                    ->height(200)
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_gerobak_depan) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_gerobak_depan)),

                    \Filament\Infolists\Components\ImageEntry::make('design_gerobak_kiri')
                    ->label('Gerobak Tampak Kiri')
                    ->height(200)
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_gerobak_kiri) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_gerobak_kiri)),

                    \Filament\Infolists\Components\ImageEntry::make('design_gerobak_kanan')
                    ->label('Gerobak Tampak Kanan')
                    ->height(200)
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_gerobak_kanan) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_gerobak_kanan)),
                ])
                ->columns(3),
                 //  Section "UMKM Terbranding" 
            \Filament\Infolists\Components\Section::make('UMKM Terbranding')
            // tambahin background dan icon biar lebih menonjol bahwa ini sudah selesai dibranding
                ->description('UMKM yang sudah selesai proses branding dan pemasangan stiker.')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('status_pasang')
                        ->label('Status Branding')
                        ->default('SELESAI BRANDING')
                        ->badge()
                        ->color('success'),
                    \Filament\Infolists\Components\TextEntry::make('updated_at')
                        ->label('Tanggal Selesai')
                        ->dateTime('d M Y H:i'),
                    \Filament\Infolists\Components\ImageEntry::make('stiker_tampak_depan')
                        ->label('Stiker Tampak Depan')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->stiker_tampak_depan) . '" })',
                        ]),
                    \Filament\Infolists\Components\ImageEntry::make('stiker_tampak_kanan')
                        ->label('Stiker Tampak Kanan')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->stiker_tampak_kanan) . '" })',
                        ]),
                    \Filament\Infolists\Components\ImageEntry::make('stiker_tampak_kiri')
                        ->label('Stiker Tampak Kiri')   
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->stiker_tampak_kiri) . '" })',
                        ]),
                    \Filament\Infolists\Components\ImageEntry::make('foto_wide')
                        ->label('Foto Wide (Keseluruhan)')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg   overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_wide) . '" })',
                        ]),
                ])->columns(2)
                ->collapsible() 
                ->visible(fn ($record) =>
                    !empty($record->design_final) ||
                    !empty($record->design_gerobak_depan) ||
                    !empty($record->design_gerobak_kiri) ||
                    !empty($record->design_gerobak_kanan)
                ),

                // TOMBOL AKSI DI BOTTOM POPUP
                \Filament\Infolists\Components\Section::make('Tindakan')
                    ->schema([
                        \Filament\Infolists\Components\Actions::make([
                            \Filament\Infolists\Components\Actions\Action::make('design_sekarang')
                                ->label(fn (Umkm $record) => $record->umkmDesign?->status === 'revision_needed' ? 'Revise Now' : 'Design Now')
                                ->icon('heroicon-o-paint-brush')
                                ->color('warning')
                                ->url(fn (Umkm $record) => $record->umkmDesign?->status === 'revision_needed'
                                    ? '/admin/umkm-designs/' . $record->umkmDesign->id . '/edit?umkm=' . $record->id
                                    : '/admin/umkm-designs/create?umkm=' . $record->id
                                ),
                        ])->columnSpanFull(),
                    ]),
            ] ),
        ] )

        // klik row buka popup
        ->recordAction('view')
        ->paginated(10)
        ->striped();
    }

    public static function canView(): bool
    {
        $userRole = auth()->user()?->role;
        return in_array($userRole, ['design']);
    }
}