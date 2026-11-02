<?php

namespace App\Console\Commands;

use App\Domain\Ekim\EkimNotificationMessage;
use App\Services\ProcessRunService;
use App\Services\SystemMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendEkimNotifications extends Command
{
    protected $signature = 'prima:notifications {--dry-run : Periksa kandidat tanpa mengirim email atau mengubah database}';

    protected $description = 'Kirim notifikasi email EKIM yang mendekati atau melewati masa berlaku';

    public function handle(SystemMailer $mailer, EkimNotificationMessage $notificationMessage, ProcessRunService $runs): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $runId = $runs->start('ekim_notifications', ['dry_run' => $dryRun]);
        $settings = DB::table('system_settings')->pluck('setting_value', 'setting_key');
        $days = max(0, min(365, (int) ($settings['notif_days_threshold'] ?? 30)));
        $until = today()->addDays($days)->toDateString();
        DB::table('formulir_checklist')
            ->where('status_approval', 'approved')
            ->whereNotNull('ekim_valid_until')
            ->whereDate('ekim_valid_until', '<', today())
            ->update(['status_approval' => 'expired', 'updated_at' => now()]);
        $rows = DB::table('formulir_checklist as f')
            ->join('kendaraan as k', 'k.nomor_polisi', '=', 'f.nomor_polisi')
            ->whereIn('f.status_approval', ['approved', 'expired'])
            ->whereNotNull('k.email_transportir')
            ->whereRaw("TRIM(k.email_transportir) <> ''")
            ->whereNotNull('f.ekim_valid_until')
            ->where('f.ekim_valid_until', '<=', $until)
            ->whereRaw("f.id=(SELECT MAX(f2.id) FROM formulir_checklist f2 WHERE f2.nomor_polisi=f.nomor_polisi AND f2.status_approval IN ('approved','expired'))")
            ->select('f.*', 'k.email_transportir')
            ->get();

        if ($dryRun) {
            $runs->finish($runId, 'Dry-run notifikasi selesai', ['candidates' => $rows->count()]);
            $this->info('Mode uji: '.$rows->count().' kandidat ditemukan; tidak ada email atau data yang diubah.');

            return self::SUCCESS;
        }

        if (! $mailer->isReady($settings)) {
            $message = 'SMTP belum lengkap. Isi host, port, dan email pengirim; jika memakai akun SMTP, isi juga passwordnya.';
            $this->recordRun($message);
            $runs->fail($runId, $message);
            $this->error($message);

            return self::FAILURE;
        }

        try {
            $mailer->configure($settings);
        } catch (\Throwable $e) {
            $message = 'Konfigurasi SMTP tidak dapat dibaca: '.$e->getMessage();
            $this->recordRun($message);
            $runs->fail($runId, $message);
            $this->error($message);

            return self::FAILURE;
        }

        $sent = 0;
        $failed = 0;
        foreach ($rows as $x) {
            $email = strtolower(trim((string) $x->email_transportir));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log($x, (int) today()->diffInDays($x->ekim_valid_until, false), 'failed', 'Format email transportir tidak valid.');
                $failed++;

                continue;
            }
            $remaining = (int) today()->diffInDays($x->ekim_valid_until, false);
            $category = $remaining < 0 ? 'expired' : 'threshold-'.$days;
            $notificationKey = hash('sha256', implode('|', [$x->nomor_polisi, $email, $x->ekim_valid_until, $category]));
            if (DB::table('kim_notifications')->where('notification_key', $notificationKey)->where('status', 'sent')->exists()) {
                continue;
            }
            $status = 'sent';
            $error = null;
            try {
                $content = $notificationMessage->build($x->nomor_polisi, $x->ekim_valid_until, $remaining);
                Mail::raw($content['body'], fn ($m) => $m->to($email)->subject($content['subject']));
                $sent++;
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
                $failed++;
            }
            $x->email_transportir = $email;
            $x->notification_key = $notificationKey;
            $this->log($x, $remaining, $status, $error);
        }
        $message = "Scheduler selesai: {$rows->count()} kandidat, $sent terkirim, $failed gagal.";
        $this->recordRun($message);
        if ($failed > 0) {
            $runs->fail($runId, $message, 'Pengiriman notifikasi selesai dengan kegagalan');
        } else {
            $runs->finish($runId, $message, ['candidates' => $rows->count(), 'sent' => $sent]);
        }
        $this->info($message);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function recordRun(string $status): void
    {
        DB::table('system_settings')->upsert([
            ['setting_key' => 'cron_last_run', 'setting_value' => now()->toDateTimeString(), 'updated_at' => now()],
            ['setting_key' => 'cron_last_status', 'setting_value' => $status, 'updated_at' => now()],
        ], ['setting_key'], ['setting_value', 'updated_at']);
    }

    private function log(object $x, int $remaining, string $status, ?string $error): void
    {
        DB::table('kim_notifications')->insert(['notification_key' => $x->notification_key ?? null, 'nomor_polisi' => $x->nomor_polisi, 'nama_transport' => $x->nama_transport, 'email_to' => $x->email_transportir, 'ekim_valid_until' => $x->ekim_valid_until, 'hari_tersisa' => $remaining, 'status' => $status, 'error_message' => $error, 'sent_at' => now()]);
    }
}
