<?php

namespace App\Filament\Resources\AdminResource\Widgets;

use App\Models\Umkm;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\HtmlString;

class SummaryPerKotaWidget extends BaseWidget {
    protected static ?string $heading = 'LATEST SUBMISSIONS';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

   public static function canView(): bool
{
    // Mengambil user yang sedang login
    $user = auth()->user();

    // Memastikan user ada dan role-nya adalah 'admin' atau 'client'
    return $user && in_array($user->role, ['admin', 'client']);
}

    public function table( Table $table ): Table {
        $user = auth()->user();

        return $table
        ->query(
            Umkm::query()   
            ->with( ['kota', 'umkmDesign'] ) 
            ->when($user?->role === 'client', function ($query) {
                $query->whereIn('status', ['pending', 'design_review']);
            })
            ->latest()
            ->limit( 10 ) 
        )

        ->headerActions( [
            // 1. INJEKSI CSS MURNI (Disembunyikan secara visual, hanya memuat style)
            Tables\Actions\Action::make('custom_css_injector')
                ->label('')
                ->disabled()
                ->extraAttributes([
                    'style' => 'display: none !important; padding: 0 !important; margin: 0 !important;'
                ])
                ->icon(fn () => new HtmlString('
                    <style>
                        /* Mewarnai Latar Belakang & Teks Header Tabel */
                        .fi-ta-table thead, 
                        .fi-ta-table thead tr {
                            background-color: #ea580c !important; /* Warna Oranye Filament */
                        }
                        
                        /* Memaksa text th menjadi putih bersih, tebal, dan kontras */
                        .fi-ta-table thead th span,
                        .fi-ta-table thead th {
                            color: #ffffff !important;
                            font-weight: 700 !important;
                            letter-spacing: 0.05em !important;
                        }

                        /* Kustomisasi Tombol "Lihat Semua" agar serasi dengan Oranye (Dark Charcoal Style) */
                        .btn-lihat-semua-custom {
                            background-color: #1e293b !important; /* Slate / Dark Charcoal */
                            color: #ffffff !important;
                            border: 1px solid #334155 !important;
                            border-radius: 8px !important;
                            padding: 6px 14px !important;
                            transition: all 0.2s ease-in-out !important;
                        }

                        .btn-lihat-semua-custom:hover {
                            background-color: #334155 !important; /* Lebih terang saat di-hover */
                            transform: translateY(-1px);
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                        }
                        
                        /* Menghilangkan border default gray bawah thead */
                        .fi-ta-header-cell {
                            border-bottom: none !important;
                        }
                    </style>
                ')),

            // 2. TOMBOL UTAMA "LIHAT SEMUA"
            Tables\Actions\Action::make( 'lihat_semua' )
            ->label( 'View All' )
            ->url( route( 'filament.admin.resources.umkm.index' ) )
            ->icon( 'heroicon-m-arrow-right' )
            ->extraAttributes([
                'class' => 'btn-lihat-semua-custom' // Menyambungkan ke CSS di atas
            ]),
        ] )

        ->columns( [
            // USAHA / KOTA
            Tables\Columns\TextColumn::make( 'nama_usaha' )
            ->label( 'BUSINESS / CITY' )
            ->description( fn ( $record ) => $record->kota?->nama )
            ->weight( 'bold' )
            ->searchable(),

            // KATEGORI
            Tables\Columns\TextColumn::make( 'kategori' )
            ->label( 'CATEGORY' )
            ->badge()
            ->getStateUsing( fn ( $record ) => match(true) {
                $record->status === 'design_review' => 'Design Submission',
                $record->status === 'pending' => 'UMKM Submission',
                default => 'UMKM Submission',
            })
            ->color( fn ( $state ) => $state === 'Design Submission' ? 'info' : 'warning' ),

            // STATUS
            Tables\Columns\TextColumn::make( 'status' )
            ->label( 'STATUS' )
            ->badge()
            ->formatStateUsing( fn ( $state ) => match($state) {
                'pending' => 'AWAITING APPROVAL',
                'design_review' => 'REVIEW DESIGN',
                default => strtoupper(str_replace('_', ' ', $state)),
            })
            ->colors( [
                'warning' => 'pending',
                'info' => 'design_review',
                'success' => 'approved',
                'danger' => 'rejected',
            ] ),

            // AKSI
            Tables\Columns\IconColumn::make( 'aksi' )
            ->label( 'ACTION' )
            ->icon( 'heroicon-m-chevron-right' )
            ->color( 'gray' ),
        ] )

        // popup view
        ->actions( [
            Tables\Actions\ViewAction::make()
            ->slideOver()
            ->modalWidth( 'screen' )
            ->modalHeading( fn ( $record ) => $record->status === 'design_review' 
                ? 'Review Design: ' . $record->nama_usaha 
                : $record->nama_usaha )
            ->infolist( [

                // =========================================================================
                // SECTION: DESIGN YANG DIAJUKAN (hanya muncul saat design_review)
                // =========================================================================
                \Filament\Infolists\Components\Section::make('Submitted Design')
                    ->description('Review the design below, then Approve or Request Revision.')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('umkmDesign.nama_desainer')
                            ->label('Designer'),
                        \Filament\Infolists\Components\TextEntry::make('umkmDesign.versi')
                            ->label('Versi')
                            ->badge(),
                        \Filament\Infolists\Components\TextEntry::make('umkmDesign.catatan_revisi')
                            ->label('Previous Revision Notes')
                            ->visible(fn ($record) => !empty($record->umkmDesign?->catatan_revisi))
                            ->color('warning'),
                        \Filament\Infolists\Components\ImageEntry::make('umkmDesign.file_path')
                            ->label('File Design Final (FA)')
                            ->height(300)
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\ImageEntry::make('umkmDesign.gerobak_depan')
                            ->label('Mockup Gerobak Depan')
                            ->height(200),
                        \Filament\Infolists\Components\ImageEntry::make('umkmDesign.gerobak_kiri')
                            ->label('Mockup Gerobak Kiri')
                            ->height(200),
                        \Filament\Infolists\Components\ImageEntry::make('umkmDesign.gerobak_kanan')
                            ->label('Mockup Gerobak Kanan')
                            ->height(200),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->status === 'design_review'),

                // Info singkat UMKM (untuk konteks saat review design)
                \Filament\Infolists\Components\Section::make('UMKM Info')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('nama_usaha')->label('Business Name'),
                        \Filament\Infolists\Components\TextEntry::make('nama_pemilik')->label('Pemilik'),
                        \Filament\Infolists\Components\TextEntry::make('kota.nama')->label('Kota'),
                        \Filament\Infolists\Components\TextEntry::make('alamat_usaha')->label('Address'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->status === 'design_review'),

                // =========================================================================
                // SECTION: PROFIL UMKM LENGKAP (hanya muncul saat BUKAN design_review)
                // =========================================================================

                // 2. Menampilkan Alasan Reject dengan CSS Murni (Inline Styles)
                \Filament\Infolists\Components\TextEntry::make('alasan_reject')
                    ->label('Rejection Reason')
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
                \Filament\Infolists\Components\Section::make( 'Owner Data' )
                ->visible(fn ($record) => $record->status !== 'design_review')
                ->schema( [
                    \Filament\Infolists\Components\TextEntry::make( 'nama_pemilik' )
                    ->label( 'Owner Name' ),

                    \Filament\Infolists\Components\TextEntry::make( 'nama_usaha' )
                    ->label( 'Business Name' ),

                    \Filament\Infolists\Components\TextEntry::make( 'alamat_usaha' )
                    ->label( 'Address' ),

                    \Filament\Infolists\Components\TextEntry::make( 'no_wa' )
                    ->label( 'WhatsApp Number' ),

                    \Filament\Infolists\Components\TextEntry::make( 'radius' )
                    ->label( 'Alfamart Radius' )
                    ->suffix(' Meter'),

                    \Filament\Infolists\Components\TextEntry::make( 'kota.nama' )
                    ->label( 'Kota' ),
                ] )
                ->columns( 2 ),

                // REKENING
                \Filament\Infolists\Components\Section::make( 'Bank Account' )
                ->visible(fn ($record) => $record->status !== 'design_review')
                ->schema( [
                    \Filament\Infolists\Components\TextEntry::make( 'no_rekening' )
                    ->label( 'Account Number' ),

                    \Filament\Infolists\Components\TextEntry::make( 'nama_bank' )
                    ->label( 'Bank' ),

                    \Filament\Infolists\Components\TextEntry::make( 'atas_nama_rekening' )
                    ->label( 'Account Holder' ),
                ] )
                ->columns( 3 ),

                // LOKASI
                \Filament\Infolists\Components\Section::make( 'Location' )
                ->visible(fn ($record) => $record->status !== 'design_review')
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
                \Filament\Infolists\Components\Section::make('Panel Dimensions')
                    ->visible(fn ($record) => $record->status !== 'design_review')
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('ukuran_panel_table')
                            ->view('filament.infolists.components.tabel-panel')
                            ->columnSpanFull(),
                    ]),
                
                // FOTO
                \Filament\Infolists\Components\Section::make( 'Foto' )
                ->visible(fn ($record) => $record->status !== 'design_review')
                ->schema( [
                    \Filament\Infolists\Components\ImageEntry::make( 'foto_depan' )
                    ->label( 'Front Photo' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_depan) . '" })',
                    ]),

                    \Filament\Infolists\Components\ImageEntry::make( 'foto_kanan' )
                    ->label( 'Right Photo' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_kanan) . '" })',
                    ]),

                    \Filament\Infolists\Components\ImageEntry::make( 'foto_kiri' )
                    ->label( 'Left Photo' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_kiri) . '" })',
                    ]),
                    \Filament\Infolists\Components\ImageEntry::make( 'foto_plang_alfamart' )
                    ->label( 'Alfamart Sign Photo' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_plang_alfamart) . '" })',
                    ]),
                    \Filament\Infolists\Components\ImageEntry::make( 'foto_tampak_jauh' )
                    ->label( 'Distance Photo' )
                    ->height( 200 )
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_tampak_jauh) . '" })',
                    ]),
                            // video validasi
                   \Filament\Infolists\Components\ViewEntry::make('video_validasi')
            ->label('Validation Video')
            ->view('filament.infolists.components.video-player')
            ->visible(fn ($record) => !empty($record->video_validasi))
                ->columns(3),
                ] )
                ->columns( 3 ),

                // DESIGN GEROBAK
                \Filament\Infolists\Components\Section::make('Cart Design')
                ->description('Approved final design and cart mockups.')
                ->icon('heroicon-o-paint-brush')
                ->visible(fn ($record) => $record->status !== 'design_review' && (
                    !empty($record->design_final) ||
                    !empty($record->design_gerobak_depan) ||
                    !empty($record->design_gerobak_kiri) ||
                    !empty($record->design_gerobak_kanan)
                ))
                ->schema([
                    \Filament\Infolists\Components\ImageEntry::make('design_final')
                    ->label('Final Design')
                    ->height(220)
                    ->columnSpanFull() 
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_final) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_final)),

                    \Filament\Infolists\Components\ImageEntry::make('design_gerobak_depan')
                    ->label('Cart Front View')
                    ->height(200)
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_gerobak_depan) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_gerobak_depan)),

                    \Filament\Infolists\Components\ImageEntry::make('design_gerobak_kiri')
                    ->label('Cart Left View')
                    ->height(200)
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_gerobak_kiri) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_gerobak_kiri)),

                    \Filament\Infolists\Components\ImageEntry::make('design_gerobak_kanan')
                    ->label('Cart Right View')
                    ->height(200)
                    ->extraAttributes(fn ($record) => [
                        'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                        'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->design_gerobak_kanan) . '" })',
                    ])
                    ->visible(fn ($record) => !empty($record->design_gerobak_kanan)),
                ]),
                 //  Section "UMKM Terbranding" 
            \Filament\Infolists\Components\Section::make('Branded UMKM')
            // tambahin background dan icon biar lebih menonjol bahwa ini sudah selesai dibranding
                ->description('UMKM that completed branding and sticker installation.')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('status_pasang')
                        ->label('Branding Status')
                        ->default('BRANDING COMPLETE')
                        ->badge()
                        ->color('success'),
                    \Filament\Infolists\Components\TextEntry::make('updated_at')
                        ->label('Completion Date')
                        ->dateTime('d M Y H:i'),
                    \Filament\Infolists\Components\ImageEntry::make('stiker_tampak_depan')
                        ->label('Sticker Front View')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->stiker_tampak_depan) . '" })',
                        ]),
                    \Filament\Infolists\Components\ImageEntry::make('stiker_tampak_kanan')
                        ->label('Sticker Right View')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->stiker_tampak_kanan) . '" })',
                        ]),
                    \Filament\Infolists\Components\ImageEntry::make('stiker_tampak_kiri')
                        ->label('Sticker Left View')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->stiker_tampak_kiri) . '" })',
                        ]),
                    \Filament\Infolists\Components\ImageEntry::make('foto_wide')
                        ->label('Wide Photo (Overall)')
                        ->height(200)
                        ->extraAttributes(fn ($record) => [
                            'class' => 'cursor-pointer hover:scale-105 transition duration-300 rounded-lg   overflow-hidden',
                            'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_wide) . '" })',
                        ]),
                ])->visible(fn ($record) =>
                    $record->status !== 'design_review' && (
                    !empty($record->stiker_tampak_depan) ||
                    !empty($record->stiker_tampak_kanan) ||
                    !empty($record->stiker_tampak_kiri) ||
                    !empty($record->foto_wide)
                    )
                ),
            ] )
        ->extraModalFooterActions(fn (Tables\Actions\ViewAction $action): array => [
            Tables\Actions\Action::make('approve_from_widget')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Umkm $record) => $record->status === 'pending' && auth()->user()?->role === 'client')
                ->action(function (Umkm $record) {
                    $record->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ]);
                    \Filament\Notifications\Notification::make()->title('UMKM Approved ✅')->success()->send();
                }),
            Tables\Actions\Action::make('reject_from_widget')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Umkm $record) => $record->status === 'pending' && auth()->user()?->role === 'client')
                ->form([
                    \Filament\Forms\Components\Textarea::make('alasan_reject')
                        ->label('Rejection Reason')
                        ->required(),
                ])
                ->action(function (Umkm $record, array $data) {
                    $record->update([
                        'status' => 'rejected',
                        'alasan_reject' => $data['alasan_reject'],
                    ]);
                    \Filament\Notifications\Notification::make()->title('UMKM Rejected ❌')->danger()->send();
                }),
            Tables\Actions\Action::make('approve_design_from_widget')
                ->label('Approve Design')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Umkm $record) => $record->status === 'design_review' && auth()->user()?->role === 'client')
                ->action(function (Umkm $record) {
                    $design = $record->umkmDesign;
                    if ($design) {
                        $design->update(['status' => 'approved']);
                    }
                    $record->update(['status' => Umkm::STATUS_DESIGN_APPROVED]);
                    \Filament\Notifications\Notification::make()->title('Design Approved ✅')->success()->send();
                }),
            Tables\Actions\Action::make('revisi_design_from_widget')
                ->label('Request Revision')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (Umkm $record) => $record->status === 'design_review' && auth()->user()?->role === 'client')
                ->form([
                    \Filament\Forms\Components\Textarea::make('catatan_revisi')
                        ->label('Revision Notes')
                        ->required(),
                ])
                ->action(function (Umkm $record, array $data) {
                    $design = $record->umkmDesign;
                    if ($design) {
                        $design->update([
                            'status' => 'revision_needed',
                            'catatan_revisi' => $data['catatan_revisi'],
                        ]);
                    }
                    $record->update(['status' => Umkm::STATUS_REVISION_NEEDED]);
                    \Filament\Notifications\Notification::make()->title('Revision Requested 🔄')->warning()->send();
                }),
        ]),
        ] )

        // supaya klik row buka popup
        ->recordAction( 'view' )
        ->paginated()
        ->striped()
        ->poll('5s');
    }
}