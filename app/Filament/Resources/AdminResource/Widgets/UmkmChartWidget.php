<?php

namespace App\Filament\Resources\AdminResource\Widgets;

use App\Models\Kota;
use App\Models\Umkm;
use Filament\Widgets\ChartWidget;

class UmkmChartWidget extends ChartWidget
{
    protected static ?string $heading = 'UMKM Progress by City';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '400px';

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && in_array($user->role, ['admin', 'client']);
    }

    protected function getData(): array
    {
        $kotas = Kota::whereHas('umkms')->orderBy('nama')->get();

        $labels = [];
        $pending = [];
        $approved = [];
        $proses_design = [];
        $siap_pasang = [];
        $terbranding = [];
        $rejected = [];

        foreach ($kotas as $kota) {
            $labels[] = $kota->nama;

            $counts = Umkm::where('kota_id', $kota->id)
                ->selectRaw("
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status IN ('approved', 'menunggu_didesain') THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status IN ('designing', 'design_review', 'revision_needed', 'revision') THEN 1 ELSE 0 END) as proses_design,
                    SUM(CASE WHEN status IN ('design_approved', 'waiting_installation') THEN 1 ELSE 0 END) as siap_pasang,
                    SUM(CASE WHEN status IN ('branded', 'terbranding_final', 'installation_completed') THEN 1 ELSE 0 END) as terbranding,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                ")->first();

            $pending[] = (int) $counts->pending;
            $approved[] = (int) $counts->approved;
            $proses_design[] = (int) $counts->proses_design;
            $siap_pasang[] = (int) $counts->siap_pasang;
            $terbranding[] = (int) $counts->terbranding;
            $rejected[] = (int) $counts->rejected;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pending',
                    'data' => $pending,
                    'backgroundColor' => '#F59E0B',
                ],
                [
                    'label' => 'Approved',
                    'data' => $approved,
                    'backgroundColor' => '#3B82F6',
                ],
                [
                    'label' => 'Design Process',
                    'data' => $proses_design,
                    'backgroundColor' => '#8B5CF6',
                ],
                [
                    'label' => 'Ready to Install',
                    'data' => $siap_pasang,
                    'backgroundColor' => '#06B6D4',
                ],
                [
                    'label' => 'Branded',
                    'data' => $terbranding,
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'Rejected',
                    'data' => $rejected,
                    'backgroundColor' => '#EF4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
