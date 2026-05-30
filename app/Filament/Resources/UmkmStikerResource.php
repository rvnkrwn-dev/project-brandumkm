<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UmkmStikerResource\Pages;
use App\Models\Kota;
use App\Models\Umkm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UmkmStikerResource extends Resource
{
    protected static ?string $model = Umkm::class;
    protected static ?string $navigationLabel = 'Sticker Installation';
    protected static ?string $slug = 'pemasangan-stiker';
    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['team_pasang']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Form Instalasi')
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_pasang')
                            ->label('Tanggal Pemasangan')
                            ->default(now()->toDateString())
                            ->required(),

                        Forms\Components\Select::make('kota_id')
                            ->label('Kota')
                            ->options(Kota::orderBy('nama')->pluck('nama', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(),

                        Forms\Components\TextInput::make('nama_usaha')
                            ->label('Nama UMKM')
                            ->disabled(),

                        Forms\Components\TextInput::make('nama_team_pasang')
                            ->label('Nama Tim Pasang')
                            ->default(fn () => auth()->user()?->name)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Upload Dokumentasi Pemasangan Stiker')
                    ->description('⚠️ Proses pemotretan wajib dalam keadaan cahaya yang terang, jelas, dan bagus.')
                    ->schema([
                        Forms\Components\FileUpload::make('stiker_tampak_depan')
                            ->label('Tampak Depan Terbranding')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->maxSize(5120)
                            ->directory(fn ($record) => 'umkm/' . ($record?->kota_id ?: 'temp') . '/stiker')
                            ->required(),

                        Forms\Components\FileUpload::make('stiker_tampak_kanan')
                            ->label('Sisi Kanan Terbranding')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->maxSize(5120)
                            ->directory(fn ($record) => 'umkm/' . ($record?->kota_id ?: 'temp') . '/stiker')
                            ->required(),

                        Forms\Components\FileUpload::make('stiker_tampak_kiri')
                            ->label('Sisi Kiri Terbranding')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->maxSize(5120)
                            ->directory(fn ($record) => 'umkm/' . ($record?->kota_id ?: 'temp') . '/stiker')
                            ->required(),

                        Forms\Components\FileUpload::make('foto_wide')
                            ->label('Foto Wide / Kejauhan')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->maxSize(5120)
                            ->directory(fn ($record) => 'umkm/' . ($record?->kota_id ?: 'temp') . '/stiker')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $query->where('status', Umkm::STATUS_WAITING_INSTALLATION)
                    ->where(function ($q) {
                        $q->whereNull('stiker_tampak_depan')
                          ->orWhereNull('stiker_tampak_kanan')
                          ->orWhereNull('stiker_tampak_kiri')
                          ->orWhereNull('foto_wide');
                    })
                    ->latest();

                // Filter berdasarkan kota akun team_pasang
                if ($user && $user->role === 'team_pasang') {
                    if ($user->kota_id) {
                        $query->where('kota_id', $user->kota_id);
                    } else {
                        $query->whereRaw('0 = 1'); // no kota assigned = no records
                    }
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('nama_usaha')
                    ->label('Nama UMKM')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('kota.nama')
                    ->label('Kota')
                    ->searchable(),

                Tables\Columns\TextColumn::make('nama_pemilik')
                    ->label('Pemilik'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn () => 'SIAP PASANG')
                    ->color('warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kota_id')
                    ->label('Kota')
                    ->options(Kota::pluck('nama', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDesign')
                    ->label('Lihat Design')
                    ->icon('heroicon-o-paint-brush')
                    ->color('info')
                    ->modalHeading('File Design untuk Printing')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->infolist(fn (Umkm $record) => [
                        \Filament\Infolists\Components\ImageEntry::make('design_final')
                            ->label('Screenshot File FA / Final Artwork')
                            ->height(250)
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\ImageEntry::make('design_gerobak_depan')
                            ->label('Mockup Gerobak Depan')
                            ->height(200),
                        \Filament\Infolists\Components\ImageEntry::make('design_gerobak_kiri')
                            ->label('Mockup Gerobak Kiri')
                            ->height(200),
                        \Filament\Infolists\Components\ImageEntry::make('design_gerobak_kanan')
                            ->label('Mockup Gerobak Kanan')
                            ->height(200),
                        \Filament\Infolists\Components\Actions::make([
                            \Filament\Infolists\Components\Actions\Action::make('download_fa')
                                ->label('Download FA')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('success')
                                ->url(fn (Umkm $record) => $record->design_final ? asset('storage/' . $record->design_final) : null)
                                ->openUrlInNewTab()
                                ->visible(fn (Umkm $record) => !empty($record->design_final)),
                            \Filament\Infolists\Components\Actions\Action::make('download_depan')
                                ->label('Download Depan')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->url(fn (Umkm $record) => $record->design_gerobak_depan ? asset('storage/' . $record->design_gerobak_depan) : null)
                                ->openUrlInNewTab()
                                ->visible(fn (Umkm $record) => !empty($record->design_gerobak_depan)),
                            \Filament\Infolists\Components\Actions\Action::make('download_kiri')
                                ->label('Download Kiri')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->url(fn (Umkm $record) => $record->design_gerobak_kiri ? asset('storage/' . $record->design_gerobak_kiri) : null)
                                ->openUrlInNewTab()
                                ->visible(fn (Umkm $record) => !empty($record->design_gerobak_kiri)),
                            \Filament\Infolists\Components\Actions\Action::make('download_kanan')
                                ->label('Download Kanan')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->url(fn (Umkm $record) => $record->design_gerobak_kanan ? asset('storage/' . $record->design_gerobak_kanan) : null)
                                ->openUrlInNewTab()
                                ->visible(fn (Umkm $record) => !empty($record->design_gerobak_kanan)),
                        ])->columnSpanFull(),
                    ])
                    ->modalWidth('4xl')
                    ->visible(fn (Umkm $record) => !empty($record->design_final)),

                Tables\Actions\EditAction::make()
                    ->label('Upload Dokumentasi')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->after(function (Umkm $record) {
                        if ($record->stiker_tampak_depan && $record->stiker_tampak_kanan
                            && $record->stiker_tampak_kiri && $record->foto_wide) {
                            $record->update(['status' => Umkm::STATUS_TERBRANDING_FINAL]);
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUmkmStikers::route('/'),
            'edit' => Pages\EditUmkmStiker::route('/{record}/edit'),
        ];
    }
}
