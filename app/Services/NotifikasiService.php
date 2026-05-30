<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Umkm;
use App\Models\UmkmDesign;

class NotifikasiService
{
    private static function notifyAdmin(string $judul, string $pesan, string $tipe, string $notifiableType, int $notifiableId): void
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notifikasi::create([
                'user_id' => $admin->id,
                'judul' => '[LOG] ' . $judul,
                'pesan' => $pesan,
                'tipe' => $tipe,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
            ]);
        }
    }

    public static function notifyNewUmkm(Umkm $umkm): void
    {
        $pesan = "New UMKM: {$umkm->nama_usaha} from {$umkm->kota->nama} needs review.";

        $clients = User::where('role', 'client')->get();
        foreach ($clients as $client) {
            Notifikasi::create([
                'user_id' => $client->id,
                'judul' => 'New UMKM Submission',
                'pesan' => $pesan,
                'tipe' => 'umkm_baru',
                'notifiable_type' => Umkm::class,
                'notifiable_id' => $umkm->id,
            ]);
        }

        self::notifyAdmin('New UMKM Submission', $pesan, 'umkm_baru', Umkm::class, $umkm->id);
    }

    public static function notifyUmkmApproved(Umkm $umkm): void
    {
        $designers = User::where('role', 'design')->get();
        foreach ($designers as $designer) {
            Notifikasi::create([
                'user_id' => $designer->id,
                'judul' => 'UMKM Needs Design',
                'pesan' => "UMKM: {$umkm->nama_usaha} has been approved and needs a design.",
                'tipe' => 'perlu_design',
                'notifiable_type' => Umkm::class,
                'notifiable_id' => $umkm->id,
            ]);
        }

        Notifikasi::create([
            'user_id' => $umkm->submitted_by,
            'judul' => 'UMKM Disetujui ✅',
            'pesan' => "UMKM {$umkm->nama_usaha} telah disetujui oleh client.",
            'tipe' => 'umkm_approved',
            'notifiable_type' => Umkm::class,
            'notifiable_id' => $umkm->id,
        ]);

        self::notifyAdmin('UMKM Approved', "UMKM {$umkm->nama_usaha} approved by client.", 'umkm_approved', Umkm::class, $umkm->id);
    }

    public static function notifyUmkmRejected(Umkm $umkm): void
    {
        Notifikasi::create([
            'user_id' => $umkm->submitted_by,
            'judul' => 'UMKM Ditolak ❌',
            'pesan' => "UMKM {$umkm->nama_usaha} ditolak. Alasan: {$umkm->alasan_reject}",
            'tipe' => 'umkm_rejected',
            'notifiable_type' => Umkm::class,
            'notifiable_id' => $umkm->id,
        ]);

        self::notifyAdmin('UMKM Rejected', "UMKM {$umkm->nama_usaha} rejected. Reason: {$umkm->alasan_reject}", 'umkm_rejected', Umkm::class, $umkm->id);
    }

    public static function notifyNewDesign(UmkmDesign $design): void
    {
        $pesan = "New design for {$design->umkm->nama_usaha} needs review.";

        $clients = User::where('role', 'client')->get();
        foreach ($clients as $client) {
            Notifikasi::create([
                'user_id' => $client->id,
                'judul' => 'New Design Uploaded',
                'pesan' => $pesan,
                'tipe' => 'design_baru',
                'notifiable_type' => UmkmDesign::class,
                'notifiable_id' => $design->id,
            ]);
        }

        self::notifyAdmin('New Design Uploaded', $pesan, 'design_baru', UmkmDesign::class, $design->id);
    }

    public static function notifyDesignRevision(UmkmDesign $design): void
    {
        Notifikasi::create([
            'user_id' => $design->designer_id,
            'judul' => 'Design Needs Revision',
            'pesan' => "Design for {$design->umkm->nama_usaha} needs revision. Notes: {$design->catatan_revisi}",
            'tipe' => 'perlu_revisi',
            'notifiable_type' => UmkmDesign::class,
            'notifiable_id' => $design->id,
        ]);

        self::notifyAdmin('Design Needs Revision', "Design for {$design->umkm->nama_usaha} revision requested.", 'perlu_revisi', UmkmDesign::class, $design->id);
    }

    public static function notifyDesignRevised(UmkmDesign $design): void
    {
        $pesan = "Design for {$design->umkm->nama_usaha} has been revised. Please review again.";

        $clients = User::where('role', 'client')->get();
        foreach ($clients as $client) {
            Notifikasi::create([
                'user_id' => $client->id,
                'judul' => 'Design Revised 🎨',
                'pesan' => $pesan,
                'tipe' => 'revised',
                'notifiable_type' => UmkmDesign::class,
                'notifiable_id' => $design->id,
            ]);
        }

        self::notifyAdmin('Design Revised', $pesan, 'revised', UmkmDesign::class, $design->id);
    }

    public static function notifyDesignApproved(UmkmDesign $design): void
    {
        Notifikasi::create([
            'user_id' => $design->designer_id,
            'judul' => 'Design Approved ✅',
            'pesan' => "Design for {$design->umkm->nama_usaha} has been approved by client.",
            'tipe' => 'design_approved',
            'notifiable_type' => UmkmDesign::class,
            'notifiable_id' => $design->id,
        ]);

        self::notifyAdmin('Design Approved', "Design for {$design->umkm->nama_usaha} approved by client.", 'design_approved', UmkmDesign::class, $design->id);
    }

    public static function notifyTeamPasangDesignApproved(UmkmDesign $design): void
    {
        $pesan = "UMKM {$design->umkm->nama_usaha} ({$design->umkm->kota->nama}) siap untuk pemasangan stiker.";

        $users = User::where('role', 'team_pasang')->get();
        foreach ($users as $user) {
            Notifikasi::create([
                'user_id' => $user->id,
                'judul' => 'UMKM Ready for Installation 🎯',
                'pesan' => $pesan,
                'tipe' => 'siap_pasang',
                'notifiable_type' => Umkm::class,
                'notifiable_id' => $design->umkm->id,
            ]);
        }

        self::notifyAdmin('UMKM Ready for Installation', "UMKM {$design->umkm->nama_usaha} ready for sticker installation.", 'siap_pasang', Umkm::class, $design->umkm->id);
    }
}
