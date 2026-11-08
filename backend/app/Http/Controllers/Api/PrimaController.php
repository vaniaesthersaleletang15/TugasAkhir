<?php

namespace App\Http\Controllers\Api;

use App\Domain\Checklist\ChecklistExpiryPolicy;
use App\Domain\Checklist\ChecklistFailureReasonBuilder;
use App\Domain\Checklist\ChecklistSignaturePolicy;
use App\Domain\Checklist\ChecklistWorkflowPolicy;
use App\Domain\Ekim\EkimSignatureService;
use App\Domain\Ekim\EkimVerificationEvaluator;
use App\Http\Controllers\Concerns\InteractsWithPrimaApi;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ChecklistComplianceService;
use App\Services\SystemMailer;
use App\Services\TurnstileVerifier;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PrimaController extends Controller
{
    use InteractsWithPrimaApi;

    public function __construct(private readonly EkimSignatureService $signatureService) {}

    private const ROLES = ['admin', 'pengurus', 'hsse', 'manager_hsse', 'regional_manager'];

    private const INTERNAL_ROLES = ['admin', 'hsse', 'manager_hsse', 'regional_manager'];

    private const STATES = ['draft', 'pending_hsse', 'signed_hsse', 'approved', 'rejected', 'expired'];

    public function login(Request $r): JsonResponse
    {
        $turnstile = app(TurnstileVerifier::class);
        $r->merge(['email' => strtolower(trim((string) $r->email))]);
        $v = Validator::make($r->all(), [
            'email' => 'required|email|max:100',
            'password' => 'required|string',
            'portal' => ['required', Rule::in(['internal', 'transportir'])],
            'captcha_token' => $turnstile->enabled() ? 'required|string|max:2048' : 'nullable|string|max:2048',
        ]);
        if ($v->fails()) {
            $message = $turnstile->enabled()
                ? 'Email, password, dan verifikasi CAPTCHA wajib diisi.'
                : 'Email dan password wajib diisi.';

            return $this->fail($message, 422, $v->errors());
        }
        if (! $turnstile->verify($r->string('captcha_token')->toString(), $r->ip())) {
            return $this->fail('Verifikasi CAPTCHA gagal atau kedaluwarsa. Silakan coba kembali.', 422);
        }
        $u = User::whereRaw('LOWER(email) = ?', [strtolower($r->string('email')->trim()->toString())])->first();
        if (! $u || ! Hash::check((string) $r->password, $u->password)) {
            if ($u) {
                $u->login_attempts++;
                if ($u->login_attempts >= 5) {
                    $u->locked_until = now()->addMinutes(15);
                } $u->save();
            }

            DB::table('audit_log')->insert([
                'terminal_id' => $u?->terminal_id,
                'action' => 'LOGIN_FAILED',
                'user_name' => $u?->username ?: strtolower($r->string('email')->toString()),
                'ip_address' => $r->ip(),
                'description' => 'Login gagal karena kredensial tidak valid',
                'created_at' => now(),
            ]);

            return $this->fail('Email atau password salah.', 401);
        }
        if ($u->locked_until?->isFuture()) {
            return $this->fail('Akun terkunci sementara.', 423);
        }
        if ($u->status !== 'active') {
            return $this->fail('Akun belum aktif.', 403);
        }
        $portal = $r->string('portal')->toString();
        $allowedRoles = $portal === 'internal' ? self::INTERNAL_ROLES : ['pengurus'];
        if (! in_array($u->role, $allowedRoles, true)) {
            DB::table('audit_log')->insert([
                'terminal_id' => $u->terminal_id,
                'action' => 'LOGIN_PORTAL_DENIED',
                'user_name' => $u->username,
                'ip_address' => $r->ip(),
                'description' => "Percobaan login akun {$u->role} melalui portal {$portal}",
                'created_at' => now(),
            ]);

            return $this->fail('Akun tidak dapat digunakan pada portal ini. Gunakan portal sesuai jenis akun.', 403);
        }
        if ($u->role !== 'admin' && $this->setting('maintenance_mode', '0') === '1') {
            return $this->fail($this->setting('maintenance_message', 'Sistem sedang dalam pemeliharaan.'), 503);
        }
        $u->forceFill(['login_attempts' => 0, 'locked_until' => null, 'last_login' => now()])->save();
        $minutes = max(15, min(480, (int) $this->setting('session_timeout_minutes', 60)));
        $token = $u->createToken("prima-{$portal}", ["portal:{$portal}"], now()->addMinutes($minutes))->plainTextToken;
        DB::table('audit_log')->insert(['terminal_id' => $u->terminal_id, 'action' => 'LOGIN', 'user_name' => $u->username, 'ip_address' => $r->ip(), 'description' => "Login berhasil melalui portal {$portal}", 'created_at' => now()]);

        return $this->ok('Login berhasil.', ['user' => $this->userData($u), 'token' => $token]);
    }

    public function captchaConfig(): JsonResponse
    {
        $enabled = app(TurnstileVerifier::class)->enabled();
        $siteKey = (string) config('services.turnstile.site_key');

        if ($enabled && $siteKey === '') {
            return $this->fail('Konfigurasi CAPTCHA belum lengkap.', 503);
        }

        return $this->ok('OK', [
            'enabled' => $enabled,
            'provider' => 'cloudflare-turnstile',
            'site_key' => $enabled ? $siteKey : null,
        ]);
    }

    public function publicTerminals(): JsonResponse
    {
        return $this->ok('OK', DB::table('integrated_terminals')
            ->where('region_code', 'SULAWESI')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city']));
    }

    public function publicTransporters(Request $r): JsonResponse
    {
        $validated = $r->validate([
            'terminal_id' => ['required', 'integer', Rule::exists('integrated_terminals', 'id')->where('region_code', 'SULAWESI')],
        ]);

        return $this->ok('OK', DB::table('transporters')
            ->where('terminal_id', $validated['terminal_id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']));
    }

    public function forgotPassword(Request $r, SystemMailer $mailer): JsonResponse
    {
        $r->merge(['email' => strtolower(trim((string) $r->email))]);
        $v = Validator::make($r->all(), ['email' => ['required', 'email', 'max:100']]);
        if ($v->fails()) {
            return $this->fail('Masukkan alamat email yang valid.', 422, $v->errors());
        }

        $genericMessage = 'Jika email terdaftar sebagai akun transportir, tautan reset password akan dikirim.';
        $user = User::whereRaw('LOWER(email) = ?', [$r->email])
            ->where('role', 'pengurus')
            ->where('status', 'active')
            ->first();
        if (! $user) {
            return $this->ok($genericMessage);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        try {
            if (! $mailer->configure()) {
                throw new \RuntimeException('Konfigurasi SMTP belum lengkap.');
            }
            $resetUrl = rtrim((string) config('app.frontend_url'), '/').'/#/transportir/reset-password?'.http_build_query([
                'email' => $user->email,
                'token' => $token,
            ]);
            Mail::raw(
                "Kami menerima permintaan reset password akun transportir PRIMA.\n\nBuka tautan berikut dalam 15 menit:\n{$resetUrl}\n\nTautan hanya dapat digunakan satu kali. Jika Anda tidak meminta reset password, abaikan email ini.",
                fn ($message) => $message->to($user->email)->subject('Reset Password Akun Transportir PRIMA')
            );
            DB::table('audit_log')->insert(['terminal_id' => $user->terminal_id, 'action' => 'PASSWORD_RESET_REQUEST', 'user_name' => $user->username, 'ip_address' => $r->ip(), 'description' => 'Tautan reset password transportir dikirim', 'created_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            Log::error('Pengiriman reset password PRIMA gagal.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return $this->ok($genericMessage);
    }

    public function resetPassword(Request $r): JsonResponse
    {
        $r->merge(['email' => strtolower(trim((string) $r->email))]);
        $v = Validator::make($r->all(), [
            'email' => ['required', 'email', 'max:100'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.mixed' => 'Password harus mengandung huruf besar dan huruf kecil.',
            'password.numbers' => 'Password harus mengandung minimal satu angka.',
            'password.symbols' => 'Password harus mengandung minimal satu simbol.',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }

        $user = User::whereRaw('LOWER(email) = ?', [$r->email])
            ->where('role', 'pengurus')
            ->where('status', 'active')
            ->first();
        $reset = DB::table('password_reset_tokens')->where('email', $r->email)->first();
        $valid = $user
            && $reset
            && $reset->created_at
            && Carbon::parse($reset->created_at)->gte(now()->subMinutes(15))
            && Hash::check((string) $r->token, $reset->token);
        if (! $valid) {
            return $this->fail('Tautan reset password tidak valid atau sudah kedaluwarsa.', 422);
        }

        DB::transaction(function () use ($user, $r): void {
            $user->forceFill([
                'password' => $r->password,
                'login_attempts' => 0,
                'locked_until' => null,
            ])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('audit_log')->insert(['terminal_id' => $user->terminal_id, 'action' => 'PASSWORD_RESET_SELF', 'user_name' => $user->username, 'ip_address' => $r->ip(), 'description' => 'Password transportir direset melalui tautan email', 'created_at' => now()]);
        });

        return $this->ok('Password berhasil diubah. Silakan masuk menggunakan password baru.');
    }

    public function register(Request $r): JsonResponse
    {
        $r->merge(['email' => strtolower(trim((string) $r->email))]);
        $v = Validator::make($r->all(), [
            'portal' => ['required', Rule::in(['transportir'])],
            'email' => 'required|email|max:100',
            'password' => ['required', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()],
            'full_name' => 'required|max:100', 'phone' => 'required|max:20', 'reason' => 'required|max:1000', 'requested_role' => ['required', Rule::in(['pengurus'])],
            'terminal_id' => ['required', 'integer', Rule::exists('integrated_terminals', 'id')->where(fn ($query) => $query->where('region_code', 'SULAWESI')->where('is_active', true))],
            'transporter_id' => ['required', 'integer', Rule::exists('transporters', 'id')->where(fn ($query) => $query->where('terminal_id', $r->integer('terminal_id'))->where('is_active', true))],
        ], [
            'password.min' => 'Password minimal 8 karakter.',
            'password.mixed' => 'Password harus mengandung huruf besar dan huruf kecil.',
            'password.numbers' => 'Password harus mengandung minimal satu angka.',
            'password.symbols' => 'Password harus mengandung minimal satu simbol.',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }
        $requestedRole = $r->string('requested_role')->toString();
        $email = strtolower(trim((string) $r->email));
        $username = 'user_'.substr(hash('sha256', $email), 0, 32);
        $exists = User::whereRaw('LOWER(email) = ?', [$email])->exists() || DB::table('user_registrations')->whereRaw('LOWER(email) = ?', [$email])->whereIn('status', ['pending', 'approved'])->exists();
        if ($exists) {
            return $this->fail('Email sudah terdaftar.', 409);
        }
        DB::table('user_registrations')->whereRaw('LOWER(email) = ?', [$email])->delete();
        $terminalId = $r->integer('terminal_id');
        DB::table('user_registrations')->insert(['username' => $username, 'email' => $email, 'password' => Hash::make($r->password), 'full_name' => $r->full_name, 'phone' => $r->phone, 'reason' => $r->reason, 'requested_role' => $requestedRole, 'terminal_id' => $terminalId, 'transporter_id' => $r->filled('transporter_id') ? $r->integer('transporter_id') : null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return $this->ok('Pendaftaran berhasil dikirim dan menunggu persetujuan administrator.');
    }

    public function logout(Request $r): JsonResponse
    {
        $this->audit($r, 'LOGOUT', 'Pengguna keluar dan token aktif dicabut');
        $r->user()->currentAccessToken()?->delete();

        return $this->ok('Logout berhasil.');
    }

    public function me(Request $r): JsonResponse
    {
        return $this->ok('OK', $this->userData($r->user()));
    }

    public function updateProfile(Request $r): JsonResponse
    {
        $user = $r->user();
        $validated = $r->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'current_password' => ['required', 'string'],
        ]);
        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->fail('Password saat ini tidak sesuai.', 422);
        }
        $user->fill([
            'full_name' => trim($validated['full_name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
        ])->save();
        $this->audit($r, 'PROFILE_UPDATE', 'Profil pengguna diperbarui');

        return $this->ok('Profil berhasil diperbarui.', $this->userData($user->fresh()));
    }

    public function changePassword(Request $r): JsonResponse
    {
        $validated = $r->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $user = $r->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->fail('Password saat ini tidak sesuai.', 422);
        }
        $user->password = $validated['password'];
        $user->save();
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '<>', $currentTokenId)->delete();
        }
        $this->audit($r, 'PASSWORD_CHANGE', 'Password pengguna diubah dari halaman profil');

        return $this->ok('Password berhasil diubah.');
    }

    private function userData(User $u): array
    {
        $terminal = $u->terminal_id ? DB::table('integrated_terminals')->where('id', $u->terminal_id)->first(['id', 'code', 'name']) : null;

        return ['id' => $u->id, 'full_name' => $u->full_name, 'email' => $u->email, 'phone' => $u->phone, 'role' => $u->role, 'status' => $u->status, 'terminal_id' => $u->terminal_id, 'terminal' => $terminal, 'region_code' => $u->region_code];
    }

    private function canAccessTerminal(User $user, mixed $terminalId): bool
    {
        return $user->terminal_id !== null
            && $terminalId !== null
            && (int) $user->terminal_id === (int) $terminalId;
    }

    public function dashboard(Request $r): JsonResponse
    {
        $u = $r->user();
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $counts = [];
        if ($u->role !== 'pengurus') {
            $base = DB::table('formulir_checklist');
            if ($u->role === 'regional_manager') {
                $base->whereIn('terminal_id', DB::table('integrated_terminals')->where('region_code', $u->region_code ?: 'SULAWESI')->where('is_active', true)->pluck('id'));
            } elseif ($u->terminal_id) {
                $base->where('terminal_id', $u->terminal_id);
            }
            $counts = ['checklists' => (clone $base)->count(), 'spbu' => (clone $base)->whereRaw('UPPER(jenis_kendaraan)=?', ['SPBU'])->count(), 'industri' => (clone $base)->whereRaw('UPPER(jenis_kendaraan)=?', ['INDUSTRI'])->count(), 'bulan_ini' => (clone $base)->whereBetween('tanggal_pemeriksaan', [$start, $end])->count()];
            foreach (self::STATES as $s) {
                $counts[$s] = (clone $base)->where('status_approval', $s)->count();
            }
        }
        if ($u->role === 'admin') {
            $terminalUsers = User::where('terminal_id', $u->terminal_id)->whereNotIn('role', ['admin', 'regional_manager']);
            $counts += [
                'users' => (clone $terminalUsers)->count(),
                'active_users' => (clone $terminalUsers)->where('status', 'active')->count(),
                'pending_registrations' => DB::table('user_registrations')->where('terminal_id', $u->terminal_id)->where('status', 'pending')->count(),
                'vehicles' => DB::table('kendaraan')->where('terminal_id', $u->terminal_id)->count(),
                'pending_vehicles' => DB::table('kendaraan')->where('terminal_id', $u->terminal_id)->where('approval_status', 'PENDING')->count(),
                'pending_documents' => DB::table('dokumen_kendaraan')->where('terminal_id', $u->terminal_id)->where('status', 'PENDING')->count(),
                'vehicle_alerts' => DB::table('kendaraan')->where('terminal_id', $u->terminal_id)->whereNotNull('ekim_valid_until')->whereDate('ekim_valid_until', '<=', today()->addDays(30))->count(),
            ];
        }
        if (in_array($u->role, ['admin', 'hsse', 'manager_hsse'], true)) {
            $vehicles = DB::table('kendaraan')->where('terminal_id', $u->terminal_id);
            $counts += [
                'ekim_active' => (clone $vehicles)->whereNotNull('ekim_valid_until')->whereDate('ekim_valid_until', '>', today()->addDays(30))->count(),
                'ekim_expiring' => (clone $vehicles)->whereBetween('ekim_valid_until', [today()->toDateString(), today()->addDays(30)->toDateString()])->count(),
                'ekim_expired' => (clone $vehicles)->whereNotNull('ekim_valid_until')->whereDate('ekim_valid_until', '<', today())->count(),
                'pending_documents' => DB::table('dokumen_kendaraan')->where('terminal_id', $u->terminal_id)->where('status', 'PENDING')->count(),
                'pending_hsse_queue' => DB::table('formulir_checklist')->where('terminal_id', $u->terminal_id)->where('status_approval', 'pending_hsse')->count(),
                'pending_manager_queue' => DB::table('formulir_checklist')->where('terminal_id', $u->terminal_id)->where('status_approval', 'signed_hsse')->count(),
            ];
        }
        if ($u->role === 'pengurus') {
            $rows = $this->managedVehicles($u->id);
            $counts['ekim_notifications'] = DB::table('ekim_notifikasi')->where('user_id', $u->id)->orderByDesc('created_at')->limit(10)->get();
            $counts['unread_ekim_notifications'] = DB::table('ekim_notifikasi')->where('user_id', $u->id)->where('is_read', false)->count();
            $counts += ['my_vehicles' => count($rows), 'my_pending_documents' => collect($rows)->sum('pending_dokumen'), 'my_approved_documents' => collect($rows)->sum('disetujui_dokumen'), 'my_rejected_documents' => collect($rows)->sum('ditolak_dokumen')];
            $plates = collect($rows)->pluck('nomor_polisi');
            $counts['my_checklists'] = DB::table('formulir_checklist')->whereIn('nomor_polisi', $plates)->latest('created_at')->limit(25)->get(['id', 'nomor_polisi', 'jenis_kendaraan', 'status_approval', 'tanggal_pemeriksaan', 'ekim_valid_until']);
        }
        $counts['profile'] = $this->userData($u);

        return $this->ok('OK', $counts);
    }

    public function ekimNotifications(Request $r): JsonResponse
    {
        $this->requireRole($r, ['pengurus']);
        $notifications = DB::table('ekim_notifikasi')
            ->where('user_id', $r->user()->id)
            ->whereExists(fn ($scope) => $scope->from('kendaraan as k')->join('transporter_user as tu', 'tu.transporter_id', '=', 'k.transporter_id')->whereColumn('k.nomor_polisi', 'ekim_notifikasi.nomor_polisi')->where('tu.user_id', $r->user()->id))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->ok('OK', [
            'notifications' => $notifications,
            'unread_count' => $notifications->where('is_read', false)->count(),
        ]);
    }

    public function readEkimNotifications(Request $r): JsonResponse
    {
        $this->requireRole($r, ['pengurus']);
        $query = DB::table('ekim_notifikasi')->where('user_id', $r->user()->id)
            ->whereExists(fn ($scope) => $scope->from('kendaraan as k')->join('transporter_user as tu', 'tu.transporter_id', '=', 'k.transporter_id')->whereColumn('k.nomor_polisi', 'ekim_notifikasi.nomor_polisi')->where('tu.user_id', $r->user()->id));
        if ($r->filled('id')) {
            $query->where('id', $r->integer('id'));
        }
        $updated = $query->where('is_read', false)->update(['is_read' => true]);

        return $this->ok('Notifikasi telah ditandai dibaca.', ['updated' => $updated]);
    }

    public function checklistStats(Request $r): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse', 'regional_manager']);
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $query = DB::table('formulir_checklist');
        if ($r->user()->role === 'regional_manager') {
            $query->whereIn('terminal_id', DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->pluck('id'));
            if ($r->filled('terminal_id')) {
                $query->where('terminal_id', $r->integer('terminal_id'));
            }
        } elseif ($r->user()->terminal_id) {
            $query->where('terminal_id', $r->user()->terminal_id);
        }

        return $this->ok('OK', ['total' => (clone $query)->count(), 'spbu' => (clone $query)->whereRaw('UPPER(jenis_kendaraan)=?', ['SPBU'])->count(), 'industri' => (clone $query)->whereRaw('UPPER(jenis_kendaraan)=?', ['INDUSTRI'])->count(), 'bulan_ini' => (clone $query)->whereBetween('tanggal_pemeriksaan', [$start, $end])->count(), 'kim_kedaluwarsa' => (clone $query)->whereNotNull('ekim_valid_until')->whereDate('ekim_valid_until', '<', today())->count()]);
    }

    public function vehicles(Request $r, ChecklistComplianceService $compliance): JsonResponse
    {
        $q = DB::table('kendaraan as k')->leftJoin('integrated_terminals as t', 't.id', '=', 'k.terminal_id')->select('k.*', 't.code as terminal_code', 't.name as terminal_name')->selectSub(fn ($s) => $s->from('pengurus_kendaraan as pk')->whereColumn('pk.nomor_polisi', 'k.nomor_polisi')->latest('pk.created_at')->select('pk.user_id')->limit(1), 'user_id_transportir')->latest('k.created_at');
        if ($r->user()->role === 'pengurus') {
            $q->whereExists(fn ($s) => $s->from('pengurus_kendaraan as owner')->whereColumn('owner.nomor_polisi', 'k.nomor_polisi')->where('owner.user_id', $r->user()->id))
                ->whereExists(fn ($s) => $s->from('transporter_user as tu')->whereColumn('tu.transporter_id', 'k.transporter_id')->where('tu.user_id', $r->user()->id));
        } elseif ($r->user()->role === 'regional_manager') {
            $q->whereIn('k.terminal_id', DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->pluck('id'));
        } elseif ($r->user()->terminal_id) {
            $q->where('k.terminal_id', $r->user()->terminal_id);
        }

        $vehicles = $q->get();
        $expiryByVehicle = $compliance->effectiveDocumentExpiries($vehicles);
        $vehicles->each(function ($vehicle) use ($expiryByVehicle): void {
            $vehicle->approved_document_expiries = $expiryByVehicle[$vehicle->nomor_polisi] ?? [];
        });

        return $this->ok('OK', $vehicles);
    }

    public function transporters(Request $r): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse']);

        $query = User::query()
            ->where('role', 'pengurus')
            ->where('status', 'active')
            ->orderBy('full_name');
        if ($r->user()->terminal_id) {
            $query->where('terminal_id', $r->user()->terminal_id);
        }

        $rows = $query->get(['id', 'full_name', 'email', 'department', 'position', 'terminal_id']);
        $links = DB::table('transporter_user as tu')
            ->join('transporters as t', 't.id', '=', 'tu.transporter_id')
            ->whereIn('tu.user_id', $rows->pluck('id'))
            ->where('t.is_active', true)
            ->get(['tu.user_id', 't.id', 't.code', 't.name']);
        $rows->each(function ($row) use ($links): void {
            $row->transporters = $links->where('user_id', $row->id)->values();
            $row->transporter_ids = $row->transporters->pluck('id')->values();
        });

        return $this->ok('OK', $rows);
    }

    public function myTransporters(Request $r): JsonResponse
    {
        $this->requireRole($r, ['pengurus']);

        return $this->ok('OK', DB::table('transporters as t')
            ->join('transporter_user as tu', 'tu.transporter_id', '=', 't.id')
            ->where('tu.user_id', $r->user()->id)
            ->where('t.terminal_id', $r->user()->terminal_id)
            ->where('t.is_active', true)
            ->orderBy('t.name')
            ->get(['t.id', 't.code', 't.name', 't.email']));
    }

    public function filterTransporters(Request $r): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse', 'regional_manager']);
        $query = DB::table('transporters as t')->where('t.is_active', true);
        if ($r->user()->role === 'regional_manager') {
            $query->whereIn('t.terminal_id', DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->pluck('id'));
            if ($r->filled('terminal_id')) {
                $query->where('t.terminal_id', $r->integer('terminal_id'));
            }
        } else {
            $query->where('t.terminal_id', $r->user()->terminal_id);
        }

        return $this->ok('OK', $query->orderBy('t.name')->get(['t.id', 't.name', 't.terminal_id']));
    }

    public function template(Request $r): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse', 'regional_manager']);
        $jenis = strtoupper($r->query('jenis', 'SPBU')) === 'INDUSTRI' ? 'INDUSTRI' : 'SPBU';
        $file = resource_path('checklists/'.strtolower($jenis).'.html');
        if (! is_file($file) || ! is_readable($file)) {
            report(new \RuntimeException("Template checklist {$jenis} tidak tersedia: {$file}"));

            return $this->fail('Template item pemeriksaan tidak tersedia. Hubungi administrator sistem.', 500);
        }

        $html = file_get_contents($file);
        if ($html === false || trim($html) === '') {
            report(new \RuntimeException("Template checklist {$jenis} kosong: {$file}"));

            return $this->fail('Template item pemeriksaan tidak dapat dibaca. Hubungi administrator sistem.', 500);
        }

        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp = new DOMXPath($dom);
        $items = [];
        foreach ($xp->query('//table[@id="checklistTable"]//tbody/tr') as $row) {
            $c = $xp->query('./td', $row);
            if ($c->length < 5) {
                continue;
            }$o = preg_match('/^\d+$/', trim($c->item(0)->textContent)) ? 1 : 0;
            $name = trim(preg_replace('/\s+/', ' ', $c->item($o)->textContent));
            if ($name === '' || stripos($name, 'tanda tangan') !== false) {
                continue;
            }$items[] = ['nama' => $name, 'pelaksana' => trim($c->item($o + 1)->textContent ?? ''), 'prioritas' => trim($c->item($o + 2)->textContent ?? ''), 'nomor' => $o ? trim($c->item(0)->textContent) : null, 'rowspan' => $o ? max(1, (int) ($c->item(0)->getAttribute('rowspan') ?: 1)) : 0];
        }

        if ($items === []) {
            report(new \RuntimeException("Template checklist {$jenis} tidak menghasilkan item pemeriksaan: {$file}"));

            return $this->fail('Item pemeriksaan tidak dapat dimuat. Hubungi administrator sistem.', 500);
        }

        return $this->ok('OK', $items);
    }

    public function checklists(Request $r): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse', 'regional_manager']);
        $limit = max(1, min(100, (int) $r->query('limit', 50)));
        $q = DB::table('formulir_checklist as f')->leftJoin('integrated_terminals as t', 't.id', '=', 'f.terminal_id');
        if ($r->user()->role === 'regional_manager') {
            $allowedTerminalIds = DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->pluck('id');
            $q->whereIn('f.terminal_id', $allowedTerminalIds);
            if ($r->filled('terminal_id')) {
                if (! $allowedTerminalIds->contains($r->integer('terminal_id'))) {
                    return $this->fail('Integrated Terminal berada di luar wilayah kewenangan.', 403);
                }
                $q->where('f.terminal_id', $r->integer('terminal_id'));
            }
        } elseif ($r->user()->terminal_id) {
            $q->where('f.terminal_id', $r->user()->terminal_id);
        }
        if ($s = trim($r->query('search', ''))) {
            $q->where(fn ($x) => $x->where('f.nomor_polisi', 'ilike', "%$s%")->orWhere('f.nama_transport', 'ilike', "%$s%")->orWhere('f.nomor_urut', 'ilike', "%$s%"));
        }
        if ($d = $r->query('dateFrom')) {
            $q->whereDate('f.tanggal_pemeriksaan', '>=', $d);
        } if ($d = $r->query('dateTo')) {
            $q->whereDate('f.tanggal_pemeriksaan', '<=', $d);
        }
        if (in_array($r->query('jenis'), ['SPBU', 'INDUSTRI'], true)) {
            $q->where('f.jenis_kendaraan', $r->query('jenis'));
        } if (in_array($r->query('status'), self::STATES, true)) {
            $q->where('f.status_approval', $r->query('status'));
        }
        if ($r->filled('transporter_id')) {
            $q->whereExists(fn ($sub) => $sub->from('kendaraan as kv')
                ->whereColumn('kv.nomor_polisi', 'f.nomor_polisi')
                ->where('kv.transporter_id', $r->integer('transporter_id')));
        }
        if ($r->boolean('signedByMe')) {
            if ($r->user()->role !== 'manager_hsse') {
                return $this->fail('Riwayat persetujuan hanya dapat diakses oleh Manager HSSE.', 403);
            }
            $q->whereIn('f.status_approval', ['approved', 'expired'])
                ->where('f.ttd_manajer_user_id', $r->user()->id)
                ->whereNotNull('f.ttd_manajer_timestamp');
        }
        $p = $q->select('f.*', 't.code as terminal_code', 't.name as terminal_name')->selectSub(fn ($s) => $s->from('checklist_items')->whereColumn('formulir_id', 'f.id')->selectRaw('COUNT(*)'), 'total_items')->selectSub(fn ($s) => $s->from('checklist_items')->whereColumn('formulir_id', 'f.id')->where('is_baik', true)->selectRaw('COUNT(*)'), 'total_baik')->selectSub(fn ($s) => $s->from('checklist_items')->whereColumn('formulir_id', 'f.id')->where('is_tidak', true)->selectRaw('COUNT(*)'), 'total_tidak')->orderByDesc('f.tanggal_pemeriksaan')->orderByDesc('f.created_at')->paginate($limit);
        $data = collect($p->items())->map(function ($x) {
            $x = (array) $x;
            $x['persentase_baik'] = $x['total_items'] ? round($x['total_baik'] / $x['total_items'] * 100, 2) : 0;
            $x['ekim_is_expired'] = ! empty($x['ekim_valid_until']) && $x['ekim_valid_until'] < today()->toDateString() ? 1 : 0;

            return $x;
        })->values();

        return response()->json(['success' => true, 'message' => 'Data berhasil dimuat', 'data' => $data, 'pagination' => ['page' => $p->currentPage(), 'limit' => $p->perPage(), 'total' => $p->total(), 'totalPages' => $p->lastPage()]]);
    }

    public function checklist(Request $r, int $id): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse', 'regional_manager']);
        $query = DB::table('formulir_checklist as f')->leftJoin('integrated_terminals as t', 't.id', '=', 'f.terminal_id')->select('f.*', 't.code as terminal_code', 't.name as terminal_name')->where('f.id', $id);
        if ($r->user()->role === 'regional_manager') {
            $query->whereIn('f.terminal_id', DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->pluck('id'));
        } elseif ($r->user()->terminal_id) {
            $query->where('f.terminal_id', $r->user()->terminal_id);
        }
        $f = $query->first();
        if (! $f) {
            return $this->fail('Data tidak ditemukan.', 404);
        }
        $d = (array) $f;
        $d['viewer_role'] = $r->user()->role;
        $d['viewer_is_manager'] = $r->user()->role === 'manager_hsse';
        $d['viewer_can_sign_hsse'] = in_array($r->user()->role, ['admin', 'hsse'], true);
        $d['viewer_can_approve'] = $r->user()->role === 'manager_hsse' && $f->status_approval === 'signed_hsse';
        $d['checklist_items'] = DB::table('checklist_items')->where('formulir_id', $id)->orderBy('item_number')->get();

        return $this->ok('Data berhasil dimuat', $d);
    }

    public function saveChecklist(Request $r, ChecklistExpiryPolicy $expiryPolicy, ChecklistComplianceService $compliance, ?int $id = null): JsonResponse
    {
        if ($id !== null) {
            $r->merge(['id' => $id]);
        }
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse']);
        if ($r->user()->role === 'manager_hsse') {
            return $this->fail('Manager tidak dapat mengubah formulir.', 403);
        }
        $v = Validator::make($r->all(), [
            'selectedVehicleId' => 'required|integer',
            'jenisKendaraan' => ['required', Rule::in(['SPBU', 'INDUSTRI'])],
            'namaPemeriksaBagian' => 'required|string|max:100',
            'tanggalPemeriksaan' => 'required|date',
            'tanggalPemeriksaBagian' => 'required|date',
            'ekimValidUntil' => 'required|date|after_or_equal:tanggalPemeriksaan',
            'checklist' => 'required|array|min:1',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }
        if ($itemError = $compliance->validateItems($r->input('checklist', []))) {
            return $this->fail($itemError, 422);
        }
        $vehicle = DB::table('kendaraan')->where('id', $r->selectedVehicleId)->where('status', 'AKTIF')->first();
        if (! $vehicle) {
            return $this->fail('Kendaraan tidak ditemukan atau sudah tidak aktif.', 404);
        }
        if (! $this->canAccessTerminal($r->user(), $vehicle->terminal_id)) {
            return $this->fail('Kendaraan berada di luar kewenangan Integrated Terminal Anda.', 403);
        }
        if (strtoupper($vehicle->jenis) !== strtoupper($r->jenisKendaraan)) {
            return $this->fail('Jenis kendaraan tidak sesuai dengan formulir.', 422);
        }
        if ($r->user()->role === 'pengurus' && ! DB::table('pengurus_kendaraan')->where(['user_id' => $r->user()->id, 'nomor_polisi' => $vehicle->nomor_polisi])->exists()) {
            return $this->fail('Kendaraan ini bukan tanggung jawab akun Anda.', 403);
        }
        $expiryError = $expiryPolicy->validate($r->checklist, $vehicle->jenis, $r->tanggalPemeriksaan);
        if ($expiryError !== null) {
            return $this->fail($expiryError);
        }
        if ($r->id) {
            $existing = DB::table('formulir_checklist')->where('id', $r->id)->first();
            if (! $existing) {
                return $this->fail('Data tidak ditemukan.', 404);
            }
            if (! $this->canAccessTerminal($r->user(), $existing->terminal_id)) {
                return $this->fail('Checklist berada di luar kewenangan Integrated Terminal Anda.', 403);
            }
        }
        try {
            $id = DB::transaction(function () use ($r, $vehicle, $expiryPolicy) {
                $templateVersion = hash('sha256', json_encode(collect($r->checklist)->pluck('nama')->values(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $values = ['terminal_id' => $vehicle->terminal_id ?: $r->user()->terminal_id, 'jenis_kendaraan' => $vehicle->jenis, 'template_version' => $templateVersion, 'nomor_urut' => $r->nomorUrut, 'merk_mobil' => trim($vehicle->merk_mobil.' '.($vehicle->tahun_kendaraan ?? '')), 'nama_transport' => $vehicle->nama_transport, 'nomor_polisi' => $vehicle->nomor_polisi, 'tanggal_terakhir' => $vehicle->tanggal_pemeriksaan_terakhir, 'produk_kapasitas' => $vehicle->produk_kapasitas, 'tanggal_pemeriksaan' => $r->tanggalPemeriksaan, 'ekim_valid_until' => $r->ekimValidUntil, 'status_gate' => $r->statusGate, 'status_upload' => $r->statusUpload, 'catatan' => $r->catatan, 'nama_pemeriksa' => $r->namaPemeriksaBagian, 'tanggal_pemeriksa' => $r->tanggalPemeriksaBagian, 'updated_at' => now()];
                if ($r->id) {
                    $old = DB::table('formulir_checklist')->where('id', $r->id)->first();
                    if (! $old) {
                        return 0;
                    }if ($old->status_approval !== 'draft') {
                        throw new HttpException(409, 'Dokumen bukan draft dan tidak dapat diubah.');
                    }DB::table('formulir_checklist')->where('id', $r->id)->update($values);
                    $id = (int) $r->id;
                    DB::table('checklist_items')->where('formulir_id', $id)->delete();
                } else {
                    $values += ['created_by' => $r->user()->id, 'status_approval' => 'draft', 'created_at' => now()];
                    $id = DB::table('formulir_checklist')->insertGetId($values);
                }foreach ($r->checklist as $i => $item) {
                    DB::table('checklist_items')->insert(['formulir_id' => $id, 'item_number' => $i + 1, 'item_name' => $item['nama'] ?? ('Item '.($i + 1)), 'is_baik' => ! empty($item['baik']), 'is_tidak' => ! empty($item['tidak']), 'keterangan' => $item['keterangan'] ?? null, 'tanggal_expire' => $expiryPolicy->tracksExpiry($item['nama'] ?? '') ? ($item['tanggal_expire'] ?: null) : null]);
                }

                return $id;
            });
            if (! $id) {
                return $this->fail('Data tidak ditemukan.', 404);
            }$this->audit($r, $r->id ? 'UPDATE' : 'CREATE', "Formulir checklist {$vehicle->nomor_polisi}", $id);

            return $this->ok($r->id ? 'Data berhasil diupdate' : 'Data berhasil disimpan', ['id' => $id]);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage(), $e->getStatusCode());
        }
    }

    public function deleteChecklist(Request $r, int $id, ChecklistWorkflowPolicy $workflowPolicy): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse']);
        if ($r->user()->role === 'manager_hsse') {
            return $this->fail('Akses ditolak.', 403);
        }$f = DB::table('formulir_checklist')->where('id', $id)->first();
        if (! $f) {
            return $this->fail('Data tidak ditemukan.', 404);
        }
        if (! $this->canAccessTerminal($r->user(), $f->terminal_id)) {
            return $this->fail('Checklist berada di luar kewenangan Integrated Terminal Anda.', 403);
        }
        if (! $workflowPolicy->canDelete($r->user()->role, $f->status_approval)) {
            return $this->fail('Hanya draft yang dapat dihapus.', 409);
        }DB::table('formulir_checklist')->where('id', $id)->delete();
        $this->audit($r, 'DELETE', "Formulir {$f->nomor_polisi} dihapus");

        return $this->ok('Data berhasil dihapus.');
    }

    private function canonical(int $id, string $algo = 'sha256'): ?string
    {
        $f = DB::table('formulir_checklist')->where('id', $id)->first();
        if (! $f) {
            return null;
        }$items = DB::table('checklist_items')->where('formulir_id', $id)->orderBy('item_number')->get()->map(fn ($x) => (array) $x)->all();

        return $this->signatureService->canonicalHash((array) $f, $items, $algo);
    }

    private function sign(string $hash): string
    {
        return $this->signatureService->sign($hash, (string) config('app.signature_secret'));
    }

    public function signature(Request $r, ChecklistSignaturePolicy $signaturePolicy, ChecklistComplianceService $compliance): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse']);
        $v = Validator::make($r->all(), ['formulir_id' => 'required|integer', 'role' => ['required', Rule::in(['hsse', 'manajer'])], 'canvas_image' => ['required', 'string', 'max:3145728', 'regex:#^data:image/png;base64,#'], 'signer_name' => 'required|string|max:100']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }
        $u = $r->user();
        if (! $signaturePolicy->roleCanSign($u->role, $r->role)) {
            $message = $r->role === 'manajer'
                ? 'Hanya Manager yang dapat menandatangani.'
                : 'Hanya Tim HSSE atau Admin yang dapat menandatangani.';

            return $this->fail($message, 403);
        }
        if (! $signaturePolicy->signerNameMatches($r->signer_name, $u->full_name ?: $u->username)) {
            return $this->fail("Nama penandatangan harus sama dengan akun aktif: {$u->full_name}");
        }
        $f = DB::table('formulir_checklist')->where('id', $r->formulir_id)->first();
        if (! $f) {
            return $this->fail('Formulir tidak ditemukan.', 404);
        }
        if (! $this->canAccessTerminal($u, $f->terminal_id)) {
            return $this->fail('Checklist berada di luar kewenangan Integrated Terminal Anda.', 403);
        }
        if ($r->role === 'hsse' && ($approvalError = $compliance->firstApprovalError($f))) {
            return $this->fail($approvalError, 422);
        }
        $bad = DB::table('checklist_items')->where('formulir_id', $f->id)->where('is_tidak', true)->pluck('item_name')->all();
        $contentError = $signaturePolicy->contentError($bad, $f->ekim_valid_until, today()->toDateString());
        if ($contentError !== null) {
            return $this->fail($contentError, 422, $bad !== [] ? ['tidak_items' => $bad] : null);
        }
        $name = $u->full_name ?: $u->username;
        $hash = $this->canonical($f->id);
        $sig = $this->sign($hash);
        $uuid = $f->verification_uuid ?: Str::uuid()->toString();
        $url = rtrim(config('app.frontend_url'), '/').'/'.'#/verify/'.$uuid;
        if ($r->role === 'hsse') {
            if (! $signaturePolicy->statusAllowsSignature('hsse', $f->status_approval)) {
                return $this->fail('Status formulir tidak dapat ditandatangani HSSE.', 409);
            }DB::table('formulir_checklist')->where('id', $f->id)->update(['dokumen_hash' => $hash, 'status_approval' => 'signed_hsse', 'ttd_hsse_user_id' => $u->id, 'ttd_hsse_nama' => $name, 'ttd_hsse_timestamp' => now(), 'ttd_hsse_signature' => $sig, 'ttd_hsse_hash' => $hash, 'ttd_hsse_gambar' => $r->canvas_image, 'verification_uuid' => $uuid, 'verification_url' => $url]);
            $action = 'SIGN_HSSE';
            $status = 'signed_hsse';
            $message = "Tanda tangan digital HSSE berhasil disimpan oleh $name.";
        } else {
            if (! $signaturePolicy->statusAllowsSignature('manajer', $f->status_approval)) {
                return $this->fail('Formulir harus ditandatangani HSSE terlebih dahulu.', 409);
            }if (! hash_equals((string) $f->dokumen_hash, (string) $hash) || ! hash_equals((string) $f->ttd_hsse_signature, $this->sign($hash))) {
                return $this->fail('Integritas dokumen gagal. Isi berubah setelah tanda tangan HSSE.', 409);
            }$final = $this->canonical($f->id, 'sha512');
            DB::table('formulir_checklist')->where('id', $f->id)->update(['status_approval' => 'approved', 'ttd_manajer_user_id' => $u->id, 'ttd_manajer_nama' => $name, 'ttd_manajer_timestamp' => now(), 'ttd_manajer_signature' => $sig, 'ttd_manajer_hash' => $hash, 'ttd_manajer_gambar' => $r->canvas_image, 'verification_hash_sha512' => $final, 'verification_signature' => $this->sign($final), 'verification_created_at' => now(), 'verification_uuid' => $uuid, 'verification_url' => $url]);
            DB::table('kendaraan')->where('nomor_polisi', $f->nomor_polisi)->update(['ekim_valid_until' => $f->ekim_valid_until, 'tanggal_pemeriksaan_terakhir' => $f->tanggal_pemeriksaan, 'updated_at' => now()]);
            $action = 'SIGN_MANAJER';
            $status = 'approved';
            $message = "Tanda tangan Manajer berhasil disimpan oleh $name. Formulir telah DISETUJUI.";
            $this->notifyOwners($f->nomor_polisi, $f->id, 'issued', "EKIM kendaraan {$f->nomor_polisi} telah DITERBITKAN.");
        }
        DB::table('digital_signature_log')->insert(['formulir_id' => $f->id, 'action' => $action, 'user_id' => $u->id, 'user_name' => $name, 'role_signer' => $u->role, 'dokumen_hash' => $hash, 'signature_snippet' => substr($sig, 0, 50), 'ip_address' => $r->ip(), 'created_at' => now()]);
        $compliance->recordApproval($f, $u, $r->role === 'hsse' ? 'approved_hsse' : 'approved_final', $r->input('notes'), $hash, $r->ip());
        $this->audit($r, 'UPDATE', $message, $f->id);

        return $this->ok($message, ['nama' => $name, 'waktu' => now()->toDateTimeString(), 'new_status' => $status, 'verification_url' => $url]);
    }

    public function workflow(Request $r, ChecklistFailureReasonBuilder $reasonBuilder, ChecklistWorkflowPolicy $workflowPolicy, ChecklistComplianceService $compliance): JsonResponse
    {
        $this->requireRole($r, ['admin', 'hsse', 'manager_hsse']);
        $f = DB::table('formulir_checklist')->where('id', $r->integer('formulir_id'))->first();
        if (! $f) {
            return $this->fail('Formulir tidak ditemukan.', 404);
        }
        if (! $this->canAccessTerminal($r->user(), $f->terminal_id)) {
            return $this->fail('Checklist berada di luar kewenangan Integrated Terminal Anda.', 403);
        }
        $action = $r->input('action');
        if ($action === 'submit') {
            if (! $workflowPolicy->canSubmit($r->user()->role, $f->status_approval)) {
                return $this->fail('Aksi tidak diizinkan.', 403);
            }
            $failureReason = $this->checklistFailureReason($f->id, $reasonBuilder);
            if ($failureReason !== '') {
                DB::table('formulir_checklist')->where('id', $f->id)->update(['status_approval' => 'rejected']);
                $state = 'rejected';
                $this->notifyOwners($f->nomor_polisi, $f->id, 'rejected', "E-KIM kendaraan {$f->nomor_polisi} tidak dapat diterbitkan. Alasan: $failureReason");
            } else {
                DB::table('formulir_checklist')->where('id', $f->id)->update(['status_approval' => 'pending_hsse']);
                $state = 'pending_hsse';
            }
        } elseif ($action === 'reject') {
            if (blank($r->input('reason'))) {
                return $this->fail('Alasan penolakan wajib diisi.', 422);
            }
            $canReject = $r->user()->role === 'admin'
                || $workflowPolicy->canReject($r->user()->role, $f->status_approval);
            if (! $canReject) {
                return $this->fail('Aksi penolakan tidak diizinkan.', 403);
            }
            if (! in_array($f->status_approval, ['pending_hsse', 'signed_hsse'], true)) {
                return $this->fail('Status tidak dapat ditolak.', 409);
            }DB::table('formulir_checklist')->where('id', $f->id)->update(['status_approval' => 'rejected']);
            $state = 'rejected';
            $failureReason = $this->checklistFailureReason($f->id, $reasonBuilder, $r->input('reason'));
            if ($failureReason === '') {
                $failureReason = 'Checklist tidak memenuhi persyaratan penerbitan E-KIM.';
            }
            $this->notifyOwners($f->nomor_polisi, $f->id, 'rejected', "E-KIM kendaraan {$f->nomor_polisi} tidak dapat diterbitkan. Alasan: $failureReason");
            $compliance->recordApproval($f, $r->user(), 'rejected', $failureReason, $this->canonical($f->id), $r->ip());
        } elseif ($action === 'reset_draft' && $r->user()->role === 'admin') {
            if (! $workflowPolicy->canResetDraft($r->user()->role, $f->status_approval)) {
                return $this->fail('Formulir approved tidak dapat di-reset.', 409);
            }DB::table('formulir_checklist')->where('id', $f->id)->update(['status_approval' => 'draft', 'dokumen_hash' => null, 'ttd_hsse_user_id' => null, 'ttd_hsse_nama' => null, 'ttd_hsse_signature' => null, 'ttd_hsse_timestamp' => null, 'ttd_manajer_user_id' => null, 'ttd_manajer_nama' => null, 'ttd_manajer_signature' => null, 'ttd_manajer_timestamp' => null, 'verification_uuid' => null, 'verification_hash_sha512' => null, 'verification_signature' => null, 'verification_url' => null, 'verification_created_at' => null]);
            $state = 'draft';
        } else {
            return $this->fail('Aksi tidak dikenali atau tidak diizinkan.', 403);
        }$this->audit($r, 'UPDATE', "Status checklist menjadi $state", $f->id);

        $message = $state === 'rejected'
            ? 'Checklist ditolak dan alasan kegagalan E-KIM telah dikirim kepada transportir.'
            : 'Status checklist berhasil diperbarui.';

        return $this->ok($message, ['new_status' => $state, 'reason' => $failureReason ?? null]);
    }

    public function verify(Request $r, string $value, EkimVerificationEvaluator $evaluator): JsonResponse
    {
        $q = DB::table('formulir_checklist');
        $f = Str::isUuid($value) ? $q->where('verification_uuid', $value)->first() : $q->where('qr_token', $value)->first();
        if (! $f) {
            return $this->fail('Dokumen tidak ditemukan.', 404);
        }$hash = $this->canonical($f->id);
        $unchanged = $f->dokumen_hash && hash_equals($f->dokumen_hash, $hash);
        $hsse = $unchanged && hash_equals((string) $f->ttd_hsse_signature, $this->sign($hash));
        $manager = $unchanged && hash_equals((string) $f->ttd_manajer_signature, $this->sign($hash));
        $finalHash = $this->canonical($f->id, 'sha512');
        $final = $unchanged && ! empty($f->verification_hash_sha512) && hash_equals((string) $f->verification_hash_sha512, (string) $finalHash) && hash_equals((string) $f->verification_signature, $this->sign($finalHash));
        $notExpired = ! $f->ekim_valid_until || $f->ekim_valid_until >= today()->toDateString();
        $verification = $evaluator->evaluate(
            $f->status_approval,
            $unchanged,
            $hsse,
            $manager,
            $final,
            $notExpired,
        );
        $safe = collect((array) $f)->only(['id', 'nomor_polisi', 'nama_transport', 'jenis_kendaraan', 'tanggal_pemeriksaan', 'ekim_valid_until', 'status_approval', 'ttd_hsse_nama', 'ttd_hsse_timestamp', 'ttd_manajer_nama', 'ttd_manajer_timestamp', 'verification_uuid', 'verification_created_at'])->all();
        $safe['validation'] = $verification['validation'];

        return $this->ok($verification['message'], $safe);
    }

    private function notifyOwners(string $plate, int $formId, string $status, string $message): void
    {
        $owners = DB::table('pengurus_kendaraan as pk')
            ->join('kendaraan as k', 'k.nomor_polisi', '=', 'pk.nomor_polisi')
            ->join('transporter_user as tu', fn ($join) => $join->on('tu.transporter_id', '=', 'k.transporter_id')->on('tu.user_id', '=', 'pk.user_id'))
            ->where('pk.nomor_polisi', $plate)
            ->pluck('pk.user_id');
        foreach ($owners as $uid) {
            DB::table('ekim_notifikasi')->updateOrInsert(
                ['user_id' => $uid, 'formulir_id' => $formId, 'status' => $status],
                ['nomor_polisi' => $plate, 'pesan' => $message, 'is_read' => false, 'created_at' => now()]
            );
        }
    }

    private function checklistFailureReason(int $formId, ChecklistFailureReasonBuilder $builder, ?string $manualReason = null): string
    {
        $badItems = DB::table('checklist_items')
            ->where('formulir_id', $formId)
            ->where('is_tidak', true)
            ->orderBy('item_number')
            ->get(['item_name', 'keterangan']);

        return $builder->build($badItems, $manualReason);
    }

    private function managedVehicles(int $uid): array
    {
        return DB::table('pengurus_kendaraan as pk')->join('kendaraan as k', 'k.nomor_polisi', '=', 'pk.nomor_polisi')->leftJoin('dokumen_kendaraan as dk', fn ($j) => $j->on('dk.nomor_polisi', '=', 'pk.nomor_polisi')->where('dk.uploaded_by', $uid))->where('pk.user_id', $uid)->whereExists(fn ($scope) => $scope->from('transporter_user as tu')->whereColumn('tu.transporter_id', 'k.transporter_id')->where('tu.user_id', $uid))->groupBy('pk.nomor_polisi', 'pk.nama_transport')->select('pk.nomor_polisi', 'pk.nama_transport')->selectRaw('COUNT(dk.id) total_dokumen')->selectRaw("SUM(CASE WHEN dk.status='PENDING' THEN 1 ELSE 0 END) pending_dokumen")->selectRaw("SUM(CASE WHEN dk.status='DISETUJUI' THEN 1 ELSE 0 END) disetujui_dokumen")->selectRaw("SUM(CASE WHEN dk.status='DITOLAK' THEN 1 ELSE 0 END) ditolak_dokumen")->selectRaw('MAX(dk.created_at) last_upload')->get()->map(fn ($x) => (array) $x)->all();
    }

    private function vehicleAlerts(int $days): array
    {
        $limit = today()->addDays($days)->toDateString();
        $rows = DB::table('formulir_checklist as f')->whereIn('status_approval', ['approved', 'expired'])->whereNotNull('ekim_valid_until')->where('ekim_valid_until', '<=', $limit)->whereRaw("f.id=(SELECT MAX(f2.id) FROM formulir_checklist f2 WHERE f2.nomor_polisi=f.nomor_polisi AND f2.status_approval IN ('approved','expired'))")->get();

        return $rows->map(fn ($x) => ['id' => $x->id, 'nomor_polisi' => $x->nomor_polisi, 'merk_mobil' => $x->merk_mobil, 'nama_transport' => $x->nama_transport, 'jenis' => $x->jenis_kendaraan, 'ekim_valid_until' => $x->ekim_valid_until, 'effective_valid_until' => $x->ekim_valid_until, 'hari_tersisa' => today()->diffInDays(Carbon::parse($x->ekim_valid_until), false), 'status_alert' => $x->ekim_valid_until < today()->toDateString() ? 'SUDAH_EXPIRED' : 'PERLU_INSPEKSI'])->all();
    }
}
