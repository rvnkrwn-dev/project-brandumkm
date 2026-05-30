<?php

namespace App\Filament\Resources\UmkmResource\Widgets;

use App\Models\Umkm;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UmkmTerbrandingTableWidget extends BaseWidget
{
    protected static ?string $heading = 'UMKM Terbranding';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 5;
    
       public static function canView(): bool
{
    // Mengambil user yang sedang login
    $user = auth()->user();

    // Memastikan user ada dan role-nya adalah 'admin' atau 'client'
    return $user && in_array($user->role, ['admin', 'client', 'team_pasang']);
}

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->query(
                Umkm::query()
                    ->whereIn('status', ['branded', 'terbranding_final'])
                    ->whereNotNull('stiker_tampak_depan')
                    ->whereNotNull('stiker_tampak_kanan')
                    ->whereNotNull('stiker_tampak_kiri')
                    ->whereNotNull('foto_wide')
                    ->when($user && $user->role === 'team_pasang' && $user->kota_id, function ($q) use ($user) {
                        $q->where('kota_id', $user->kota_id);
                    })
                    ->latest()
            )

            ->columns([
                Tables\Columns\TextColumn::make('nama_usaha')
                    ->label('Nama UMKM')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nama_pemilik')
                    ->label('Pemilik')
                    ->searchable(),

                Tables\Columns\TextColumn::make('kota.nama')
                    ->label('Kota')
                    ->badge()
                    ->color('success'),

                       // Menampilkan status kelengkapan secara visual bahwa ini sudah sukses dibranding
                Tables\Columns\TextColumn::make('status_pasang')
                    ->label('Status Branding')
                    ->badge()
                    ->getStateUsing(fn () => 'SELESAI BRANDING')
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_area_branding')
                    ->label('Area Branding')
                    ->suffix(' m2'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'branded', 'terbranding_final' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Tanggal Selesai')
                    ->dateTime('d M Y'),
            ])

            ->filters([

                Tables\Filters\SelectFilter::make('kota_id')
                    ->label('Kota')
                    ->relationship('kota', 'nama'),

            ])
      ->actions([
        Tables\Actions\ViewAction::make()
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

        // Video Entry menggunakan ViewEntry murni tanpa modal
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
    ])
    ->modalWidth('7xl')
    // Section hanya muncul jika salah satu data tersedia
    ->visible(fn ($record) => !empty($record->status_pasang) || !empty($record->updated_at)),
            ])

            ->paginated([5, 10, 25])

            ->defaultPaginationPageOption(5);
    }
}