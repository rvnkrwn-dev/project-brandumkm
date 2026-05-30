<?php

namespace App\Filament\Resources\UmkmResource\Widgets;

use App\Filament\Resources\UmkmDesignResource;
use App\Filament\Resources\UmkmResource;
use App\Filament\Resources\UmkmTerbrandingResource;
use App\Models\Umkm;
use App\Models\UmkmDesign;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class SummaryStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';

    protected array|int|string $columns = [
        'default' => 2,
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
    ];

    protected function getStats(): array
    {
        $stats = [];
        $user = auth()->user();
        $userRole = $user?->role;

        $baseStyle = 'box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -4px rgba(0, 0, 0, 0.4); border-radius: 0.75rem; cursor: pointer; transition: transform 0.2s, filter 0.2s;';

        $extraHtmlStyles = [
            'class' => '[&_*]:text-white [&_*]:text-white/90 [&_p]:text-white [&_span]:text-white [&_svg]:!text-white'
        ];

        // Batch query UmkmDesign untuk menghindari N+1
        $designCounts = null;
        if (in_array($userRole, ['design', 'admin', 'client'])) {
            if ($userRole === 'design') {
                $designCounts = UmkmDesign::selectRaw("
                    SUM(CASE WHEN status = 'approved' AND designer_id = ? THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'revision_needed' AND designer_id = ? THEN 1 ELSE 0 END) as revision_needed,
                    SUM(CASE WHEN status = 'revised' AND designer_id = ? THEN 1 ELSE 0 END) as revised,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_all
                ", [$user->id, $user->id, $user->id])->first();
            } else {
                $designCounts = UmkmDesign::selectRaw("
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'revision_needed' THEN 1 ELSE 0 END) as revision_needed,
                    SUM(CASE WHEN status = 'revised' THEN 1 ELSE 0 END) as revised,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_all
                ")->first();
            }
        }

        if (in_array($userRole, ['team_pasang'])) {
            $queryPerluBranding = Umkm::where('status', Umkm::STATUS_WAITING_INSTALLATION)
                ->where(function ($q) {
                    $q->whereNull('stiker_tampak_depan')
                      ->orWhereNull('stiker_tampak_kanan')
                      ->orWhereNull('stiker_tampak_kiri')
                      ->orWhereNull('foto_wide');
                });

            // Filter berdasarkan kota akun team_pasang
            if ($user->kota_id) {
                $queryPerluBranding->where('kota_id', $user->kota_id);
            }

            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">UMKM Perlu di-Branding</span>'), $queryPerluBranding->count())
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Total antrean pasang stiker</span>'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url('/admin/pemasangan-stiker')
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #b45309;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));
        }

        if (in_array($userRole, ['team_pasang'])) {
            $queryBranded = Umkm::whereIn('status', [Umkm::STATUS_BRANDED, Umkm::STATUS_TERBRANDING_FINAL]);

            // Filter berdasarkan kota akun team_pasang
            if ($user->kota_id) {
                $queryBranded->where('kota_id', $user->kota_id);
            }

            $totalSudahBranding = $queryBranded->count();

            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Total UMKM Branded</span>'), $totalSudahBranding)
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Gerobak selesai pasang stiker</span>'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url(UmkmTerbrandingResource::getUrl('index'))
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #047857;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));
        }

        if (in_array($userRole, ['pic_lapangan'])) {
            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Create Data UMKM</span>'), 'Add New')
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Tambah data UMKM</span>'))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary')
                ->url(UmkmResource::getUrl('create'))
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #2563eb;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));
        }

        if (in_array($userRole, ['design'])) {
            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Create Design UMKM</span>'), 'Add New')
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Add UMKM design</span>'))
                ->descriptionIcon('heroicon-m-paint-brush')
                ->color('success')
                ->url(UmkmDesignResource::getUrl('create'))
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #16a34a;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));
        }

        if (in_array($userRole, ['admin', 'client'])) {
            $totalMasuk    = Umkm::count();
            $totalPending  = Umkm::where('status', 'pending')->count();
            $totalReject   = Umkm::where('status', 'rejected')->count();
            $totalApproved = Umkm::whereNotIn('status', ['pending', 'rejected'])->count();
            $totalDesignReview = UmkmDesign::whereIn('status', ['pending', 'revised'])->count();
            $totalRevisi   = UmkmDesign::where('status', 'revision_needed')->count();
            $totalDesigned = UmkmDesign::where('status', 'approved')->count();
            $totalFinal    = Umkm::whereIn('status', ['branded', 'terbranding_final'])->count();

            // 1. Review UMKM Candidates - UMKM yang diajukan PIC dan butuh approve/reject client
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Review UMKM Candidates</span>'), $totalPending)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Needs client approve/reject</span>'))
                ->descriptionIcon('heroicon-m-clock')->color('warning')
                ->url(UmkmResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'pending']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #ea580c;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 2. Total UMKM Approved - total UMKM yang sudah di-approve client
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Total UMKM Approved</span>'), $totalApproved)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Total approved by client</span>'))
                ->descriptionIcon('heroicon-m-check-circle')->color('success')
                ->url(UmkmResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'approved_all']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #16a34a;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 3. Review Design - desain yang butuh approve/revisi dari client
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Review Design</span>'), $totalDesignReview)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Needs client approve/revision</span>'))
                ->descriptionIcon('heroicon-m-eye')->color('warning')
                ->url(UmkmDesignResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'review']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #c2410c;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 4. Design Need Revision - total desain yang diminta revisi oleh client
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Design Need Revision</span>'), $totalRevisi)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Total designs revision requested</span>'))
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')->color('danger')
                ->url(UmkmDesignResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'revision_needed']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #ef4444;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 5. Total UMKM Designed & Revised - selesai didesain dan sudah ACC client
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Total UMKM Designed & Revised</span>'), $totalDesigned)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Total designed & client approved</span>'))
                ->descriptionIcon('heroicon-m-check-badge')->color('info')
                ->url(UmkmDesignResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'approved']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #0891b2;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 6. Total UMKM Candidates - semua pengajuan dari PIC (approve + reject)
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Total UMKM Candidates</span>'), $totalMasuk)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Total submissions from PIC</span>'))
                ->descriptionIcon('heroicon-m-building-storefront')->color('primary')
                ->url(UmkmResource::getUrl('index'))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #4f46e5;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 7. Total UMKM Rejected - kandidat yang ditolak client
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Total UMKM Rejected</span>'), $totalReject)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Total rejected by client</span>'))
                ->descriptionIcon('heroicon-m-x-circle')->color('danger')
                ->url(UmkmResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'rejected']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #be123c;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            // 8. Total UMKM Branded / Ter-branding - sudah ACC, didesain, dicetak, dan terpasang
            $stats[] = Stat::make(new HtmlString('<span style="color:#fff;font-weight:600;">Total UMKM Branded</span>'), $totalFinal)
                ->description(new HtmlString('<span style="color:#fff;opacity:.9;">Total branding completed</span>'))
                ->descriptionIcon('heroicon-m-trophy')->color('success')
                ->url(UmkmTerbrandingResource::getUrl('index'))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #047857;',
                    'onmouseover' => "this.style.transform='translateY(-4px)';", 'onmouseout' => "this.style.transform='translateY(0)';"]));

            return $stats;
        }

        if (in_array($userRole, ['design', 'admin'])) {
            if ($userRole === 'design') {
                $queryAntrean = Umkm::query()
                    ->whereIn('umkms.status', [Umkm::STATUS_APPROVED, Umkm::STATUS_MENUNGGU_DIDESAIN, Umkm::STATUS_REVISION_NEEDED])
                    ->leftJoin('umkm_designs', 'umkm_designs.umkm_id', '=', 'umkms.id')
                    ->where(function ($q) use ($user) {
                        $q->whereNull('umkm_designs.id')
                          ->orWhere(function ($sub) use ($user) {
                              $sub->where('umkm_designs.status', 'revision_needed')
                                  ->where('umkm_designs.designer_id', $user->id);
                          });
                    });

                $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">UMKM Needs Design</span>'), $queryAntrean->count())
                    ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Awaiting design queue</span>'))
                    ->descriptionIcon('heroicon-m-paint-brush')
                    ->color('warning')
                    ->url(UmkmResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'menunggu_didesain']]]))
                    ->extraAttributes(array_merge($extraHtmlStyles, [
                        'style' => $baseStyle . ' background-color: #d97706;',
                        'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                        'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                    ]));
            }

            $stats[] = Stat::make(
                new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Design Approved</span>'),
                $designCounts?->approved ?? 0
            )
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Design approved</span>'))
                ->descriptionIcon('heroicon-m-paint-brush')
                ->color('success')
                ->url(UmkmDesignResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'approved']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #059669;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));

            $stats[] = Stat::make(
                new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Design Needs Revision</span>'),
                $designCounts?->revision_needed ?? 0
            )
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Needs revision</span>'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url(UmkmDesignResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'revision_needed']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #ef4444;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));

            $stats[] = Stat::make(
                new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Design Revised</span>'),
                $designCounts?->revised ?? 0
            )
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Awaiting re-review</span>'))
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color('info')
                ->url(UmkmDesignResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'revised']]]))
                ->extraAttributes(array_merge($extraHtmlStyles, [
                    'style' => $baseStyle . ' background-color: #0891b2;',
                    'onmouseover' => "this.style.transform='translateY(-4px)'; this.style.filter='brightness(1.15)';",
                    'onmouseout' => "this.style.transform='translateY(0)'; this.style.filter='brightness(1)';"
                ]));
        }

        $query = Umkm::query();
        if ($userRole === 'pic_lapangan' && $user->kota_id) {
            $query->where('kota_id', $user->kota_id);
        }

        $getFilter = function ($status) use ($user, $userRole) {
            $filters = [];
            if (!empty($status)) {
                $filters['status'] = ['value' => $status];
            }
            if ($userRole === 'pic_lapangan') {
                $filters['pic_filter'] = ['value' => $user->name];
            }
            return UmkmResource::getUrl('index', ['tableFilters' => $filters]);
        };

        if ($userRole === 'pic_lapangan') {
            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">UMKM Pending</span>'), (clone $query)->where('status', 'pending')->count())
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Menunggu di review</span>'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url($getFilter('pending'))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #ea580c;']));
        }

        if (in_array($userRole, ['admin', 'pic_lapangan', 'client'])) {
            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Total UMKM Submitted</span>'), (clone $query)->count())
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">' . ($userRole === 'pic_lapangan' ? 'Data Kota Saya' : 'All cities') . '</span>'))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary')
                ->url($getFilter(''))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #4f46e5;']));

            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">UMKM Approved</span>'), (clone $query)->whereNotIn('status', ['pending', 'rejected'])->count())
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Approved by client</span>'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($getFilter('approved_all'))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #16a34a;']));

            if ($userRole !== 'pic_lapangan') {
                $designProcessCount = (clone $query)->whereIn('status', ['designing', 'design_review', 'revision_needed'])->count();
                $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Design Process</span>'), $designProcessCount)
                    ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Being designed/reviewed</span>'))
                    ->descriptionIcon('heroicon-m-paint-brush')
                    ->color('info')
                    ->url($getFilter('designing'))
                    ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #0891b2;']));

                $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">Ready for Installation</span>'), (clone $query)->where('status', 'design_approved')->count())
                    ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Design approved</span>'))
                    ->descriptionIcon('heroicon-m-scissors')
                    ->color('success')
                    ->url($getFilter('design_approved'))
                    ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #059669;']));
            }

            $stats[] = Stat::make(new HtmlString('<span style="color: #ffffff !important; font-weight: 600;">UMKM Rejected</span>'), (clone $query)->where('status', 'rejected')->count())
                ->description(new HtmlString('<span style="color: #ffffff !important; opacity: 0.9;">Tidak memenuhi syarat</span>'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->url($getFilter('rejected'))
                ->extraAttributes(array_merge($extraHtmlStyles, ['style' => $baseStyle . ' background-color: #be123c;']));
        }

        return $stats;
    }
}
