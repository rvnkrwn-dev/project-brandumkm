<?php

namespace App\Filament\Resources;

use App\Exports\UmkmExport;
use App\Filament\Resources\UmkmResource\Pages;
use App\Models\Kota;
use App\Models\Umkm;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class UmkmResource extends Resource
{
    protected static ?string $model = Umkm::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'UMKM Data';
    protected static ?string $slug = 'umkm';
    protected static ?string $label = 'UMKM';
    protected static ?string $pluralLabel = 'UMKM Data';

    // akses role design, client, admin
    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['design', 'pic_lapangan', 'client', 'admin']);
    }

    // select data pic
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
{
    $query = parent::getEloquentQuery();

    $user = auth()->user();

    // Jika role pic_lapangan, tampilkan hanya data sesuai kota akunnya
    if ($user && $user->role === 'pic_lapangan' && $user->kota_id) {
        $query->where('kota_id', $user->kota_id);
    }

    return $query;
}

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['client', 'admin', 'pic_lapangan'])) return null;

        $query = Umkm::where('status', 'pending');
        if ($user->role === 'pic_lapangan' && $user->kota_id) {
            $query->where('kota_id', $user->kota_id);
        }
        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['client', 'admin'])) return null;

        $count = Umkm::where('status', 'pending')->count();
        return $count > 5 ? 'danger' : 'warning';
    }
    

//  tombol create disni yang bisa akses hanya oleh pic_lapangan
  public static function canCreate(): bool
{
    $user = auth()->user();
    return $user && in_array($user->role, ['pic_lapangan']);
}
public static function canEdit($record): bool
{
    $user = auth()->user();
    if (!$user || $user->role !== 'pic_lapangan') return false;
    return $record->status === 'pending';
}

// tombol delete  hanya untuk pic_lapangan dan admin
public static function canDelete($record): bool
{
    $user = auth()->user();
    if (!$user) return false;
    if ($user->role === 'admin') return true;
    if ($user->role === 'pic_lapangan') {
        return in_array($record->status, [Umkm::STATUS_PENDING, Umkm::STATUS_REJECTED]);
    }
    return false;
}

    // Helper function untuk menghitung m2 dari W x H (cm)
    private static function calculateM2(?float $width, ?float $height): float
    {
        if (!$width || !$height) return 0;
        return round(($width * $height) / 10000, 2); // cm² to m²
    }

    // Helper function untuk menghitung total panel
    private static function calculatePanelTotal(Get $get, string $prefix): float
    {
        $atas = self::calculateM2(
            floatval($get("{$prefix}_atas_w")),
            floatval($get("{$prefix}_atas_h"))
        );
        $tengah = self::calculateM2(
            floatval($get("{$prefix}_tengah_w")),
            floatval($get("{$prefix}_tengah_h"))
        );
        $bawah = self::calculateM2(
            floatval($get("{$prefix}_bawah_w")),
            floatval($get("{$prefix}_bawah_h"))
        );
        
        return round($atas + $tengah + $bawah, 2);
    }

   public static function form(Form $form): Form
{
    return $form
        ->schema([

            Forms\Components\Wizard::make([

                // STEP 1
                Forms\Components\Wizard\Step::make('Data Pemilik')
                    ->icon('heroicon-o-user')
                   ->schema([
            Forms\Components\Hidden::make('submitted_by')
                ->default(auth()->id()),

            Forms\Components\TextInput::make('nama_pemilik')
                ->label('Nama Pemilik')
                ->placeholder('Nama Sesuai KTP')
                ->required(),

                        Forms\Components\TextInput::make('nama_usaha')
                            ->label('Nama Usaha')
                            ->required(),

                        Forms\Components\Textarea::make('alamat_usaha')
                            ->label('Alamat Usaha')
                            ->required()
                            ->rows(3),

                        Forms\Components\TextInput::make('no_wa')
                            ->label('No. WhatsApp')
                            ->tel()
                             ->unique(table: Umkm::class, column: 'no_wa', ignoreRecord: true)
                            ->required()
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('radius')
                            ->label('Radius dari Alfamart')
                            ->placeholder('Contoh: 50')
                            ->suffix('Meter')
                            ->numeric(),

                        Forms\Components\TextInput::make('request_text')
                            ->label('Teks Branding yang Diminta')
                            ->placeholder('Contoh: Aneka Gorengan UMY')
                            ->columnSpanFull(),

                        Forms\Components\TimePicker::make('jam_buka')
                            ->label('Jam Buka')
                            ->seconds(false),

                        Forms\Components\TimePicker::make('jam_tutup')
                            ->label('Jam Tutup')
                            ->seconds(false),

                        Forms\Components\Select::make('kota_id')
    ->label('Kota')
    ->options(Kota::pluck('nama', 'id'))
    ->searchable()
    ->preload()
    ->required()

    ->createOptionAction(function ($action) {
        return $action->label('Tambah Kota');
    })

    ->createOptionForm([
        Forms\Components\TextInput::make('nama')
            ->label('Nama Kota')
            ->required(),
    ])

    ->createOptionUsing(function (array $data) {
        return Kota::create($data)->id;
    }),
                    ])
                    ->columns(2),

                // STEP 2
                Forms\Components\Wizard\Step::make('Data Rekening')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Forms\Components\TextInput::make('no_rekening')
                            ->label('No. Rekening')
                            ->unique(table: Umkm::class, column: 'no_rekening', ignoreRecord: true)
                            ->required()
                            ->live(onBlur: true),

                        Forms\Components\Select::make('nama_bank') // Sesuaikan dengan nama kolom di database Anda
    ->label('Pilih Bank')
    ->placeholder('Pilih bank penyedia')
    ->options([
        'BCA' => 'BCA',
        'Mandiri' => 'Mandiri',
        'BNI' => 'BNI',
        'BRI' => 'BRI',
        'Danamon' => 'Danamon',
        'Permata' => 'Permata',
        'CIMB Niaga' => 'CIMB Niaga',
        'Maybank' => 'Maybank',
        'Panin Bank' => 'Panin Bank',
        'Bank Syariah Indonesia (BSI)' => 'Bank Syariah Indonesia (BSI)',
        'Bank Jago' => 'Bank Jago',
        'bank jatim' => 'bank jatim',
        'bank jateng' => 'bank jateng',
        'Bank Mega' => 'Bank Mega',
        'Bank Bukopin' => 'Bank Bukopin',
        'Bank OCBC NISP' => 'Bank OCBC NISP',
        'Bank BTN' => 'Bank BTN',
        'Bank BTPN' => 'Bank BTPN',
        'Bank DKI' => 'Bank DKI',
        'Bank Sinarmas' => 'Bank Sinarmas',
        'Bank Jabar' => 'Bank Jabar',
        'Bank BRI Syariah' => 'Bank BRI Syariah',
        'Bank Danamon Syariah' => 'Bank Danamon Syariah',
        'Bank Permata Syariah' => 'Bank Permata Syariah',
        'Bank Panin Syariah' => 'Bank Panin Syariah',
        'Bank OCBC NISP Syariah' => 'Bank OCBC NISP Syariah',
        'Bank Blue Bca' => 'Bank Blue Bca',
        'Bank Bukopin Syariah' => 'Bank Bukopin Syariah',
    ])
    ->searchable() // Biar tim design/user bisa mengetik nama bank (tidak capek scroll)
    ->preload()    // Memuat semua data di awal agar pencarian terasa instan dan cepat
    ->required(),  // Opsional, jika wajib diisi

                        Forms\Components\TextInput::make('atas_nama_rekening')
                            ->label('Atas Nama')
                            ->required()
                            ->placeholder('Sesuaikan dengan nama di rekening bank'),
                    ])
                    ->columns(2),

                // STEP 3
                Forms\Components\Wizard\Step::make('Lokasi')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        // GPS — ViewField dengan Alpine inline
                        \Filament\Forms\Components\ViewField::make('get_location')
                            ->label('Lokasi GPS')
                            ->view('filament.forms.components.get-location-button')
                            ->key('gps-v3')
                            ->columnSpanFull(),

        Forms\Components\Grid::make(2)
            ->schema([
                Forms\Components\TextInput::make('latitude')
                    ->label('Latitude')
                    ->required()
                    ->numeric()
                    ->step(0.00000001)
                    ->readOnly()
                    ->placeholder('Wajib dari GPS — nyalakan lokasi')
                    ->helperText('Koordinat otomatis dari GPS. Tidak bisa diisi manual.')
                    ->live(),
                    
                Forms\Components\TextInput::make('longitude')
                    ->label('Longitude')
                    ->required()
                    ->numeric()
                    ->step(0.00000001)
                    ->readOnly()
                    ->placeholder('Wajib dari GPS — nyalakan lokasi')
                    ->helperText('Koordinat otomatis dari GPS. Tidak bisa diisi manual.')
                    ->live(),
            ]),

        Forms\Components\TextInput::make('sharelock_url')
            ->label('Google Maps URL')
            ->required()
            ->url()
            ->readOnly()
            ->placeholder('Otomatis terisi dari GPS...')
            ->columnSpanFull()
            ->suffixAction(
                Forms\Components\Actions\Action::make('openMap')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Get $get) => $get('sharelock_url'))
                    ->openUrlInNewTab()
                    ->visible(fn (Get $get) => !empty($get('sharelock_url')))
            ),

        // Preview Map
        Forms\Components\Placeholder::make('map_preview')
            ->label('Preview Lokasi')
            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                $lat = $get('latitude');
                $lng = $get('longitude');
                
                if ($lat && $lng) {
                    return new \Illuminate\Support\HtmlString(
                        "<iframe 
                            width='100%' 
                            height='250' 
                            style='border:0; border-radius: 8px;' 
                            loading='lazy' 
                            allowfullscreen 
                            src='https://maps.google.com/maps?q={$lat},{$lng}&z=17&output=embed'>
                        </iframe>"
                    );
                }
                
                return new \Illuminate\Support\HtmlString(
                    '<div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6 text-center text-gray-500">
                        <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <p class="text-sm">Menunggu lokasi GPS...</p>
                    </div>'
                );
            })
            ->columnSpanFull(),
                    ]),

                // STEP 4
                Forms\Components\Wizard\Step::make('Ukuran Panel')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                     // Info minimum area
        Forms\Components\Placeholder::make('info_minimum')
            ->label('')
            // tambahin keterangan jika data ukuran tidak ada cukup isi 0
         ->content(new \Illuminate\Support\HtmlString('
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <span style="color: #facc15 !important; font-weight: 600; font-size: 0.85rem;">
            ⚠️ Jika tidak ada ukuran panel, isi dengan 0 (nol)
        </span>
        <span style="color: #facc15 !important; font-weight: 600; font-size: 0.85rem;">
            📐 Minimum total area branding: 1.5 M²
        </span>
    </div>
')),

        // ========== SISI DEPAN ==========
        Forms\Components\Section::make('Sisi Depan')
            ->description(function (Get $get): string {
                $atas = self::calculateM2(floatval($get('depan_atas_w')), floatval($get('depan_atas_h')));
                $tengah = self::calculateM2(floatval($get('depan_tengah_w')), floatval($get('depan_tengah_h')));
                $bawah = self::calculateM2(floatval($get('depan_bawah_w')), floatval($get('depan_bawah_h')));
                return round($atas + $tengah + $bawah, 2) . ' M²';
            })
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('depan_atas_label')->label('')->content('Panel Atas'),
                        Forms\Components\TextInput::make('depan_atas_w')->required()->label('P (cm)')->placeholder('contoh: 163')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('depan_atas_h')->label('T (cm)')->required()->placeholder('contoh: 20')->numeric()->step(1)->live(onBlur: true),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('depan_tengah_label')->label('')->content('Panel Tengah'),
                        Forms\Components\TextInput::make('depan_tengah_w')->label('P (cm)')->placeholder('contoh: 100')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('depan_tengah_h')->label('T (cm)')->placeholder('contoh: 40')->numeric()->step(1)->live(onBlur: true),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('depan_bawah_label')->label('')->content('Panel Bawah'),
                        Forms\Components\TextInput::make('depan_bawah_w')->label('P (cm)')->required()->placeholder('contoh: 163')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('depan_bawah_h')->label('T (cm)')->required()->numeric()->step(1)->placeholder('contoh: 62')->live(onBlur: true),
                    ]),
            ])
            ->collapsible()
            ->extraAttributes(['class' => 'border-l-4 border-l-primary-500']),

        // ========== SISI KANAN ==========
        Forms\Components\Section::make('Sisi Kanan')
            ->description(function (Get $get): string {
                $atas = self::calculateM2(floatval($get('kanan_atas_w')), floatval($get('kanan_atas_h')));
                $tengah = self::calculateM2(floatval($get('kanan_tengah_w')), floatval($get('kanan_tengah_h')));
                $bawah = self::calculateM2(floatval($get('kanan_bawah_w')), floatval($get('kanan_bawah_h')));
                return round($atas + $tengah + $bawah, 2) . ' M²';
            })
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('kanan_atas_label')->label('')->content('Panel Atas'),
                        Forms\Components\TextInput::make('kanan_atas_w')->label('P (cm)')->required()->placeholder('contoh: 54')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('kanan_atas_h')->label('T (cm)')->numeric()->step(1)->placeholder('contoh: 20')->required()->live(onBlur: true),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('kanan_tengah_label')->label('')->content('Panel Tengah'),
                        Forms\Components\TextInput::make('kanan_tengah_w')->label('P (cm)')->placeholder('contoh: 50')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('kanan_tengah_h')->label('T (cm)')->placeholder('contoh: 40')->numeric()->step(1)->live(onBlur: true),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('kanan_bawah_label')->label('')->content('Panel Bawah'),
                        Forms\Components\TextInput::make('kanan_bawah_w')->label('P (cm)')->numeric()->step(1)->placeholder('contoh: 49')->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('kanan_bawah_h')->label('T (cm)')->numeric()->step(1)->placeholder('contoh: 58')->required()->live(onBlur: true),
                    ]),
            ])
            ->collapsible()
            ->extraAttributes(['class' => 'border-l-4 border-l-success-500']),

        // ========== SISI KIRI ==========
        Forms\Components\Section::make('Sisi Kiri')
            ->description(function (Get $get): string {
                $atas = self::calculateM2(floatval($get('kiri_atas_w')), floatval($get('kiri_atas_h')));
                $tengah = self::calculateM2(floatval($get('kiri_tengah_w')), floatval($get('kiri_tengah_h')));
                $bawah = self::calculateM2(floatval($get('kiri_bawah_w')), floatval($get('kiri_bawah_h')));
                return round($atas + $tengah + $bawah, 2) . ' M²';
            })
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('kiri_atas_label')->label('')->content('Panel Atas'),
                        Forms\Components\TextInput::make('kiri_atas_w')->label('P (cm)')->placeholder('contoh: 54')->numeric()->step(1)->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('kiri_atas_h')->label('T (cm)')->placeholder('contoh: 20')->numeric()->step(1)->required()->live(onBlur: true),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('kiri_tengah_label')->label('')->content('Panel Tengah'),
                        Forms\Components\TextInput::make('kiri_tengah_w')->label('P (cm)')->placeholder('contoh: 50')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('kiri_tengah_h')->label('T (cm)')->placeholder('contoh: 40')->numeric()->step(1)->live(onBlur: true),
                    ]),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('kiri_bawah_label')->label('')->content('Panel Bawah'),
                        Forms\Components\TextInput::make('kiri_bawah_w')->label('P (cm)')->required()->placeholder('contoh: 54')->numeric()->step(1)->live(onBlur: true),
                        Forms\Components\TextInput::make('kiri_bawah_h')->label('T (cm)')->numeric()->step(1)->placeholder('contoh: 62')->required()->live(onBlur: true),
                    ]),
            ])
            ->collapsible()
            ->extraAttributes(['class' => 'border-l-4 border-l-warning-500']),
              // Total Area Branding
        Forms\Components\Placeholder::make('total_area_display')
            ->label('Total Area Branding')
            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                $depan = self::calculateM2(floatval($get('depan_atas_w')), floatval($get('depan_atas_h')))
                       + self::calculateM2(floatval($get('depan_tengah_w')), floatval($get('depan_tengah_h')))
                       + self::calculateM2(floatval($get('depan_bawah_w')), floatval($get('depan_bawah_h')));
                $kanan = self::calculateM2(floatval($get('kanan_atas_w')), floatval($get('kanan_atas_h')))
                       + self::calculateM2(floatval($get('kanan_tengah_w')), floatval($get('kanan_tengah_h')))
                       + self::calculateM2(floatval($get('kanan_bawah_w')), floatval($get('kanan_bawah_h')));
                $kiri  = self::calculateM2(floatval($get('kiri_atas_w')), floatval($get('kiri_atas_h')))
                       + self::calculateM2(floatval($get('kiri_tengah_w')), floatval($get('kiri_tengah_h')))
                       + self::calculateM2(floatval($get('kiri_bawah_w')), floatval($get('kiri_bawah_h')));
                $total = round($depan + $kanan + $kiri, 2);
                
                if ($total >= 1.5) {
                    return new \Illuminate\Support\HtmlString(
                        "<span class='text-success-600 text-xl font-bold'>{$total} M² (Memenuhi Kriteria)</span>"
                    );
                } else {
                    return new \Illuminate\Support\HtmlString(
                        "<span class='text-danger-600 text-xl font-bold'>{$total} M² (Belum Memenuhi Kriteria)</span>"
                    );
                }
            })
            ->columnSpanFull(),
                    ])
                    ->afterValidation(function (Get $get) {
                        $depan = self::calculateM2(floatval($get('depan_atas_w')), floatval($get('depan_atas_h')))
                               + self::calculateM2(floatval($get('depan_tengah_w')), floatval($get('depan_tengah_h')))
                               + self::calculateM2(floatval($get('depan_bawah_w')), floatval($get('depan_bawah_h')));
                        $kanan = self::calculateM2(floatval($get('kanan_atas_w')), floatval($get('kanan_atas_h')))
                               + self::calculateM2(floatval($get('kanan_tengah_w')), floatval($get('kanan_tengah_h')))
                               + self::calculateM2(floatval($get('kanan_bawah_w')), floatval($get('kanan_bawah_h')));
                        $kiri  = self::calculateM2(floatval($get('kiri_atas_w')), floatval($get('kiri_atas_h')))
                               + self::calculateM2(floatval($get('kiri_tengah_w')), floatval($get('kiri_tengah_h')))
                               + self::calculateM2(floatval($get('kiri_bawah_w')), floatval($get('kiri_bawah_h')));
                        $total = round($depan + $kanan + $kiri, 2);

                        if ($total < 1.5) {
                            \Filament\Notifications\Notification::make()
                                ->title('Tidak Dapat Melanjutkan ❌')
                                ->body("Total area branding {$total} M² kurang dari minimum 1.5 M².")
                                ->danger()
                                ->persistent()
                                ->send();

                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'depan_atas_w' => "Total area branding {$total} M² kurang dari minimum 1.5 M².",
                            ]);
                        }
                    }),

                // STEP 5
                Forms\Components\Wizard\Step::make('Foto')
                    ->icon('heroicon-o-photo')
                    ->schema([
                         Forms\Components\Grid::make(2)
                ->schema([
                   Forms\Components\Section::make('Foto')
    ->schema([

        Forms\Components\Grid::make(2)
            ->schema([
Forms\Components\FileUpload::make('foto_depan')
    ->required()
    ->label('FOTO DEPAN')
    ->directory(fn (Forms\Get $get) => 'umkm/' . ($get('kota_id') ?: 'temp') . '/foto')
    ->image()
    ->imageResizeMode('cover')
    ->imageResizeTargetWidth('1200')
    ->imageResizeTargetHeight('1200')
    ->imageResizeUpscale(false)
    ->maxSize(5120)
    ->imagePreviewHeight('200')
    ->loadingIndicatorPosition('left')
    ->panelAspectRatio('2:1')
    ->panelLayout('integrated')
    ->removeUploadedFileButtonPosition('right')
    ->uploadProgressIndicatorPosition('left')
    ->openable()
    ->downloadable()
    ->previewable()
    ->extraInputAttributes(['capture' => 'environment']),

Forms\Components\FileUpload::make('foto_kanan')
    ->required()
    ->label('FOTO KANAN')
    ->directory(fn (Forms\Get $get) => 'umkm/' . ($get('kota_id') ?: 'temp') . '/foto')
    ->image()
    ->imageResizeMode('cover')
    ->imageResizeTargetWidth('1200')
    ->imageResizeTargetHeight('1200')
    ->imageResizeUpscale(false)
    ->maxSize(5120)
    ->imagePreviewHeight('200')
    ->loadingIndicatorPosition('left')
    ->panelAspectRatio('2:1')
    ->panelLayout('integrated')
    ->removeUploadedFileButtonPosition('right')
    ->uploadProgressIndicatorPosition('left')
    ->openable()
    ->downloadable()
    ->previewable()
    ->extraInputAttributes(['capture' => 'environment']),

Forms\Components\FileUpload::make('foto_kiri')
    ->required()
    ->label('FOTO KIRI')
    ->directory(fn (Forms\Get $get) => 'umkm/' . ($get('kota_id') ?: 'temp') . '/foto')
    ->image()
    ->imageResizeMode('cover')
    ->imageResizeTargetWidth('1200')
    ->imageResizeTargetHeight('1200')
    ->imageResizeUpscale(false)
    ->maxSize(5120)
    ->imagePreviewHeight('200')
    ->loadingIndicatorPosition('left')
    ->panelAspectRatio('2:1')
    ->panelLayout('integrated')
    ->removeUploadedFileButtonPosition('right')
    ->uploadProgressIndicatorPosition('left')
    ->openable()
    ->downloadable()
    ->previewable()
    ->extraInputAttributes(['capture' => 'environment']),

Forms\Components\FileUpload::make('foto_plang_alfamart')
    ->required()
    ->label('FOTO WIDE JARAK JAUH (PLANG ALFAMART)')
    ->directory(fn (Forms\Get $get) => 'umkm/' . ($get('kota_id') ?: 'temp') . '/foto')
    ->image()
    ->imageResizeMode('cover')
    ->imageResizeTargetWidth('1200')
    ->imageResizeTargetHeight('1200')
    ->imageResizeUpscale(false)
    ->maxSize(5120)
    ->imagePreviewHeight('200')
    ->loadingIndicatorPosition('left')
    ->panelAspectRatio('2:1')
    ->panelLayout('integrated')
    ->removeUploadedFileButtonPosition('right')
    ->uploadProgressIndicatorPosition('left')
    ->openable()
    ->downloadable()
    ->previewable()
    ->extraInputAttributes(['capture' => 'environment']),

Forms\Components\FileUpload::make('foto_tampak_jauh')
    ->required()
    ->label('FOTO TAMPAK JAUH (KESELURUHAN AREA)')
    ->directory(fn (Forms\Get $get) => 'umkm/' . ($get('kota_id') ?: 'temp') . '/foto')
    ->image()
    ->imageResizeMode('cover')
    ->imageResizeTargetWidth('1200')
    ->imageResizeTargetHeight('1200')
    ->imageResizeUpscale(false)
    ->maxSize(5120)
    ->imagePreviewHeight('200')
    ->loadingIndicatorPosition('left')
    ->panelAspectRatio('2:1')
    ->panelLayout('integrated')
    ->removeUploadedFileButtonPosition('right')
    ->uploadProgressIndicatorPosition('left')
    ->openable()
    ->downloadable()
    ->previewable()
    ->extraInputAttributes(['capture' => 'environment']),

            ])

    ])
                ]),

        // Section Video Validasi
        Forms\Components\Section::make('VIDEO VALIDASI JIKA ALFAMART TIDAK TERLIHAT ATAU TERHALANAG (OPSIONAL)')
            ->schema([
                Forms\Components\FileUpload::make('video_validasi')
                  ->label('UPLOAD VIDEO (MP4) max 2 menit / 50MB — Wajib jika Alfamart tidak terlihat di foto')
                     ->maxSize(51200) // 50MB
                    ->directory(fn (Forms\Get $get) => 'umkm/' . ($get('kota_id') ?: 'temp') . '/video')
                    ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/3gpp', 'video/3gpp2'])
                    ->helperText('Rekam dari lokasi gerobak sampai terlihat Alfamart. Max 2 menit. Format: MP4, MOV, AVI, 3GP.')
                    ->placeholder('Klik untuk rekam video')
                    ->extraInputAttributes(['capture' => 'environment']),
            ])
            ->collapsible(),

        // Section Catatan PIC Lapangan
        Forms\Components\Section::make('Catatan Tambahan')
            ->schema([
                Forms\Components\Textarea::make('catatan')
                    ->label('Catatan PIC Lapangan')
                    ->placeholder('Contoh: Lokasi sebelah Alfamart, area UMY')
                    ->rows(3),
            ])
            ->collapsible(),
                    ]),

            ])
            ->columnSpanFull()
            ->persistStepInQueryString()
            ->skippable(false)
            ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50" style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600);">Submit</button>')),

        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_usaha')
                    ->label('Nama Usaha')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_pemilik')
                    ->label('Pemilik')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kota.nama')
                    ->label('Kota')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_area_branding')
                    ->label('Total Area (m2)')
                    ->sortable(),
                Tables\Columns\IconColumn::make('memenuhi_kriteria')
                    ->label('Kriteria')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'pending' => 'warning',
                        in_array($state, ['approved', 'design_approved', 'branded', 'terbranding_final', 'installation_completed']) => 'success',
                        in_array($state, ['rejected', 'revision_needed']) => 'danger',
                        in_array($state, ['designing', 'design_review', 'menunggu_didesain', 'revision']) => 'info',
                        in_array($state, ['waiting_installation']) => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'menunggu_didesain' => 'Menunggu Di-design',
                        'designing' => 'Sedang Didesain',
                        'design_review' => 'Review Design',
                        'design_approved' => 'Design OK',
                        'waiting_installation' => 'Waiting Installation',
                        'revision_needed' => 'Perlu Revisi',
                        'revision' => 'Sedang Direvisi',
                        'installation_completed' => 'Installation Completed',
                        'branded' => 'Terbranding',
                        'terbranding_final' => 'UMKM Terbranding Final',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('submittedBy.name')
                    ->label('PIC'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Submit')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
             ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'approved_all' => 'Approved',
                        'pending' => 'Pending',
                        'rejected' => 'Rejected',
                        'menunggu_didesain' => 'Menunggu Di-design',
                        'designing' => 'Sedang Didesain',
                        'design_review' => 'Design Menunggu Review',
                        'design_approved' => 'Design Disetujui',
                        'waiting_installation' => 'Waiting Installation',
                        'revision_needed' => 'Perlu Revisi Design',
                        'revision' => 'Sedang Direvisi',
                        'installation_completed' => 'Installation Completed',
                        'branded' => 'Sudah Terbranding',
                        'terbranding_final' => 'UMKM Terbranding Final',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) return;
                        if ($data['value'] === 'approved_all') {
                            $query->whereNotIn('status', ['pending', 'rejected']);
                        } else {
                            $query->where('status', $data['value']);
                        }
                    }),
                Tables\Filters\SelectFilter::make('kota_id')
                    ->label('Kota')
                    ->options(Kota::pluck('nama', 'id')),
                Tables\Filters\TernaryFilter::make('memenuhi_kriteria')
                    ->label('Memenuhi Kriteria'),
                Filter::make('created_at')
    ->label('Tanggal Submit')
    ->form([
        DatePicker::make('created_from')->label('Dari Tanggal'),
        DatePicker::make('created_until')->label('Sampai Tanggal'),
    ])
    ->query(function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['created_from'],
                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
            )
            ->when(
                $data['created_until'],
                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
            );
    })
            ])
            ->actions([
        Tables\Actions\ViewAction::make()
    ->slideOver() // optional biar full kanan
    ->infolist([


    // Tampilkan alasan reject jika statusnya rejected
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

        // DATA PEMILIK
        \Filament\Infolists\Components\Section::make('Owner Data')
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
        \Filament\Infolists\Components\Section::make('Bank Account')
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
        \Filament\Infolists\Components\Section::make('Location')
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

        \Filament\Infolists\Components\ImageEntry::make('foto_tampak_jauh')
            ->label('Foto Tampak Jauh')
            ->height(200)
            ->extraAttributes(fn ($record) => [
                'class' => 'cursor-pointer hover:scale-105 transition duration-300',
                'x-on:click' => '$dispatch("open-preview-modal", { src: "' . asset('storage/' . $record->foto_tampak_jauh) . '" })',
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

    ])
    ->modalWidth('7xl')
    ->extraModalFooterActions(fn (Tables\Actions\ViewAction $action): array => [
        Tables\Actions\Action::make('approve_from_view')
            ->label('Approve UMKM')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Umkm $record) => $record->status === 'pending' && auth()->user()->isClient())
            ->action(function (Umkm $record) {
                $record->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);
                \Filament\Notifications\Notification::make()->title('UMKM Approved ✅')->success()->send();
            }),
        Tables\Actions\Action::make('reject_from_view')
            ->label('Reject UMKM')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Umkm $record) => $record->status === 'pending' && auth()->user()->isClient())
            ->form([
                Forms\Components\Textarea::make('alasan_reject')
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
    ]),
              Tables\Actions\EditAction::make()
    ->visible(fn () => in_array(auth()->user()?->role, ['pic_lapangan'])),
                
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Umkm $record) => 
                        $record->status === 'pending' && 
                        (auth()->user()->isClient())
                    )
                    ->action(function (Umkm $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);
                    }),
                
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('alasan_reject')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->visible(fn (Umkm $record) => 
                        $record->status === 'pending' && 
                        (auth()->user()->isClient())
                    )
                    ->action(function (Umkm $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'alasan_reject' => $data['alasan_reject'],
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                  Tables\Actions\DeleteBulkAction::make()
    ->visible(fn () => in_array(auth()->user()?->role, ['admin', 'pic_lapangan']))
    ->before(function ($records) {
        $user = auth()->user();
        if ($user?->role === 'pic_lapangan') {
            $records->each(function ($record) {
                if (!in_array($record->status, [Umkm::STATUS_PENDING, Umkm::STATUS_REJECTED])) {
                    throw new \Exception('Tidak bisa menghapus UMKM yang sudah diproses.');
                }
            });
        }
    }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->visible(fn () => in_array(auth()->user()?->role, ['admin', 'pic_lapangan'])),

                 Action::make('exportExcel')
        ->label('Export Excel')
        ->icon('heroicon-o-document-arrow-down')
        ->color('success')
        ->form([
            Forms\Components\Select::make('filter_type')
                ->label('Filter Export')
                ->options([
                    'all'      => 'Semua Data (Raw Data)',
                    'kota'     => 'Per Kota',
                    'pic'      => 'Per PIC Lapangan',
                    'designer' => 'Per Desainer',
                ])
                ->default('all')
                ->live()
                ->required(),

            Forms\Components\Select::make('kota_id')
                ->label('Pilih Kota')
                ->options(Kota::orderBy('nama')->pluck('nama', 'id'))
                ->searchable()
                ->preload()
                ->visible(fn ($get) => $get('filter_type') === 'kota')
                ->required(fn ($get) => $get('filter_type') === 'kota'),

            Forms\Components\Select::make('pic_id')
                ->label('Pilih PIC Lapangan')
                ->options(\App\Models\User::where('role', 'pic_lapangan')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->visible(fn ($get) => $get('filter_type') === 'pic')
                ->required(fn ($get) => $get('filter_type') === 'pic'),

            Forms\Components\Select::make('designer_id')
                ->label('Pilih Desainer')
                ->options(\App\Models\User::where('role', 'design')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->visible(fn ($get) => $get('filter_type') === 'designer')
                ->required(fn ($get) => $get('filter_type') === 'designer'),
        ])
        ->action(function (array $data) {
            $query = \App\Models\Umkm::with(['kota', 'submittedBy', 'umkmDesign', 'approvedBy']);

            match ($data['filter_type']) {
                'kota'     => $query->where('kota_id', $data['kota_id']),
                'pic'      => $query->where('submitted_by', $data['pic_id']),
                'designer' => $query->whereHas('designs', fn ($q) => $q->where('designer_id', $data['designer_id'])),
                default    => null,
            };

            $filename = match ($data['filter_type']) {
                'kota'     => 'export_kota_' . $data['kota_id'],
                'pic'      => 'export_pic_' . $data['pic_id'],
                'designer' => 'export_designer_' . $data['designer_id'],
                default    => 'export_all',
            };

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\UmkmExport($query->get()),
                $filename . '_' . now()->format('Ymd_His') . '.xlsx'
            );
        }),

                Action::make('exportPdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Filter Status')
                            ->options([
                                '' => 'Semua Status',
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ]),
                        Forms\Components\Select::make('kota_id')
                            ->label('Filter Kota')
                            ->options(['' => 'Semua Kota'] + Kota::pluck('nama', 'id')->toArray()),
                    ])
                    ->action(function (array $data) {
                        $query = Umkm::with(['kota', 'submittedBy', 'umkmDesign']);

                        if (!empty($data['status'])) {
                            $query->where('status', $data['status']);
                        }

                        if (!empty($data['kota_id'])) {
                            $query->where('kota_id', $data['kota_id']);
                        }

                        $umkms = $query->get();

                        $pdf = Pdf::loadView('exports.umkm-pdf', [
                            'umkms' => $umkms,
                            'title' => 'Data UMKM Branding Gerobak',
                            'date' => now()->format('d F Y'),
                        ]);

                        $pdf->setPaper('A4', 'landscape');

                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'data-umkm-' . now()->format('Y-m-d') . '.pdf'
                        );
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUmkms::route('/'),
            'create' => Pages\CreateUmkm::route('/create'),
            // 'view' => Pages\ViewUmkm::route('/{record}'),
            'edit' => Pages\EditUmkm::route('/{record}/edit'),
        ];
    }
}