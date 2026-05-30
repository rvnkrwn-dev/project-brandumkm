@php
    $record = $getRecord();

    // Helper function untuk memproses data Width (_w) dan Height (_h) dari database
    $getDimensi = function($w, $h) {
        return [
            'l' => (!empty($w) && is_numeric($w)) ? (float)$w : 0,
            't' => (!empty($h) && is_numeric($h)) ? (float)$h : 0,
        ];
    };

    // Ambil data dari kolom _w dan _h sesuai struktur database Anda
    $depanAtas   = $getDimensi($record->depan_atas_w, $record->depan_atas_h);
    $depanTengah = $getDimensi($record->depan_tengah_w, $record->depan_tengah_h);
    $depanBawah  = $getDimensi($record->depan_bawah_w, $record->depan_bawah_h);

    $kananAtas   = $getDimensi($record->kanan_atas_w, $record->kanan_atas_h);
    $kananTengah = $getDimensi($record->kanan_tengah_w, $record->kanan_tengah_h);
    $kananBawah  = $getDimensi($record->kanan_bawah_w, $record->kanan_bawah_h);

    $kiriAtas    = $getDimensi($record->kiri_atas_w, $record->kiri_as_h ?? $record->kiri_atas_h); 
    $kiriTengah  = $getDimensi($record->kiri_tengah_w, $record->kiri_tengah_h);
    $kiriBawah   = $getDimensi($record->kiri_bawah_w, $record->kiri_bawah_h);
@endphp

<style>
    .custom-table-container {
        width: 100%;
        overflow-x: auto;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        border: 1px solid #2d3748;
        font-family: system-ui, -apple-system, sans-serif;
    }

    .custom-panel-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: #1a202c; /* Warna BG utama Gelap/Elegant */
        color: #cbd5e0;
        font-size: 0.875rem;
    }

    .custom-panel-table th {
        background-color: #2d3748; /* BG Header */
        color: #f7fafc;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 14px 16px;
        border-bottom: 2px solid #4a5568;
        border-right: 1px solid #4a5568;
    }

    .custom-panel-table th:last-child {
        border-right: none;
    }

    .custom-panel-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #2d3748;
        border-right: 1px solid #2d3748;
        transition: background-color 0.2s ease;
    }

    /* Hilangkan border kanan di kolom terakhir */
    .custom-panel-table td:last-child {
        border-right: none;
    }

    /* Warna khusus kolom utama (Brand Area) */
    .brand-area-cell {
        background-color: #222a3a;
        color: #fff;
        font-weight: bold;
        text-align: center;
        letter-spacing: 0.03em;
    }

    /* Efek Hover untuk baris data */
    .custom-panel-table tbody tr:hover td:not(.brand-area-cell) {
        background-color: #242f41;
        color: #fff;
    }

    /* Pengaturan teks kolom */
    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .font-bold {
        font-weight: 700;
    }

    /* Baris pembatas antar kelompok Front Side/Kanan/Kiri */
    .group-divider {
        border-top: 2px solid #4a5568 !important;
    }

    /* Desain Footer Total */
    .custom-panel-table tfoot tr {
        background-color: #2d3748;
    }

    .custom-panel-table tfoot td {
        border-top: 2px solid #4a5568;
        padding: 16px;
        font-size: 1rem;
    }

    .total-label {
        color: #a0aec0;
        font-weight: bold;
        letter-spacing: 0.05em;
    }

    .total-value {
        color: #38bdf8; /* Warna biru muda estetik untuk total area */
        font-weight: 800;
    }
</style>

<div class="custom-table-container">
    <table class="custom-panel-table">
        <thead>
            <tr>
                <th>BRAND. AREA</th>
                <th>PANEL</th>
                <th class="text-center">W (CM)</th>
                <th class="text-center">H (CM)</th>
                <th class="text-right">M2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="brand-area-cell" rowspan="3">Front Side</td>
                <td>Top</td>
                <td class="text-center">{{ $depanAtas['l'] }}</td>
                <td class="text-center">{{ $depanAtas['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->depan_panel_atas_m2 ?? 0, 2) }}</td> </tr>
            <tr>
                <td>Middle</td>
                <td class="text-center">{{ $depanTengah['l'] }}</td>
                <td class="text-center">{{ $depanTengah['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->depan_panel_tengah_m2 ?? 0, 2) }}</td> </tr>
            <tr>
                <td>Bottom</td>
                <td class="text-center">{{ $depanBawah['l'] }}</td>
                <td class="text-center">{{ $depanBawah['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->depan_panel_bawah_m2 ?? 0, 2) }}</td> </tr>

            <tr class="group-divider">
                <td class="brand-area-cell" rowspan="3">Right Side</td>
                <td>Top</td>
                <td class="text-center">{{ $kananAtas['l'] }}</td>
                <td class="text-center">{{ $kananAtas['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->kanan_panel_atas_m2 ?? 0, 2) }}</td> </tr>
            <tr>
                <td>Middle</td>
                <td class="text-center">{{ $kananTengah['l'] }}</td>
                <td class="text-center">{{ $kananTengah['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->kanan_panel_tengah_m2 ?? 0, 2) }}</td> </tr>
            <tr>
                <td>Bottom</td>
                <td class="text-center">{{ $kananBawah['l'] }}</td>
                <td class="text-center">{{ $kananBawah['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->kanan_panel_bawah_m2 ?? 0, 2) }}</td> </tr>

            <tr class="group-divider">
                <td class="brand-area-cell" rowspan="3">Left Side</td>
                <td>Top</td>
                <td class="text-center">{{ $kiriAtas['l'] }}</td>
                <td class="text-center">{{ $kiriAtas['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->kiri_panel_atas_m2 ?? 0, 2) }}</td> </tr>
            <tr>
                <td>Middle</td>
                <td class="text-center">{{ $kiriTengah['l'] }}</td>
                <td class="text-center">{{ $kiriTengah['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->kiri_panel_tengah_m2 ?? 0, 2) }}</td> </tr>
            <tr>
                <td>Bottom</td>
                <td class="text-center">{{ $kiriBawah['l'] }}</td>
                <td class="text-center">{{ $kiriBawah['t'] }}</td>
                <td class="text-right font-bold" style="color: #fff;">{{ number_format($record->kiri_panel_bawah_m2 ?? 0, 2) }}</td> </tr>
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right total-label" colspan="4">TOTAL (M2) :</td>
                <td class="text-right total-value">{{ number_format($record->total_area_branding ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>