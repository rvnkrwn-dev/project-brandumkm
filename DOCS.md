# Dokumentasi Sistem BrandUMKM

## Daftar Isi
1. [Overview Sistem](#overview-sistem)
2. [Role & Hak Akses](#role--hak-akses)
3. [Alur Kerja UMKM (Workflow)](#alur-kerja-umkm-workflow)
4. [Fitur Per Role](#fitur-per-role)
5. [Sistem Notifikasi](#sistem-notifikasi)
6. [Filter Data Per Kota](#filter-data-per-kota)
7. [Widget Dashboard](#widget-dashboard)
8. [Status UMKM](#status-umkm)
9. [Aturan Bisnis](#aturan-bisnis)
10. [Struktur Database](#struktur-database)

---

## Overview Sistem

BrandUMKM adalah sistem manajemen branding UMKM berbasis web yang mengelola seluruh proses dari pengajuan kandidat UMKM hingga pemasangan stiker branding. Dibangun dengan Laravel 11 + Filament 3.

**URL**: https://hanz.vanila.app/admin

---

## Role & Hak Akses

| Role | Deskripsi | Filter Kota |
|------|-----------|-------------|
| **Admin** | Superuser, akses semua fitur | Tidak (lihat semua) |
| **Client** | Pemilik project, approve/reject pengajuan | Tidak (lihat semua) |
| **Design** | Tim desainer, membuat design gerobak | Tidak (lihat semua UMKM, tapi design hanya milik sendiri) |
| **PIC Lapangan** | Petugas lapangan, submit data UMKM | **Ya** (hanya kota sendiri) |
| **Team Pasang** | Tim pemasangan stiker | **Ya** (hanya kota sendiri) |

---

## Alur Kerja UMKM (Workflow)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        ALUR LENGKAP                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [PIC Lapangan] Submit Data UMKM (wizard 5 langkah)                 │
│         │                                                            │
│         ▼                                                            │
│    ╔═══════════╗                                                     │
│    ║  PENDING  ║ ← Notifikasi → Client (umkm_baru)                 │
│    ╚═══════════╝                                                     │
│         │                                                            │
│    [Client] Review                                                   │
│         │                                                            │
│    ┌────┴────┐                                                       │
│    │         │                                                       │
│    ▼         ▼                                                       │
│ APPROVED   REJECTED                                                  │
│    │         │                                                       │
│    │         └→ Notifikasi → PIC (umkm_rejected)                    │
│    │            (Selesai, bisa di-edit & submit ulang)               │
│    │                                                                 │
│    ▼ (otomatis)                                                      │
│ MENUNGGU_DIDESAIN                                                    │
│    │  Notifikasi → Designer (perlu_design)                          │
│    │  Notifikasi → PIC (umkm_approved)                              │
│    │                                                                 │
│    ▼                                                                 │
│  [Designer] Buat Design                                              │
│    │                                                                 │
│    ▼                                                                 │
│ DESIGN_REVIEW                                                        │
│    │  Notifikasi → Client (design_baru)                             │
│    │                                                                 │
│    ┌────┴────┐                                                       │
│    │         │                                                       │
│    ▼         ▼                                                       │
│ DESIGN    REVISION_NEEDED                                            │
│ APPROVED     │  Notifikasi → Designer (perlu_revisi)                │
│    │         │                                                       │
│    │         ▼                                                       │
│    │    [Designer] Revisi                                            │
│    │         │                                                       │
│    │         ▼                                                       │
│    │    DESIGN_REVIEW (kembali ke review client)                     │
│    │         Notifikasi → Client (revised)                          │
│    │                                                                 │
│    ▼                                                                 │
│ WAITING_INSTALLATION                                                 │
│    │  Design file di-copy ke record UMKM                            │
│    │  Notifikasi → Designer (design_approved)                       │
│    │  Notifikasi → Team Pasang (siap_pasang)                        │
│    │                                                                 │
│    ▼                                                                 │
│  [Team Pasang] Upload 4 Foto Stiker                                 │
│    │                                                                 │
│    ▼                                                                 │
│ TERBRANDING_FINAL ✅                                                 │
│    (Muncul di halaman UMKM Terbranding)                             │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Fitur Per Role

### 🔴 Admin

| Fitur | Halaman | Deskripsi |
|-------|---------|-----------|
| Manajemen User | /admin/users | CRUD user, assign role & kota |
| Data UMKM | /admin/umkm | Lihat semua, delete semua status |
| History Design | /admin/umkm-designs | Lihat semua design |
| UMKM Terbranding | /admin/umkm-terbranding | Lihat hasil akhir |
| Backup | /admin/backup-page | Backup ZIP (file + data) |
| Notifikasi | /admin/notifikasis | Semua notifikasi (termasuk LOG) |
| Export | Header tabel | Excel & PDF dengan filter |

### 🟡 Client

| Fitur | Halaman | Deskripsi |
|-------|---------|-----------|
| Review Kandidat UMKM | /admin/umkm | Approve/Reject pengajuan baru |
| Review Design | /admin/umkm-designs | Approve design / Minta revisi |
| UMKM Terbranding | /admin/umkm-terbranding | Lihat hasil akhir |
| Dashboard Pengajuan | Widget PENGAJUAN TERBARU | Quick approve/reject dari dashboard |
| Notifikasi | Bell icon | umkm_baru, design_baru, revised |

**Tombol Aksi Client:**
- **Approve UMKM** — Muncul jika status = `pending`
- **Reject UMKM** — Muncul jika status = `pending` (wajib isi alasan)
- **Approve Design** — Muncul jika status = `design_review`
- **Minta Revisi** — Muncul jika status = `design_review` (wajib isi catatan)

### 🔵 Design (Tim Desainer)

| Fitur | Halaman | Deskripsi |
|-------|---------|-----------|
| Lihat Data UMKM | /admin/umkm | View only, lihat detail untuk referensi design |
| Buat Design | /admin/umkm-designs/create | Upload FA + 3 mockup gerobak |
| Edit/Revisi Design | /admin/umkm-designs/{id}/edit | Edit design milik sendiri |
| Antrean Design | Widget dashboard | Daftar UMKM yang perlu di-design |
| Notifikasi | Bell icon | perlu_design, perlu_revisi, design_approved |

**Widget "Daftar Antrean UMKM Perlu di-Design":**
- Menampilkan UMKM status `menunggu_didesain` / `approved` yang belum punya design
- Juga menampilkan UMKM yang design-nya perlu revisi (oleh designer yang sama)
- Tombol "Design Sekarang" → buka form create design
- Tombol "Revisi Sekarang" → buka form edit design
- Tombol "Lihat Detail" → slideOver dengan info lengkap UMKM

### 🟢 PIC Lapangan

| Fitur | Halaman | Deskripsi |
|-------|---------|-----------|
| Submit UMKM | /admin/umkm/create | Wizard 5 langkah |
| Edit UMKM | /admin/umkm/{id}/edit | Hanya jika status = `pending` |
| Delete UMKM | Tabel | Hanya status `pending` / `rejected` |
| Lihat Data | /admin/umkm | Hanya data kota sendiri |
| Notifikasi | Bell icon | umkm_approved, umkm_rejected |

**Form Submit UMKM (5 Langkah):**
1. **Data Pemilik** — Nama, usaha, alamat, WA, radius Alfamart, jam buka/tutup
2. **Data Rekening** — No rekening, bank, atas nama
3. **Lokasi GPS** — Auto-capture dari device, Google Maps URL
4. **Ukuran Panel** — 9 panel (3 sisi × 3 posisi), auto-hitung m²
5. **Foto** — 5 foto wajib + 1 video opsional

### 🟣 Team Pasang

| Fitur | Halaman | Deskripsi |
|-------|---------|-----------|
| Pemasangan Stiker | /admin/pemasangan-stiker | Upload 4 foto dokumentasi |
| Lihat Design | Tombol "Lihat Design" | Preview file design untuk printing |
| UMKM Terbranding | /admin/umkm-terbranding | Lihat hasil yang sudah selesai |
| Notifikasi | Bell icon | siap_pasang |

**Form Upload Stiker:**
- Tanggal pemasangan
- Nama team pasang (auto-fill dari nama user)
- 4 foto wajib: Tampak Depan, Sisi Kanan, Sisi Kiri, Foto Wide/Kejauhan
- Setelah save → redirect ke list (tidak loading terus)
- Jika 4 foto lengkap → status otomatis berubah ke `terbranding_final`

---

## Sistem Notifikasi

### Kapan Notifikasi Dikirim

| Event | Tipe | Penerima | Pesan |
|-------|------|----------|-------|
| UMKM baru di-submit | `umkm_baru` | Semua Client + Admin | "UMKM baru: {nama_usaha} dari {kota}" |
| UMKM di-approve | `umkm_approved` | PIC yang submit + Admin | "UMKM {nama_usaha} telah disetujui" |
| UMKM di-approve | `perlu_design` | Semua Designer + Admin | "UMKM {nama_usaha} perlu di-design" |
| UMKM di-reject | `umkm_rejected` | PIC yang submit + Admin | "UMKM {nama_usaha} ditolak: {alasan}" |
| Design baru di-submit | `design_baru` | Semua Client + Admin | "Design baru untuk {nama_usaha}" |
| Client minta revisi | `perlu_revisi` | Designer yang buat + Admin | "Design {nama_usaha} perlu revisi" |
| Designer submit revisi | `revised` | Semua Client + Admin | "Design {nama_usaha} sudah direvisi" |
| Design di-approve | `design_approved` | Designer yang buat + Admin | "Design {nama_usaha} disetujui" |
| Design di-approve | `siap_pasang` | Semua Team Pasang + Admin | "UMKM {nama_usaha} siap dipasang" |

### Aturan Notifikasi
- **Admin selalu menerima copy** semua notifikasi (dengan prefix [LOG])
- **Notifikasi bersifat broadcast per role** — semua user dengan role tertentu menerima
- **Pengecualian**: `umkm_approved` dan `umkm_rejected` hanya ke PIC yang submit (spesifik user)
- **Tidak ada filter per kota** pada notifikasi — semua designer/team_pasang menerima notifikasi global
- **Bell icon** menampilkan dot biru jika ada notifikasi belum dibaca
- **Auto-read**: Semua notifikasi ditandai dibaca saat halaman notifikasi dibuka

### Link Notifikasi
Klik notifikasi mengarah ke halaman yang relevan:
- `umkm_baru` → Halaman UMKM (filter pending)
- `perlu_design` → Dashboard (scroll ke widget antrean)
- `design_baru` → Halaman History Design
- `siap_pasang` → Halaman Pemasangan Stiker

---

## Filter Data Per Kota

### Bagaimana Filtering Bekerja

```
┌─────────────────────────────────────────────────┐
│              ADMIN / CLIENT                      │
│         Lihat SEMUA data, semua kota            │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│              DESIGN                              │
│  Lihat semua UMKM (untuk referensi)            │
│  Design: hanya milik sendiri (designer_id)      │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│           PIC LAPANGAN                           │
│  UMKM: hanya kota_id sendiri                   │
│  Badge: hitung hanya kota sendiri              │
│  Jika kota_id = null → lihat semua (bug?)      │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│           TEAM PASANG                            │
│  Pemasangan Stiker: hanya kota_id sendiri      │
│  UMKM Terbranding: hanya kota_id sendiri       │
│  Jika kota_id = null → lihat 0 record          │
└─────────────────────────────────────────────────┘
```

---

## Widget Dashboard

### Urutan Widget di Dashboard

| Sort | Widget | Visible By |
|------|--------|------------|
| 1 | SummaryStatsWidget (Card statistik) | Semua role |
| 5 | UmkmPerluDesignTableWidget (Antrean Design) | Design only |
| 5 | UmkmChartWidget (Grafik per Kota) | Admin, Client |
| 5 | SummaryPerKotaWidget (PENGAJUAN TERBARU) | Admin, Client |
| 5 | UmkmTerbrandingTableWidget (UMKM Terbranding) | Admin, Client, Team Pasang |

### Detail Card Statistik Per Role

**Admin/Client:**
- Review Kandidat (pending) — kuning
- Total Approved — hijau
- Review Design (design_review) — biru
- Design Need Revision — merah
- Total Designed (waiting_installation) — hijau
- Total Kandidat (semua) — biru
- Total Rejected — merah
- Total Terbranding — hijau

**Design:**
- Tombol "Buat Design Baru"
- UMKM Perlu di-Design (count)
- Design Approved (count milik sendiri)
- Design Perlu Revisi (count milik sendiri)
- Design Revised (count milik sendiri)

**PIC Lapangan:**
- Tombol "Buat Data UMKM"
- Pending (kota sendiri)
- Total Masuk (kota sendiri)
- Approved (kota sendiri)
- Rejected (kota sendiri)

**Team Pasang:**
- UMKM Perlu di-Branding (waiting_installation, kota sendiri)
- Total UMKM Branded (terbranding_final, kota sendiri)

---

## Status UMKM

| Status | Label UI | Deskripsi | Siapa yang trigger |
|--------|----------|-----------|-------------------|
| `pending` | MENUNGGU APPROVAL | Baru di-submit, menunggu client | PIC Lapangan (submit) |
| `approved` | APPROVED | Disetujui client (transient) | Client (approve) |
| `rejected` | REJECTED | Ditolak client | Client (reject) |
| `menunggu_didesain` | MENUNGGU DIDESAIN | Siap untuk di-design | Auto (setelah approved) |
| `designing` | DESIGNING | Sedang di-design | - (tidak dipakai aktif) |
| `design_review` | REVIEW DESIGN | Design di-submit, menunggu review | Designer (submit design) |
| `design_approved` | DESIGN APPROVED | Design disetujui | Client (approve design) |
| `waiting_installation` | SIAP PASANG | Menunggu pemasangan stiker | Auto (setelah design approved) |
| `revision_needed` | PERLU REVISI | Design perlu direvisi | Client (minta revisi) |
| `revision` | REVISION | Sedang direvisi | - (tidak dipakai aktif) |
| `installation_completed` | INSTALASI SELESAI | - (tidak dipakai aktif) | - |
| `branded` | BRANDED | Branding selesai | - (legacy, diganti terbranding_final) |
| `terbranding_final` | TERBRANDING FINAL | Semua foto stiker lengkap ✅ | Team Pasang (upload 4 foto) |

---

## Aturan Bisnis

1. **Minimum Area Branding**: Total luas panel ≥ 1.5 m² (auto-calculated dari ukuran panel)
2. **Foto Wajib Saat Submit**: foto_depan, foto_kanan, foto_kiri, foto_plang_alfamart (4 foto)
3. **Foto Wajib Saat Pasang**: stiker_tampak_depan, stiker_tampak_kanan, stiker_tampak_kiri, foto_wide
4. **Video Validasi**: Opsional, untuk bukti tambahan
5. **GPS Wajib**: Koordinat diambil dari device, tidak bisa diisi manual
6. **Unique Constraint**: No WA dan No Rekening harus unik per UMKM
7. **Auto-transition**: `approved` → `menunggu_didesain` terjadi otomatis via Observer
8. **Design Versioning**: Setiap revisi menambah field `versi` di UmkmDesign
9. **File Optimization**: Foto di-resize max 1200×1200px, video di-compress via FFmpeg
10. **Delete Protection**: PIC Lapangan hanya bisa hapus UMKM status `pending`/`rejected`

---

## Struktur Database

### Tabel Utama

```
kotas (id, nama)
  └── users (id, name, email, role, kota_id, is_active)
  └── umkms (id, kota_id, submitted_by, approved_by, ...)
        └── umkm_designs (id, umkm_id, designer_id, file_path, status, versi, ...)
        └── after_brandings (id, umkm_id, file_path, uploaded_by, ...)

notifikasis (id, user_id, judul, pesan, tipe, notifiable_type/id, is_read)
notifikasi_user (notifikasi_id, user_id, read_at) — pivot untuk broadcast
```

### Relasi Cascade
- `umkm_designs.umkm_id` → ON DELETE CASCADE (hapus UMKM = hapus semua design)
- `after_brandings.umkm_id` → ON DELETE CASCADE (hapus UMKM = hapus after branding)

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Framework | Laravel 11 |
| Admin Panel | Filament 3.3 |
| Frontend | Livewire 3, Alpine.js, Tailwind CSS |
| Database | MySQL 8.0 |
| File Storage | Local disk (public) via Nginx |
| PDF Export | barryvdh/laravel-dompdf |
| Excel Export | maatwebsite/excel |
| Video Processing | pbmedia/laravel-ffmpeg |
| Server | Nginx + PHP-FPM 8.3 (Ubuntu) |
| SSL | Let's Encrypt (Certbot) |
