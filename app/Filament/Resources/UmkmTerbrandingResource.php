<?php

namespace App\Filament\Resources;

use App\Exports\UmkmExport;
use App\Filament\Resources\UmkmTerbrandingResource\Pages;
use App\Models\Kota;
use App\Models\Umkm;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class UmkmTerbrandingResource extends Resource
{
    // Tetap menggunakan Model Umkm karena datanya menyatu di sana
    protected static ?string $model = Umkm::class;

    protected static ?string $navigationLabel = 'UMKM Terbranding';
        protected static ?string $label = 'UMKM Terbranding';
    protected static ?string $pluralLabel = 'UMKM Terbranding';
      protected static ?string $navigationGroup = 'UMKM Data';
    
    protected static ?string $slug = 'umkm-terbranding';

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    // hak akses
    public static function canAccess(): bool 
    {
        // Atur siapa saja yang boleh melihat menu histori UMKM Terbranding ini
        // Contoh: admin, design, client, dan team_pasang bisa melihatnya
        return in_array(auth()->user()?->role, ['admin', 'client', 'team_pasang',]);
    }
    

    // hilangkan tombol "New/Create" karena ini menu histori/arsip, bukan untuk membuat data baru
    protected function getHeaderActions(): array
    {
        return [
            // Cukup kosongkan array di sini untuk menghilangkan tombol "New/Create" di pojok kanan atas
        ];
    }
    public static function canCreate(): bool
{
    // Mengembalikan nilai false agar tidak ada user pun yang bisa men-create data lewat resource ini
    return false;
}


    public static function form(Form $form): Form
    {
        // Karena ini menu histori/arsip, kita buat semua field-nya disabled (Read-Only) biar tidak bisa diedit lewat sini
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi UMKM')
                    ->schema([
                        Forms\Components\TextInput::make('nama_usaha')->disabled()->label('UMKM Name'),
                        Forms\Components\TextInput::make('nama_pemilik')->disabled()->label('Pemilik'),
                    ])->columns(2),

                Forms\Components\Section::make('Dokumentasi Pemasangan Stiker (Proses pemotretan Wajib dalam Keadaan Cahaya yang Terang Dan Jelas)')
                    ->schema([
                        Forms\Components\FileUpload::make('stiker_tampak_depan')->disabled()->label('Tampak Depan'),
                        Forms\Components\FileUpload::make('stiker_tampak_kanan')->disabled()->label('Tampak Kanan'),
                        Forms\Components\FileUpload::make('stiker_tampak_kiri')->disabled()->label('Tampak Kiri'),
                        Forms\Components\FileUpload::make('foto_wide')->disabled()->label('Foto Wide (Keseluruhan)'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            // ====================================================================
            // LOGIKA UTAMA: Hanya select UMKM yang KE-4 KOLOM STIKERNYA SUDAH TERISI
            // ====================================================================
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $query->whereIn('status', ['branded', 'terbranding_final'])
                      ->whereNotNull('stiker_tampak_depan')
                      ->whereNotNull('stiker_tampak_kanan')
                      ->whereNotNull('stiker_tampak_kiri')
                      ->whereNotNull('foto_wide')
                      ->latest();

                // Filter berdasarkan kota akun team_pasang
                if ($user && $user->role === 'team_pasang' && $user->kota_id) {
                    $query->where('kota_id', $user->kota_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('nama_usaha')
                    ->label('UMKM Name')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status UMKM')->badge()
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state))
                    ->colors([
                        'warning' => 'pending',
                        'primary' => 'design_pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('kota.nama')
                    ->label('Kota')
                    ->searchable(),

                Tables\Columns\TextColumn::make('nama_pemilik')
                    ->label('Pemilik'),

                // Menampilkan status kelengkapan secara visual bahwa ini sudah sukses dibranding
                Tables\Columns\TextColumn::make('status_pasang')
                    ->label('Status Branding')
                    ->badge()
                    ->getStateUsing(fn () => 'SELESAI BRANDING')
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Tanggal Selesai')
                    ->dateTime('d M Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kota_id')
                    ->label('Kota')
                    ->options(Kota::pluck('nama', 'id')),
            ])
             ->actions([
        Tables\Actions\ViewAction::make()
    ->label('Lihat Detail')
    ->slideOver() // optional biar full kanan
    ->infolist([


    // Tampilkan alasan reject jika statusnya rejected
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

        // DATA PEMILIK
        \Filament\Infolists\Components\Section::make('Data Pemilik')
            ->schema([
                \Filament\Infolists\Components\TextEntry::make('nama_pemilik')
                    ->label('Nama Pemilik'),

                \Filament\Infolists\Components\TextEntry::make('nama_usaha')
                    ->label('Nama Usaha'),

                \Filament\Infolists\Components\TextEntry::make('alamat_usaha')
                    ->label('Alamat'),

                \Filament\Infolists\Components\TextEntry::make('no_wa')
                    ->label('No WhatsApp'),

                \Filament\Infolists\Components\TextEntry::make('radius')
                    ->label('Radius Alfamart')
                    ->suffix(' Meter'),

                \Filament\Infolists\Components\TextEntry::make('kota.nama')
                    ->label('Kota'),

                \Filament\Infolists\Components\TextEntry::make('jam_buka')
                    ->label('Jam Buka')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-')
                    ->placeholder('-'),

                \Filament\Infolists\Components\TextEntry::make('jam_tutup')
                    ->label('Jam Tutup')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-')
                    ->placeholder('-'),
            ])
            ->columns(2),

        // REKENING
        \Filament\Infolists\Components\Section::make('Data Rekening')
            ->schema([
                \Filament\Infolists\Components\TextEntry::make('no_rekening')
                    ->label('No Rekening'),

                \Filament\Infolists\Components\TextEntry::make('nama_bank')
                    ->label('Bank'),

                \Filament\Infolists\Components\TextEntry::make('atas_nama_rekening')
                    ->label('Atas Nama'),
            ])
            ->columns(3),

        // LOKASI
        \Filament\Infolists\Components\Section::make('Lokasi')
            ->schema([

                \Filament\Infolists\Components\TextEntry::make('latitude')
                    ->label('Latitude'),

                \Filament\Infolists\Components\TextEntry::make('longitude')
                    ->label('Longitude'),

                \Filament\Infolists\Components\TextEntry::make('sharelock_url')
                    ->label('Google Maps')
                    ->url(fn ($record) => $record->sharelock_url)
                    ->openUrlInNewTab(),
            ])
            ->columns(2),

       // UKURAN PANEL
\Filament\Infolists\Components\Section::make('Ukuran Panel')
    ->schema([
        // Gunakan ViewEntry kustom untuk merender tabel HTML murni
        \Filament\Infolists\Components\ViewEntry::make('ukuran_panel_table')
            ->view('filament.infolists.components.tabel-panel')
            ->columnSpanFull(),
    ]),
// FOTO
\Filament\Infolists\Components\Section::make('Foto')
    ->schema([
// PANGGIL LIGHTBOX MODAL DI SINI (Agar selalu aktif & tidak bergantung pada ada/tidaknya video)
        \Filament\Infolists\Components\ViewEntry::make('image_lightbox')
            ->view('filament.infolists.components.image-lightbox')
            ->columnSpanFull(), // Makan space full tapi tidak terlihat karena modalnya 'display: none'

        \Filament\Infolists\Components\ImageEntry::make('foto_depan')
            ->label('Foto Depan')
            ->height(200)
            ->extraAttributes(fn ($record) => [
                'class' => 'cursor-pointer hover:scale-105 transition duration-300',
                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_depan) . '" })',
            ]),

        \Filament\Infolists\Components\ImageEntry::make('foto_kanan')
            ->label('Foto Kanan')
            ->height(200)
            ->extraAttributes(fn ($record) => [
                'class' => 'cursor-pointer hover:scale-105 transition duration-300',
                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_kanan) . '" })',
            ]),

        \Filament\Infolists\Components\ImageEntry::make('foto_kiri')
            ->label('Foto Kiri')
            ->height(200)
            ->extraAttributes(fn ($record) => [
                'class' => 'cursor-pointer hover:scale-105 transition duration-300',
                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_kiri) . '" })',
            ]),

        \Filament\Infolists\Components\ImageEntry::make('foto_plang_alfamart')
            ->label('Foto Plang Alfamart')
            ->height(200)
            ->extraAttributes(fn ($record) => [
                'class' => 'cursor-pointer hover:scale-105 transition duration-300',
                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_plang_alfamart) . '" })',
            ]),

        // popup Entry menggunakan ViewEntry murni tanpa modal
        \Filament\Infolists\Components\ViewEntry::make('video_validasi')
            ->label('Video Validasi')
            ->view('filament.infolists.components.video-player')
            ->visible(fn ($record) => !empty($record->video_validasi)),
    ])
    ->columns(2),

      // acc design gerobak
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
    ->columns(2)
    ->collapsible() // section bisa diciutkan
    ->visible(fn ($record) =>
        !empty($record->design_final) ||
        !empty($record->design_gerobak_depan) ||
        !empty($record->design_gerobak_kiri) ||
        !empty($record->design_gerobak_kanan)
    ), // section hanya muncul kalau ada minimal 1 gambar


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
                ])->columns(2),

            // LOG PERSONALIA
            \Filament\Infolists\Components\Section::make('Log Personalia')
                ->description('Rekam jejak personel yang terlibat dalam proses branding.')
                ->icon('heroicon-o-users')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('submittedBy.name')
                        ->label('Nama PIC Lapangan'),
                    \Filament\Infolists\Components\TextEntry::make('umkmDesign.nama_desainer')
                        ->label('Nama Desainer')
                        ->default('-'),
                    \Filament\Infolists\Components\TextEntry::make('nama_team_pasang')
                        ->label('Nama Team Pasang')
                        ->default('-'),
                    \Filament\Infolists\Components\TextEntry::make('tanggal_pasang')
                        ->label('Tanggal Pemasangan')
                        ->date('d M Y')
                        ->default('-'),
                ])->columns(2),
    ])
    ->modalWidth('7xl'),
            ])
->headerActions([
    Action::make('exportExcel')
        ->label('Export Excel')
        ->icon('heroicon-o-document-arrow-down')
        ->color('success')
        ->action(function ($livewire) {

            $query = $livewire->getFilteredTableQuery()->with(['kota', 'submittedBy', 'umkmDesign', 'approvedBy']);

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\UmkmExport($query->get()),
                'umkm_terbranding_' . now()->format('Ymd_His') . '.xlsx'
            );
        }),

    // Action::make('exportPdf')
    //     ->label('Export PDF')
    //     ->icon('heroicon-o-document-text')
    //     ->color('danger')
    //     ->action(function ($livewire) {

    //         $query = $livewire->getFilteredTableQuery();

    //         $records = $query->get();

    //         $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
    //             'exports.umkm_terbranding',
    //             compact('records')
    //         );

    //         return response()->streamDownload(
    //             fn () => print($pdf->output()),
    //             'umkm_terbranding_' . now()->format('Ymd_His') . '.pdf'
    //         );
    //     }),
])
            
             // Hilangkan tombol header actions (Create/Edit) karena ini hanya untuk view/arsip
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUmkmTerbrandings::route('/'),
        ];
    }
}
