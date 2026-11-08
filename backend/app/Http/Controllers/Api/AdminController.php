<?php

namespace App\Http\Controllers\Api;

use App\Domain\System\BackupFilePolicy;
use App\Domain\Vehicle\VehicleDataNormalizer;
use App\Http\Controllers\Concerns\InteractsWithPrimaApi;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\ProcessRunService;
use App\Services\RetentionService;
use App\Services\VehicleHistoryService;
use App\Support\SimpleXlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminController extends Controller
{
    use InteractsWithPrimaApi;

    private const DOCUMENT_CHECKLIST_ITEMS = [
        'STNK' => 'STNK',
        'PAJAK' => 'PAJAK',
        'SIM' => 'SIMFIT (Industri)',
        'SURAT_KEUR' => 'Surat Keur DLLAAJR',
        'SURAT_TERA' => 'Surat Tera Metrologi',
    ];

    private function admin(Request $r): void
    {
        $this->requireRole($r, ['admin']);
    }

    public function users(Request $r): JsonResponse
    {
        $this->admin($r);

        $users = User::with('terminal:id,code,name')->select('id', 'username', 'full_name', 'email', 'phone', 'department', 'position', 'role', 'terminal_id', 'region_code', 'status', 'last_login', 'created_at')
            ->where('terminal_id', $r->user()->terminal_id)
            ->where('role', '<>', 'admin')
            ->where('role', '<>', 'regional_manager')
            ->latest()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'department' => $user->department,
                'position' => $user->position,
                'role' => $user->role,
                'terminal_id' => $user->terminal_id,
                'terminal_name' => $user->terminal?->name,
                'region_code' => $user->region_code,
                'status' => $user->status,
                'last_login' => $user->last_login,
                'created_at' => $user->created_at,
                'is_primary_admin' => $user->username === 'admin',
                'transporter_ids' => DB::table('transporter_user')->where('user_id', $user->id)->pluck('transporter_id')->map(fn ($id) => (int) $id)->all(),
            ]);

        return $this->ok('OK', $users);
    }

    public function createUser(Request $r): JsonResponse
    {
        $this->admin($r);
        $r->merge(['email' => strtolower(trim((string) $r->email))]);
        $v = Validator::make($r->all(), [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(['hsse', 'pengurus', 'manager_hsse'])],
            'password' => ['required', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()],
            'transporter_ids' => ['nullable', 'array'],
            'transporter_ids.*' => ['integer', Rule::exists('transporters', 'id')->where(fn ($q) => $q->where('terminal_id', $r->user()->terminal_id)->where('is_active', true))],
        ], [
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.mixed' => 'Password harus mengandung huruf besar dan huruf kecil.',
            'password.numbers' => 'Password harus mengandung minimal satu angka.',
            'password.symbols' => 'Password harus mengandung minimal satu simbol.',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }
        if ($r->role === 'pengurus' && count($r->input('transporter_ids', [])) < 1) {
            return $this->fail('Pengurus wajib dikaitkan dengan minimal satu transportir aktif.', 422);
        }
        if (DB::table('user_registrations')->whereRaw('LOWER(email) = ?', [$r->email])->where('status', 'pending')->exists()) {
            return $this->fail('Email memiliki pendaftaran yang masih menunggu review. Tinjau melalui menu Review Pendaftaran.', 409);
        }

        $terminalId = $r->user()->terminal_id;
        if (! $terminalId) {
            return $this->fail('Integrated Terminal wajib dipilih untuk role operasional.', 422);
        }
        $user = User::create([
            'username' => 'user_'.substr(hash('sha256', $r->email), 0, 32),
            'full_name' => $r->full_name,
            'email' => $r->email,
            'phone' => $r->phone,
            'department' => $r->department,
            'position' => $r->position,
            'role' => $r->role,
            'terminal_id' => $terminalId,
            'region_code' => $r->user()->region_code ?: 'SULAWESI',
            'status' => 'active',
            'password' => $r->password,
        ]);
        $this->audit($r, 'USER_CREATE', "Akun {$user->email} dibuat dengan role {$user->role}");
        if ($user->role === 'pengurus') {
            foreach (array_unique($r->input('transporter_ids', [])) as $transporterId) {
                DB::table('transporter_user')->insert([
                    'transporter_id' => $transporterId,
                    'user_id' => $user->id,
                    'approved_by' => $r->user()->id,
                    'approved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->ok('Akun berhasil dibuat dan langsung aktif.', [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
        ], 201);
    }

    public function updateUser(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $r->merge(['email' => strtolower(trim((string) $r->email))]);
        $allowedRoles = $id === $r->user()->id
            ? ['admin']
            : ['hsse', 'manager_hsse', 'pengurus'];
        $v = Validator::make($r->all(), ['full_name' => 'required|max:100', 'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($id)], 'phone' => 'nullable|max:20', 'department' => 'nullable|max:100', 'position' => 'nullable|max:100', 'role' => ['required', Rule::in($allowedRoles)], 'transporter_ids' => ['nullable', 'array'], 'transporter_ids.*' => ['integer', Rule::exists('transporters', 'id')->where(fn ($q) => $q->where('terminal_id', $r->user()->terminal_id)->where('is_active', true))]]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }$u = User::find($id);
        if (! $u) {
            return $this->fail('User tidak ditemukan.', 404);
        }if ($u->terminal_id !== $r->user()->terminal_id || $u->role === 'regional_manager' || ($u->role === 'admin' && $u->id !== $r->user()->id)) {
            return $this->fail('Akun berada di luar kewenangan SPV HSSE.', 403);
        }if ($u->username === 'admin' && $r->role !== 'admin') {
            return $this->fail('Role akun admin utama tidak dapat diubah.', 409);
        }
        if ($r->role === 'pengurus' && count($r->input('transporter_ids', [])) < 1) {
            return $this->fail('Pengurus wajib dikaitkan dengan minimal satu transportir aktif.', 422);
        }
        $u->fill($r->only('full_name', 'email', 'phone', 'department', 'position', 'role'))->fill(['email' => strtolower(trim((string) $r->email)), 'terminal_id' => $r->user()->terminal_id])->save();
        DB::table('transporter_user')->where('user_id', $u->id)->delete();
        if ($u->role === 'pengurus') {
            foreach (array_unique($r->input('transporter_ids', [])) as $transporterId) {
                DB::table('transporter_user')->insert(['transporter_id' => $transporterId, 'user_id' => $u->id, 'approved_by' => $r->user()->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $this->audit($r, 'USER_UPDATE', "Akun {$u->username} diperbarui");

        return $this->ok('Data pengguna berhasil diperbarui.');
    }

    public function toggleUser(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $u = User::find($id);
        if (! $u) {
            return $this->fail('User tidak ditemukan.', 404);
        }if ($u->terminal_id !== $r->user()->terminal_id || in_array($u->role, ['admin', 'regional_manager'], true)) {
            return $this->fail('Akun berada di luar kewenangan SPV HSSE.', 403);
        }if ($u->id === $r->user()->id) {
            return $this->fail('Tidak dapat menonaktifkan akun sendiri.', 409);
        }$u->status = $r->input('status') === 'active' ? 'active' : 'inactive';
        $u->save();
        if ($u->status === 'inactive') {
            $u->tokens()->delete();
        }$this->audit($r, 'USER_STATUS', "Status {$u->username}: {$u->status}");

        return $this->ok('Status pengguna berhasil diperbarui.');
    }

    public function deleteUser(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $user = User::find($id);

        if (! $user) {
            return $this->fail('User tidak ditemukan.', 404);
        }
        if ($user->id === $r->user()->id) {
            return $this->fail('Akun yang sedang digunakan tidak dapat dihapus.', 409);
        }
        if ($user->username === 'admin') {
            return $this->fail('Akun admin utama tidak dapat dihapus.', 409);
        }
        if ($user->terminal_id !== $r->user()->terminal_id || in_array($user->role, ['admin', 'regional_manager'], true)) {
            return $this->fail('Akun berada di luar kewenangan SPV HSSE.', 403);
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return $this->fail('Administrator terakhir tidak dapat dihapus.', 409);
        }

        $username = $user->username;
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
        $this->audit($r, 'USER_DELETE', "Akun {$username} dihapus permanen");

        return $this->ok('Pengguna berhasil dihapus.');
    }

    public function resetPassword(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $v = Validator::make($r->all(), [
            'new_password' => ['required', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'new_password.min' => 'Password minimal 8 karakter.',
            'new_password.mixed' => 'Password harus mengandung huruf besar dan huruf kecil.',
            'new_password.numbers' => 'Password harus mengandung minimal satu angka.',
            'new_password.symbols' => 'Password harus mengandung minimal satu simbol.',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first());
        }$u = User::find($id);
        if (! $u) {
            return $this->fail('User tidak ditemukan.', 404);
        }if ($u->terminal_id !== $r->user()->terminal_id || in_array($u->role, ['admin', 'regional_manager'], true)) {
            return $this->fail('Akun berada di luar kewenangan SPV HSSE.', 403);
        }$u->password = Hash::make($r->new_password);
        $u->save();
        $u->tokens()->delete();
        $this->audit($r, 'PASSWORD_RESET', "Password {$u->username} direset");

        return $this->ok('Password berhasil direset.');
    }

    public function masterTransporters(Request $r): JsonResponse
    {
        $this->admin($r);
        $rows = DB::table('transporters as t')
            ->where('t.terminal_id', $r->user()->terminal_id)
            ->select('t.*')
            ->selectSub(fn ($q) => $q->from('transporter_user as tu')->whereColumn('tu.transporter_id', 't.id')->selectRaw('COUNT(*)'), 'users_count')
            ->selectSub(fn ($q) => $q->from('kendaraan as k')->whereColumn('k.transporter_id', 't.id')->selectRaw('COUNT(*)'), 'vehicles_count')
            ->orderBy('t.name')
            ->get();

        return $this->ok('OK', $rows);
    }

    public function saveTransporter(Request $r, ?int $id = null): JsonResponse
    {
        $this->admin($r);
        $validated = $r->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('transporters', 'code')->where('terminal_id', $r->user()->terminal_id)->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $values = $validated + ['terminal_id' => $r->user()->terminal_id, 'updated_at' => now()];
        if ($id) {
            $updated = DB::table('transporters')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->update($values);
            if (! $updated && ! DB::table('transporters')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->exists()) {
                return $this->fail('Transportir tidak ditemukan.', 404);
            }
            $transporterId = $id;
            $action = 'TRANSPORTER_UPDATE';
        } else {
            $values += ['is_active' => true, 'created_by' => $r->user()->id, 'created_at' => now()];
            $transporterId = DB::table('transporters')->insertGetId($values);
            $action = 'TRANSPORTER_CREATE';
        }
        $this->audit($r, $action, "Master transportir {$validated['name']} disimpan");

        return $this->ok('Data transportir berhasil disimpan.', ['id' => $transporterId]);
    }

    public function toggleTransporter(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $transporter = DB::table('transporters')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->first();
        if (! $transporter) {
            return $this->fail('Transportir tidak ditemukan.', 404);
        }
        $active = $r->boolean('is_active');
        DB::table('transporters')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
        $this->audit($r, 'TRANSPORTER_STATUS', "Status transportir {$transporter->name}: ".($active ? 'aktif' : 'nonaktif'));

        return $this->ok('Status transportir berhasil diperbarui.');
    }

    public function registrations(Request $r): JsonResponse
    {
        $this->admin($r);

        return $this->ok('OK', DB::table('user_registrations')->where('terminal_id', $r->user()->terminal_id)->select('id', 'email', 'full_name', 'phone', 'reason', 'requested_role', 'terminal_id', 'transporter_id', 'status', 'review_note', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'created_at', 'updated_at')->latest()->get());
    }

    public function reviewRegistration(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $validated = $r->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $reg = DB::table('user_registrations')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->first();
        if (! $reg) {
            return $this->fail('Pendaftaran tidak ditemukan.', 404);
        }if ($reg->status !== 'pending') {
            return $this->fail('Pendaftaran sudah ditinjau.', 409);
        }if ($r->input('action') === 'approve') {
            if ($reg->requested_role === 'pengurus' && ! $reg->transporter_id) {
                return $this->fail('Pilih transportir yang disetujui sebelum mengaktifkan akun pengurus.', 422);
            }
            DB::transaction(function () use ($reg, $r) {
                $user = User::create(['username' => $reg->username, 'password' => $reg->password, 'full_name' => $reg->full_name, 'email' => $reg->email, 'phone' => $reg->phone, 'role' => $reg->requested_role ?: 'pengurus', 'terminal_id' => $reg->terminal_id ?: $r->user()->terminal_id, 'region_code' => $r->user()->region_code ?: 'SULAWESI', 'status' => 'active']);
                if ($user->role === 'pengurus' && $reg->transporter_id) {
                    DB::table('transporter_user')->insert(['transporter_id' => $reg->transporter_id, 'user_id' => $user->id, 'approved_by' => $r->user()->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('user_registrations')->where('id', $reg->id)->update(['status' => 'approved', 'review_note' => $r->input('reason'), 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            });
            $message = 'Pendaftaran berhasil disetujui.';
        } elseif ($r->input('action') === 'reject') {
            DB::table('user_registrations')->where('id', $id)->update(['status' => 'rejected', 'rejection_reason' => $r->input('reason'), 'review_note' => $r->input('reason'), 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            $message = 'Pendaftaran ditolak.';
        } else {
            return $this->fail('Aksi tidak valid.');
        }$this->audit($r, 'REGISTRATION_REVIEW', "Pendaftaran {$reg->email}: {$r->input('action')}");

        return $this->ok($message);
    }

    public function documents(Request $r): JsonResponse
    {
        $q = DB::table('dokumen_kendaraan as d')->leftJoin('users as u', 'u.id', '=', 'd.uploaded_by')->select('d.*', 'u.full_name as uploader_name');
        if ($r->user()->role === 'pengurus') {
            $q->where('d.uploaded_by', $r->user()->id)
                ->whereExists(fn ($scope) => $scope->from('transporter_user as tu')->whereColumn('tu.transporter_id', 'd.transporter_id')->where('tu.user_id', $r->user()->id));
        } elseif ($r->user()->role === 'admin') {
            $q->where('d.terminal_id', $r->user()->terminal_id);
        } else {
            return $this->fail('Akses ditolak.', 403);
        }

        return $this->ok('OK', $q->latest('d.created_at')->limit(250)->get());
    }

    public function uploadDocument(Request $r): JsonResponse
    {
        if ($r->user()->role !== 'pengurus') {
            return $this->fail('Hanya pengurus yang dapat mengunggah dokumen.', 403);
        }$v = Validator::make($r->all(), ['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png', 'jenis_dokumen' => ['required', Rule::in(['STNK', 'PAJAK', 'SIM', 'SURAT_KEUR', 'SURAT_TERA', 'KIM', 'LAINNYA'])], 'nomor_polisi' => 'required|max:20', 'tanggal_berlaku' => 'nullable|date|after_or_equal:today']);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }
        $originalName = $r->file('file')->getClientOriginalName();
        if (mb_strlen($originalName) > 180 || str_contains($originalName, '/') || str_contains($originalName, '\\') || preg_match('/[\x00-\x1F]/u', $originalName)) {
            return $this->fail('Nama file tidak valid atau terlalu panjang.', 422);
        }
        $plate = strtoupper(trim($r->nomor_polisi));
        $vehicle = DB::table('kendaraan')->where('nomor_polisi', $plate)->where('terminal_id', $r->user()->terminal_id)->first();
        $ownsVehicle = $vehicle
            && DB::table('pengurus_kendaraan')->where(['user_id' => $r->user()->id, 'nomor_polisi' => $plate])->exists()
            && DB::table('transporter_user')->where(['user_id' => $r->user()->id, 'transporter_id' => $vehicle->transporter_id])->exists();
        if (! $ownsVehicle) {
            return $this->fail('Kendaraan bukan tanggung jawab Anda.', 403);
        }if (in_array($r->jenis_dokumen, ['STNK', 'PAJAK', 'SIM', 'SURAT_KEUR', 'SURAT_TERA'], true) && ! $r->tanggal_berlaku) {
            return $this->fail('Tanggal berlaku dokumen wajib diisi.');
        }$path = $r->file('file')->store('dokumen', 'private');
        $terminalId = $vehicle?->terminal_id ?: $r->user()->terminal_id;
        DB::table('dokumen_kendaraan')->insert(['terminal_id' => $terminalId, 'vehicle_id' => $vehicle?->id, 'transporter_id' => $vehicle?->transporter_id, 'nomor_polisi' => $plate, 'nama_transport' => $vehicle?->nama_transport ?: $r->nama_transport, 'jenis_dokumen' => $r->jenis_dokumen, 'nama_file_asli' => $originalName, 'file_path' => $path, 'tanggal_berlaku' => $r->tanggal_berlaku, 'keterangan' => $r->keterangan, 'uploaded_by' => $r->user()->id, 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($r, 'DOCUMENT_UPLOAD', "Dokumen {$r->jenis_dokumen} untuk {$plate} diunggah");

        return $this->ok('Dokumen berhasil diunggah dan menunggu verifikasi.');
    }

    public function deleteDocument(Request $r, int $id): JsonResponse
    {
        if ($r->user()->role !== 'pengurus') {
            return $this->fail('Akses ditolak.', 403);
        }$d = DB::table('dokumen_kendaraan')->where(['id' => $id, 'uploaded_by' => $r->user()->id, 'status' => 'PENDING'])->first();
        if (! $d || ! DB::table('transporter_user')->where(['user_id' => $r->user()->id, 'transporter_id' => $d->transporter_id])->exists()) {
            return $this->fail('Dokumen tidak ditemukan atau tidak dapat dihapus.', 404);
        }Storage::disk('private')->delete($d->file_path);
        DB::table('dokumen_kendaraan')->where('id', $id)->delete();
        $this->audit($r, 'DOCUMENT_DELETE', "Dokumen {$d->jenis_dokumen} untuk {$d->nomor_polisi} dihapus");

        return $this->ok('Dokumen berhasil dihapus.');
    }

    public function reviewDocument(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $d = DB::table('dokumen_kendaraan')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->first();
        if (! $d) {
            return $this->fail('Dokumen tidak ditemukan.', 404);
        }
        if (blank($r->input('catatan_admin', $r->input('reason')))) {
            return $this->fail('Catatan pemeriksaan dokumen wajib diisi.', 422);
        }
        $status = $r->input('action') === 'approve' ? 'DISETUJUI' : ($r->input('action') === 'reject' ? 'DITOLAK' : null);
        if (! $status) {
            return $this->fail('Aksi tidak valid.');
        }
        $updatedDraftItems = DB::transaction(function () use ($d, $id, $r, $status): int {
            DB::table('dokumen_kendaraan')->where('id', $id)->update(['status' => $status, 'catatan_admin' => $r->input('catatan_admin', $r->input('reason')), 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);

            if ($status !== 'DISETUJUI' || empty($d->tanggal_berlaku) || ! isset(self::DOCUMENT_CHECKLIST_ITEMS[$d->jenis_dokumen])) {
                return 0;
            }

            $activeExpiry = DB::table('dokumen_kendaraan')
                ->where('nomor_polisi', $d->nomor_polisi)
                ->where('jenis_dokumen', $d->jenis_dokumen)
                ->where('status', 'DISETUJUI')
                ->whereNotNull('tanggal_berlaku')
                ->orderByDesc('tanggal_berlaku')
                ->orderByDesc('reviewed_at')
                ->value('tanggal_berlaku');

            $editableIds = DB::table('formulir_checklist')
                ->where('nomor_polisi', $d->nomor_polisi)
                ->whereIn('status_approval', ['draft', 'pending_hsse'])
                ->pluck('id');

            if ($editableIds->isEmpty()) {
                return 0;
            }

            return DB::table('checklist_items')
                ->whereIn('formulir_id', $editableIds)
                ->where('item_name', self::DOCUMENT_CHECKLIST_ITEMS[$d->jenis_dokumen])
                ->update(['tanggal_expire' => $activeExpiry]);
        });
        $this->audit($r, 'DOCUMENT_REVIEW', "Dokumen {$d->jenis_dokumen} {$d->nomor_polisi} menjadi $status; $updatedDraftItems item checklist draft diperbarui");

        $message = $status === 'DISETUJUI' && isset(self::DOCUMENT_CHECKLIST_ITEMS[$d->jenis_dokumen])
            ? 'Dokumen disetujui. Masa berlaku surat kendaraan dan checklist yang belum ditandatangani telah diperbarui otomatis.'
            : 'Status dokumen berhasil diperbarui.';

        return $this->ok($message, ['updated_draft_items' => $updatedDraftItems]);
    }

    public function downloadDocument(Request $r, int $id)
    {
        $d = DB::table('dokumen_kendaraan')->where('id', $id)->first();
        if (! $d) {
            abort(404);
        }if ($r->user()->role === 'admin' && $d->terminal_id !== $r->user()->terminal_id) {
            abort(403);
        }if ($r->user()->role !== 'admin' && ($d->uploaded_by !== $r->user()->id || ! DB::table('transporter_user')->where(['user_id' => $r->user()->id, 'transporter_id' => $d->transporter_id])->exists())) {
            abort(403);
        }if (! Storage::disk('private')->exists($d->file_path)) {
            abort(404);
        }

        return Storage::disk('private')->download($d->file_path, $d->nama_file_asli);
    }

    public function audits(Request $r): JsonResponse
    {
        $this->admin($r);
        $s = trim($r->query('search', ''));
        $q = DB::table('audit_log as a')->leftJoin('formulir_checklist as f', 'f.id', '=', 'a.formulir_id')->where('a.terminal_id', $r->user()->terminal_id)->select('a.*', 'f.nomor_polisi');
        if ($s) {
            $q->where(fn ($x) => $x->where('a.user_name', 'ilike', "%$s%")->orWhere('a.action', 'ilike', "%$s%")->orWhere('a.description', 'ilike', "%$s%"));
        }

        return $this->ok('OK', $q->latest('a.created_at')->limit(250)->get());
    }

    public function alerts(Request $r): JsonResponse
    {
        $this->admin($r);
        $limit = today()->addDays(30)->toDateString();
        $rows = DB::table('formulir_checklist as f')->leftJoin('kendaraan as k', 'k.nomor_polisi', '=', 'f.nomor_polisi')->where('f.terminal_id', $r->user()->terminal_id)->whereIn('f.status_approval', ['approved', 'expired'])->whereNotNull('f.ekim_valid_until')->where('f.ekim_valid_until', '<=', $limit)->whereRaw("f.id=(SELECT MAX(f2.id) FROM formulir_checklist f2 WHERE f2.nomor_polisi=f.nomor_polisi AND f2.status_approval IN ('approved','expired'))")->select('f.id', 'f.nomor_polisi', 'f.merk_mobil', 'f.nama_transport', 'k.email_transportir', 'f.jenis_kendaraan as jenis', 'f.ekim_valid_until')->get()->map(function ($x) {
            $x->effective_valid_until = $x->ekim_valid_until;
            $x->hari_tersisa = today()->diffInDays($x->ekim_valid_until, false);
            $x->status_alert = $x->hari_tersisa < 0 ? 'SUDAH_EXPIRED' : 'PERLU_INSPEKSI';

            return $x;
        });

        return $this->ok('OK', $rows);
    }

    public function settings(Request $r): JsonResponse
    {
        $this->admin($r);
        $tables = $this->tableMetrics();
        $backups = collect(Storage::disk('local')->files('backups'))->filter(fn ($x) => str_ends_with($x, '.dump') || str_ends_with($x, '.json.gz'))->map(fn ($x) => ['filename' => basename($x), 'size' => $this->bytes(Storage::disk('local')->size($x)), 'created_at' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($x)), 'checksum' => Storage::disk('local')->exists($x.'.sha256') ? trim(Storage::disk('local')->get($x.'.sha256')) : null, 'status' => 'verified'])->sortByDesc('created_at')->values()->take(10);
        $last = $this->setting('cron_last_run');

        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $smtpUsername = $this->setting('smtp_username');
        $smtpReady = filled($this->setting('smtp_host'))
            && filled($this->setting('smtp_port'))
            && filled($this->setting('smtp_from_email'))
            && (blank($smtpUsername) || filled($this->setting('smtp_password')));

        $driver = DB::connection()->getDriverName();

        return $this->ok('OK', ['php_version' => PHP_VERSION, 'database' => DB::connection()->getDatabaseName(), 'timezone' => config('app.timezone'), 'upload_max' => ini_get('upload_max_filesize'), 'memory_limit' => ini_get('memory_limit'), 'app_version' => config('app.version'), 'tables' => $tables, 'health' => ['database' => ['ok' => true, 'label' => 'Terhubung '.strtoupper($driver)], 'uploads' => ['ok' => true, 'label' => 'Private storage aktif'], 'backup_storage' => ['ok' => is_writable(storage_path('app/private')), 'label' => 'Siap digunakan'], 'smtp' => ['ok' => $smtpReady, 'label' => $smtpReady ? 'Terkonfigurasi' : 'Belum lengkap'], 'scheduler' => ['ok' => $last && strtotime($last) >= strtotime('-36 hours'), 'label' => $last ? 'Aktif' : 'Belum pernah berjalan']], 'storage' => ['free_bytes' => $free, 'total_bytes' => $total, 'free_label' => $this->bytes($free), 'total_label' => $this->bytes($total), 'upload_path' => 'private/dokumen', 'backup_path' => 'private/backups'], 'scheduler' => ['last_run' => $last, 'last_status' => $this->setting('cron_last_status'), 'healthy' => $last && strtotime($last) >= strtotime('-36 hours')], 'backup' => ['last_at' => $this->setting('last_backup_at'), 'last_status' => $this->setting('last_backup_status', 'never'), 'last_error' => $this->setting('last_backup_error'), 'items' => $backups], 'process_runs' => DB::table('process_runs')->latest('started_at')->limit(25)->get(), 'restore_requests' => DB::table('restore_requests')->where('terminal_id', $r->user()->terminal_id)->latest('requested_at')->limit(25)->get(), 'policies' => ['maintenance_mode' => $this->setting('maintenance_mode', '0') === '1', 'maintenance_message' => $this->setting('maintenance_message', 'Sistem sedang dalam pemeliharaan. Silakan coba kembali nanti.'), 'session_timeout_minutes' => (int) $this->setting('session_timeout_minutes', 60), 'audit_retention_days' => (int) $this->setting('audit_retention_days', 365), 'backup_retention_days' => (int) $this->setting('backup_retention_days', 30)]]);
    }

    private function tableMetrics(): array
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return collect(DB::select('SELECT relname AS name, n_live_tup AS row_count, pg_total_relation_size(relid) AS size_bytes FROM pg_stat_user_tables ORDER BY pg_total_relation_size(relid) DESC'))
                ->map(fn ($table) => ['name' => $table->name, 'row_count' => (int) $table->row_count, 'size_mb' => round($table->size_bytes / 1048576, 2)])
                ->all();
        }

        return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
            ->map(fn ($table) => [
                'name' => $table->name,
                'row_count' => Schema::hasTable($table->name) ? DB::table($table->name)->count() : 0,
                'size_mb' => 0.0,
            ])->all();
    }

    private function bytes(float|int|false $n): string
    {
        if ($n === false) {
            return 'Tidak tersedia';
        }$u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $p = $n ? min((int) floor(log($n, 1024)), 4) : 0;

        return number_format($n / (1024 ** $p), $p > 1 ? 2 : 0, ',', '.').' '.$u[$p];
    }

    public function saveSettings(Request $r): JsonResponse
    {
        $this->admin($r);
        $values = ['session_timeout_minutes' => max(15, min(480, $r->integer('session_timeout_minutes', 60))), 'audit_retention_days' => max(90, min(3650, $r->integer('audit_retention_days', 365))), 'backup_retention_days' => max(7, min(365, $r->integer('backup_retention_days', 30))), 'maintenance_mode' => $r->boolean('maintenance_mode') ? '1' : '0', 'maintenance_message' => mb_substr(trim($r->input('maintenance_message')) ?: 'Sistem sedang dalam pemeliharaan.', 0, 250)];
        foreach ($values as $k => $v) {
            $this->setSetting($k, $v);
        }$this->audit($r, 'SYSTEM_SETTINGS', 'Pengaturan sistem diperbarui');

        return $this->ok('Pengaturan sistem berhasil disimpan.');
    }

    public function retention(Request $r, RetentionService $retention): JsonResponse
    {
        $this->admin($r);
        $result = $retention->apply();
        $this->audit($r, 'RETENTION', "{$result['audit_deleted']} audit dan {$result['backups_deleted']} backup lama dihapus");

        return $this->ok("Retensi diterapkan. {$result['audit_deleted']} audit dan {$result['backups_deleted']} backup lama dihapus.", $result);
    }

    public function backup(Request $r, DatabaseBackupService $backups, ProcessRunService $runs): JsonResponse
    {
        $this->admin($r);
        $runId = $runs->start('database_backup', ['trigger' => 'manual', 'user_id' => $r->user()->id]);
        try {
            $result = $backups->create();
            $this->setSetting('last_backup_at', now()->toDateTimeString());
            $this->setSetting('last_backup_status', 'success');
            $this->setSetting('last_backup_error', '');
            $runs->finish($runId, "Backup {$result['filename']} berhasil", $result);
            $this->audit($r, 'BACKUP', "Backup {$result['filename']} dibuat");

            return $this->ok('Backup database berhasil dibuat.', [
                'filename' => $result['filename'],
                'size' => $this->bytes($result['size_bytes']),
                'checksum' => $result['checksum'],
                'format' => $result['format'],
            ]);
        } catch (\Throwable $e) {
            $this->setSetting('last_backup_at', now()->toDateTimeString());
            $this->setSetting('last_backup_status', 'failed');
            $this->setSetting('last_backup_error', $e->getMessage());
            $runs->fail($runId, $e->getMessage(), 'Backup manual gagal');
            $this->audit($r, 'BACKUP_FAILED', 'Backup gagal: '.$e->getMessage());

            return $this->fail('Backup database gagal: '.$e->getMessage(), 503);
        }
    }

    public function restoreRequests(Request $r): JsonResponse
    {
        if (! in_array($r->user()->role, ['admin', 'manager_hsse'], true)) {
            return $this->fail('Akses ditolak.', 403);
        }
        $rows = DB::table('restore_requests as rr')
            ->leftJoin('users as requester', 'requester.id', '=', 'rr.requested_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'rr.approved_by')
            ->leftJoin('users as executor', 'executor.id', '=', 'rr.executed_by')
            ->where('rr.terminal_id', $r->user()->terminal_id)
            ->select('rr.*', 'requester.full_name as requester_name', 'approver.full_name as approver_name', 'executor.full_name as executor_name')
            ->latest('rr.requested_at')
            ->get();

        return $this->ok('OK', $rows);
    }

    public function requestRestore(Request $r, BackupFilePolicy $policy): JsonResponse
    {
        $this->admin($r);
        $validated = $r->validate([
            'filename' => ['required', 'string', 'max:255'],
            'request_notes' => ['required', 'string', 'max:2000'],
        ]);
        if (! $policy->validRestoreFilename($validated['filename'])) {
            return $this->fail('Nama file backup tidak valid.', 422);
        }
        $disk = Storage::disk('local');
        $relative = 'backups/'.$validated['filename'];
        if (! $disk->exists($relative) || ! $disk->exists($relative.'.sha256')) {
            return $this->fail('File backup atau checksum tidak ditemukan.', 404);
        }
        $checksum = trim($disk->get($relative.'.sha256'));
        if (! $policy->checksumMatches($checksum, hash_file('sha256', $disk->path($relative)))) {
            return $this->fail('Checksum backup tidak cocok.', 422);
        }
        $id = DB::table('restore_requests')->insertGetId([
            'terminal_id' => $r->user()->terminal_id,
            'filename' => $validated['filename'],
            'checksum' => $checksum,
            'status' => 'pending',
            'request_notes' => $validated['request_notes'],
            'requested_by' => $r->user()->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($r, 'RESTORE_REQUEST', "Permohonan restore #{$id} untuk {$validated['filename']} diajukan");

        return $this->ok('Permohonan restore menunggu persetujuan Integrated Terminal Manager.', ['id' => $id], 201);
    }

    public function decideRestore(Request $r, int $id): JsonResponse
    {
        if ($r->user()->role !== 'manager_hsse') {
            return $this->fail('Hanya Integrated Terminal Manager yang dapat memberikan keputusan.', 403);
        }
        $validated = $r->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'approval_notes' => ['required', 'string', 'max:2000'],
        ]);
        $request = DB::table('restore_requests')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->first();
        if (! $request) {
            return $this->fail('Permohonan restore tidak ditemukan.', 404);
        }
        if ($request->status !== 'pending') {
            return $this->fail('Permohonan restore sudah diputuskan.', 409);
        }
        $status = $validated['decision'] === 'approve' ? 'approved' : 'rejected';
        DB::table('restore_requests')->where('id', $id)->update([
            'status' => $status,
            'approved_by' => $r->user()->id,
            'approved_at' => now(),
            'approval_notes' => $validated['approval_notes'],
            'updated_at' => now(),
        ]);
        $this->audit($r, 'RESTORE_DECISION', "Permohonan restore #{$id}: {$status}");

        return $this->ok('Keputusan restore berhasil disimpan.', ['status' => $status]);
    }

    public function executeRestore(Request $r, int $id, BackupFilePolicy $policy, ProcessRunService $runs): JsonResponse
    {
        $this->admin($r);
        $validated = $r->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($validated['current_password'], $r->user()->password)) {
            return $this->fail('Autentikasi ulang gagal.', 422);
        }
        $request = DB::table('restore_requests')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->first();
        if (! $request) {
            return $this->fail('Permohonan restore tidak ditemukan.', 404);
        }
        if ($request->status !== 'approved' || ! $request->approved_by) {
            return $this->fail('Restore belum memperoleh persetujuan Integrated Terminal Manager.', 409);
        }
        $disk = Storage::disk('local');
        $relative = 'backups/'.$request->filename;
        if (! $disk->exists($relative) || ! $policy->checksumMatches($request->checksum, hash_file('sha256', $disk->path($relative)))) {
            return $this->fail('File backup berubah atau tidak valid.', 422);
        }
        $runId = $runs->start('database_restore', ['request_id' => $id, 'filename' => $request->filename]);
        $exitCode = Artisan::call('prima:database-restore', ['filename' => $request->filename, '--force' => true]);
        $output = trim(Artisan::output());
        if ($exitCode !== 0) {
            DB::table('restore_requests')->where('id', $id)->update(['status' => 'failed', 'executed_by' => $r->user()->id, 'executed_at' => now(), 'error_message' => $output, 'updated_at' => now()]);
            $runs->fail($runId, $output ?: 'Restore gagal');
            $this->audit($r, 'RESTORE_FAILED', "Restore #{$id} gagal: {$output}");

            return $this->fail($output ?: 'Restore database gagal.', 503);
        }
        DB::table('restore_requests')->where('id', $id)->update(['status' => 'completed', 'executed_by' => $r->user()->id, 'executed_at' => now(), 'result_message' => $output, 'updated_at' => now()]);
        $runs->finish($runId, $output ?: 'Restore berhasil');
        $this->audit($r, 'RESTORE_COMPLETED', "Restore #{$id} berhasil dijalankan");

        return $this->ok('Restore database berhasil dijalankan.');
    }

    public function notifications(Request $r): JsonResponse
    {
        $this->admin($r);
        $keys = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_from_email', 'smtp_from_name', 'notif_days_threshold'];
        $cfg = [];
        foreach ($keys as $k) {
            $cfg[$k] = $this->setting($k, ['smtp_host' => 'smtp.gmail.com', 'smtp_port' => '587', 'smtp_encryption' => 'tls', 'notif_days_threshold' => '30'][$k] ?? '');
        }

        $credentialsReady = blank($cfg['smtp_username'] ?? null) || filled($this->setting('smtp_password'));
        $smtpReady = collect(['smtp_host', 'smtp_port', 'smtp_from_email'])->every(fn ($key) => filled($cfg[$key] ?? null)) && $credentialsReady;

        return $this->ok('OK', ['config' => $cfg, 'smtp_ready' => $smtpReady, 'history' => DB::table('kim_notifications as n')->leftJoin('users as u', 'u.id', '=', 'n.sent_by')->select('n.*', 'u.full_name as sender_name')->latest('n.sent_at')->limit(100)->get(), 'cron_url' => url('/api/v1/scheduler/run/'.$this->cronSecret()), 'cron_last_run' => $this->setting('cron_last_run'), 'cron_last_status' => $this->setting('cron_last_status')]);
    }

    public function saveNotifications(Request $r): JsonResponse
    {
        $this->admin($r);
        $validated = $r->validate([
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_from_email' => ['required', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1000'],
            'notif_days_threshold' => ['required', 'integer', 'between:0,365'],
        ]);
        $username = trim((string) ($validated['smtp_username'] ?? ''));
        if ($username !== '' && blank($validated['smtp_password'] ?? null) && blank($this->setting('smtp_password'))) {
            return $this->fail('Password SMTP wajib diisi jika akun SMTP digunakan.');
        }
        foreach (['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_from_email', 'smtp_from_name', 'notif_days_threshold'] as $k) {
            $this->setSetting($k, $validated[$k] ?? '');
        }if (filled($validated['smtp_password'] ?? null)) {
            $this->setSetting('smtp_password', encrypt($validated['smtp_password']));
        } elseif ($username === '') {
            DB::table('system_settings')->where('setting_key', 'smtp_password')->delete();
        }
        $this->audit($r, 'NOTIFICATION_SETTINGS', 'Konfigurasi SMTP dan ambang notifikasi diperbarui');

        return $this->ok('Konfigurasi notifikasi berhasil disimpan.');
    }

    private function cronSecret(): string
    {
        $x = $this->setting('cron_secret');
        if (! $x) {
            $x = bin2hex(random_bytes(24));
            $this->setSetting('cron_secret', $x);
        }

        return $x;
    }

    public function regenerateCron(Request $r): JsonResponse
    {
        $this->admin($r);
        $this->setSetting('cron_secret', bin2hex(random_bytes(24)));
        $this->audit($r, 'CRON_SECRET_REGENERATE', 'Kunci scheduler notifikasi dibuat ulang');

        return $this->ok('Kunci cron berhasil dibuat ulang.');
    }

    public function runScheduler(Request $r, string $secret): JsonResponse
    {
        $stored = (string) $this->setting('cron_secret', '');
        if ($stored === '' || ! hash_equals($stored, $secret)) {
            return $this->fail('Kunci scheduler tidak valid.', 403);
        }$exitCode = Artisan::call('prima:notifications');

        if ($exitCode !== 0) {
            return $this->fail(trim(Artisan::output()) ?: 'Scheduler notifikasi gagal dijalankan.', 503);
        }

        return $this->ok('Scheduler notifikasi selesai dijalankan.');
    }

    public function myVehicles(Request $r): JsonResponse
    {
        if ($r->user()->role !== 'pengurus') {
            return $this->fail('Akses ditolak.', 403);
        }
        $uid = $r->user()->id;
        $rows = DB::table('pengurus_kendaraan as p')
            ->join('kendaraan as k', 'k.nomor_polisi', '=', 'p.nomor_polisi')
            ->leftJoin('dokumen_kendaraan as d', fn ($j) => $j->on('d.nomor_polisi', '=', 'p.nomor_polisi')->where('d.uploaded_by', $uid))
            ->where('p.user_id', $uid)
            ->whereExists(fn ($scope) => $scope->from('transporter_user as tu')->whereColumn('tu.transporter_id', 'k.transporter_id')->where('tu.user_id', $uid))
            ->groupBy('k.id', 'p.nomor_polisi', 'p.nama_transport', 'k.transporter_id', 'k.jenis', 'k.nomor_urut', 'k.merk_mobil', 'k.tahun_kendaraan', 'k.produk', 'k.kapasitas', 'k.approval_status', 'k.status')
            ->select('k.id', 'p.nomor_polisi', 'p.nama_transport', 'k.transporter_id', 'k.jenis', 'k.nomor_urut', 'k.merk_mobil', 'k.tahun_kendaraan', 'k.produk', 'k.kapasitas', 'k.approval_status', 'k.status')
            ->selectRaw("SUM(CASE WHEN d.status='PENDING' THEN 1 ELSE 0 END) pending_dokumen")
            ->selectRaw("SUM(CASE WHEN d.status='DISETUJUI' THEN 1 ELSE 0 END) disetujui_dokumen")
            ->selectRaw("SUM(CASE WHEN d.status='DITOLAK' THEN 1 ELSE 0 END) ditolak_dokumen")
            ->get();

        return $this->ok('OK', $rows);
    }

    public function updateMyVehicle(Request $r, int $id, VehicleDataNormalizer $normalizer, VehicleHistoryService $history): JsonResponse
    {
        if ($r->user()->role !== 'pengurus') {
            return $this->fail('Akses ditolak.', 403);
        }
        $vehicle = DB::table('kendaraan')->where('id', $id)->where('terminal_id', $r->user()->terminal_id)->first();
        if (! $vehicle || ! DB::table('transporter_user')->where('user_id', $r->user()->id)->where('transporter_id', $vehicle->transporter_id)->exists()) {
            return $this->fail('Kendaraan berada di luar kewenangan transportir Anda.', 403);
        }
        $r->merge(['nomor_polisi' => $normalizer->plate($r->input('nomor_polisi'))]);
        $validated = $r->validate([
            'jenis' => ['required', Rule::in(['SPBU', 'INDUSTRI'])],
            'nomor_polisi' => ['required', 'max:20', Rule::unique('kendaraan', 'nomor_polisi')->ignore($id)],
            'nomor_urut' => ['required', 'string', 'max:50'],
            'merk_mobil' => ['required', 'string', 'max:100'],
            'tahun_kendaraan' => ['required', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'produk' => ['required', 'string', 'max:100'],
            'kapasitas' => ['required', 'string', 'max:100'],
        ]);
        $validated['produk_kapasitas'] = $validated['produk'].' / '.$validated['kapasitas'];
        $validated['approval_status'] = 'PENDING';
        $validated['status'] = 'NONAKTIF';
        $validated['updated_at'] = now();
        DB::transaction(function () use ($id, $vehicle, $validated): void {
            DB::table('kendaraan')->where('id', $id)->update($validated);
            if ($vehicle->nomor_polisi !== $validated['nomor_polisi']) {
                DB::table('pengurus_kendaraan')->where('nomor_polisi', $vehicle->nomor_polisi)->update(['nomor_polisi' => $validated['nomor_polisi'], 'updated_at' => now()]);
                DB::table('dokumen_kendaraan')->where('vehicle_id', $id)->update(['nomor_polisi' => $validated['nomor_polisi'], 'updated_at' => now()]);
            }
        });
        $after = DB::table('kendaraan')->where('id', $id)->first();
        $history->record($vehicle, $after, $r->user(), 'UPDATE_REQUEST', 'Perubahan oleh pengurus menunggu persetujuan SPV HSSE', $r->ip());
        $this->audit($r, 'VEHICLE_UPDATE_REQUEST', "Perubahan kendaraan {$after->nomor_polisi} diajukan");

        return $this->ok('Perubahan kendaraan menunggu persetujuan SPV HSSE.');
    }

    public function registerMyVehicle(Request $r, VehicleDataNormalizer $normalizer, VehicleHistoryService $history): JsonResponse
    {
        if ($r->user()->role !== 'pengurus') {
            return $this->fail('Akses ditolak.', 403);
        }$plate = $normalizer->plate($r->input('nomor_polisi'));
        if (! $plate) {
            return $this->fail('Nomor polisi wajib diisi.');
        }
        $vehicle = DB::table('kendaraan')->where('nomor_polisi', $plate)->where('terminal_id', $r->user()->terminal_id)->first();
        $transporterId = $r->integer('transporter_id') ?: (int) ($vehicle?->transporter_id ?? 0);
        $allowedTransporter = DB::table('transporter_user')->where('user_id', $r->user()->id)->where('transporter_id', $transporterId)->exists();
        if (! $allowedTransporter) {
            return $this->fail('Transportir berada di luar kewenangan akun Anda.', 403);
        }
        if (! $vehicle) {
            $validated = Validator::make($r->all(), [
                'nomor_urut' => ['required', 'string', 'max:50'],
                'merk_mobil' => ['required', 'string', 'max:100'],
                'tahun_kendaraan' => ['required', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
                'produk' => ['required', 'string', 'max:100'],
                'kapasitas' => ['required', 'string', 'max:100'],
                'jenis' => ['required', Rule::in(['SPBU', 'INDUSTRI'])],
            ]);
            if ($validated->fails()) {
                return $this->fail($validated->errors()->first(), 422, $validated->errors());
            }
            $transporter = DB::table('transporters')->where('id', $transporterId)->where('terminal_id', $r->user()->terminal_id)->where('is_active', true)->first();
            if (! $transporter) {
                return $this->fail('Transportir tidak aktif atau tidak ditemukan.', 422);
            }
            $vehicleId = DB::table('kendaraan')->insertGetId([
                'terminal_id' => $r->user()->terminal_id,
                'transporter_id' => $transporterId,
                'jenis' => $r->jenis,
                'nomor_polisi' => $plate,
                'nomor_urut' => $r->nomor_urut,
                'merk_mobil' => $r->merk_mobil,
                'tahun_kendaraan' => $r->tahun_kendaraan,
                'produk' => $r->produk,
                'kapasitas' => $r->kapasitas,
                'produk_kapasitas' => trim($r->produk.' / '.$r->kapasitas),
                'nama_transport' => $transporter->name,
                'email_transportir' => $transporter->email ?: $r->user()->email,
                'status' => 'NONAKTIF',
                'approval_status' => 'PENDING',
                'created_by' => $r->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $vehicle = DB::table('kendaraan')->where('id', $vehicleId)->first();
            $history->record(null, $vehicle, $r->user(), 'REGISTER', 'Diajukan pengurus dan menunggu persetujuan SPV HSSE', $r->ip());
            $this->audit($r, 'VEHICLE_REGISTRATION', "Kendaraan {$plate} diajukan untuk persetujuan SPV HSSE");
        }
        $exists = DB::table('pengurus_kendaraan')->where(['user_id' => $r->user()->id, 'nomor_polisi' => $plate])->exists();
        if (! $exists) {
            DB::table('pengurus_kendaraan')->insert(['user_id' => $r->user()->id, 'nomor_polisi' => $plate, 'nama_transport' => $vehicle->nama_transport, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->ok($vehicle->approval_status === 'PENDING' ? 'Pendaftaran kendaraan menunggu persetujuan SPV HSSE.' : ($exists ? 'Kendaraan sudah terdaftar.' : 'Kendaraan berhasil didaftarkan.'));
    }

    public function saveVehicle(Request $r, VehicleDataNormalizer $normalizer, VehicleHistoryService $history): JsonResponse
    {
        $this->admin($r);
        $existing = $r->integer('id')
            ? DB::table('kendaraan')->where('id', $r->integer('id'))->where('terminal_id', $r->user()->terminal_id)->first()
            : null;
        if ($r->integer('id') && ! $existing) {
            return $this->fail('Kendaraan tidak ditemukan pada terminal Anda.', 404);
        }

        if (in_array($r->input('action'), ['approve', 'reject', 'deactivate', 'activate', 'delete'], true)) {
            if (! $existing) {
                return $this->fail('Kendaraan tidak ditemukan pada terminal Anda.', 404);
            }
            $action = $r->input('action');
            if (in_array($action, ['approve', 'reject'], true) && blank($r->input('review_note'))) {
                return $this->fail('Catatan keputusan kendaraan wajib diisi.', 422);
            }
            $updates = match ($action) {
                'approve' => ['approval_status' => 'APPROVED', 'status' => 'AKTIF'],
                'reject' => ['approval_status' => 'REJECTED', 'status' => 'NONAKTIF'],
                'activate' => ['status' => 'AKTIF'],
                default => ['status' => 'NONAKTIF'],
            };
            $updates += ['reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'review_note' => $r->input('review_note'), 'updated_at' => now()];
            DB::table('kendaraan')->where('id', $existing->id)->update($updates);
            $after = DB::table('kendaraan')->where('id', $existing->id)->first();
            $history->record($existing, $after, $r->user(), strtoupper($action), $r->input('review_note'), $r->ip());
            $this->audit($r, 'VEHICLE_'.strtoupper($action), "Kendaraan {$existing->nomor_polisi}: {$action}");

            return $this->ok('Status kendaraan berhasil diperbarui.', ['id' => $existing->id]);
        }

        $r->merge([
            'nomor_polisi' => $normalizer->plate($r->input('nomor_polisi')),
            'email_transportir' => $normalizer->transportirEmail($r->input('email_transportir'), $r->input('email_kontraktor')),
        ]);
        $v = Validator::make($r->all(), [
            'jenis' => ['required', Rule::in(['SPBU', 'INDUSTRI'])],
            'nomor_polisi' => ['required', 'max:20', Rule::unique('kendaraan', 'nomor_polisi')->ignore($existing?->id)],
            'nomor_urut' => 'required|max:50',
            'merk_mobil' => 'required|max:100',
            'tahun_kendaraan' => 'required|integer|min:1980|max:'.(date('Y') + 1),
            'produk' => 'required|max:100',
            'kapasitas' => 'required|max:100',
            'transporter_id' => ['required', 'integer', Rule::exists('transporters', 'id')->where(fn ($q) => $q->where('terminal_id', $r->user()->terminal_id)->where('is_active', true))],
            'email_transportir' => 'required|email|max:100',
            'user_id_transportir' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'pengurus')->where('status', 'active')->where('terminal_id', $r->user()->terminal_id))],
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422, $v->errors());
        }
        if (! DB::table('transporter_user')->where('user_id', $r->integer('user_id_transportir'))->where('transporter_id', $r->integer('transporter_id'))->exists()) {
            return $this->fail('Akun pengurus tidak terkait dengan transportir yang dipilih.', 422);
        }
        $transporter = DB::table('transporters')->where('id', $r->integer('transporter_id'))->first();
        $data = $r->only('jenis', 'nomor_polisi', 'nomor_urut', 'merk_mobil', 'tahun_kendaraan', 'produk', 'kapasitas', 'transporter_id', 'email_transportir', 'status');
        $data['nama_transport'] = $transporter->name;
        $data['produk_kapasitas'] = trim($r->produk.' / '.$r->kapasitas);
        $data['updated_at'] = now();
        if ($existing) {
            DB::table('kendaraan')->where('id', $existing->id)->update($data);
            $id = $existing->id;
        } else {
            $data += ['terminal_id' => $r->user()->terminal_id, 'approval_status' => 'APPROVED', 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'created_by' => $r->user()->id, 'created_at' => now()];
            $id = DB::table('kendaraan')->insertGetId($data);
        }
        DB::table('pengurus_kendaraan')->where('nomor_polisi', $data['nomor_polisi'])->delete();
        DB::table('pengurus_kendaraan')->insert(['user_id' => $r->integer('user_id_transportir'), 'nomor_polisi' => $data['nomor_polisi'], 'nama_transport' => $data['nama_transport'], 'created_at' => now(), 'updated_at' => now()]);
        $after = DB::table('kendaraan')->where('id', $id)->first();
        $history->record($existing, $after, $r->user(), $existing ? 'UPDATE' : 'CREATE', $r->input('review_note'), $r->ip());
        $this->audit($r, $existing ? 'VEHICLE_UPDATE' : 'VEHICLE_CREATE', "Kendaraan {$data['nomor_polisi']} disimpan");

        return $this->ok('Data kendaraan berhasil disimpan.', ['id' => $id]);
    }

    public function vehicleHistory(Request $r, int $id): JsonResponse
    {
        $vehicle = DB::table('kendaraan')->where('id', $id)->first();
        if (! $vehicle) {
            return $this->fail('Kendaraan tidak ditemukan.', 404);
        }
        if ($r->user()->role === 'regional_manager') {
            $allowed = DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->where('id', $vehicle->terminal_id)->exists();
            if (! $allowed) {
                return $this->fail('Akses ditolak.', 403);
            }
        } elseif ((int) $r->user()->terminal_id !== (int) $vehicle->terminal_id) {
            return $this->fail('Akses ditolak.', 403);
        }

        return $this->ok('OK', DB::table('vehicle_histories as h')->leftJoin('users as u', 'u.id', '=', 'h.actor_id')->where('h.vehicle_id', $id)->select('h.*', 'u.full_name as actor_name')->latest('h.created_at')->get());
    }

    public function export(Request $r): BinaryFileResponse
    {
        if (! in_array($r->user()->role, ['admin', 'hsse', 'manager_hsse', 'regional_manager'], true)) {
            throw new HttpException(403, 'Akses ditolak.');
        }
        $query = DB::table('formulir_checklist as f')->leftJoin('integrated_terminals as t', 't.id', '=', 'f.terminal_id');
        if ($r->user()->role === 'regional_manager') {
            $allowedTerminalIds = DB::table('integrated_terminals')->where('region_code', $r->user()->region_code ?: 'SULAWESI')->pluck('id');
            $query->whereIn('f.terminal_id', $allowedTerminalIds);
            if ($r->filled('terminal_id')) {
                if (! $allowedTerminalIds->contains($r->integer('terminal_id'))) {
                    throw new HttpException(403, 'Integrated Terminal berada di luar wilayah kewenangan.');
                }
                $query->where('f.terminal_id', $r->integer('terminal_id'));
            }
        } elseif ($r->user()->terminal_id) {
            $query->where('f.terminal_id', $r->user()->terminal_id);
        }
        if ($search = trim($r->query('search', ''))) {
            $query->where(fn ($x) => $x->where('f.nomor_polisi', 'ilike', "%$search%")->orWhere('f.nama_transport', 'ilike', "%$search%")->orWhere('f.nomor_urut', 'ilike', "%$search%"));
        }
        if ($date = $r->query('dateFrom')) {
            $query->whereDate('f.tanggal_pemeriksaan', '>=', $date);
        }
        if ($date = $r->query('dateTo')) {
            $query->whereDate('f.tanggal_pemeriksaan', '<=', $date);
        }
        if (in_array($r->query('jenis'), ['SPBU', 'INDUSTRI'], true)) {
            $query->where('f.jenis_kendaraan', $r->query('jenis'));
        }
        if (in_array($r->query('status'), ['draft', 'pending_hsse', 'signed_hsse', 'approved', 'rejected'], true)) {
            $query->where('f.status_approval', $r->query('status'));
        }
        $rows = $query->orderByDesc('f.tanggal_pemeriksaan')->get(['f.id', 't.name as terminal_name', 'f.jenis_kendaraan', 'f.nomor_polisi', 'f.nama_transport', 'f.tanggal_pemeriksaan', 'f.ekim_valid_until', 'f.status_approval'])->map(fn ($x) => [$x->id, $x->terminal_name, $x->jenis_kendaraan, $x->nomor_polisi, $x->nama_transport, $x->tanggal_pemeriksaan, $x->ekim_valid_until, $x->status_approval]);
        $path = SimpleXlsx::create(['ID', 'Integrated Terminal', 'Jenis', 'Nomor Polisi', 'Transportir', 'Tanggal Pemeriksaan', 'EKIM Berlaku', 'Status'], $rows);
        $this->audit($r, 'EXPORT', 'Export XLSX data checklist');

        return response()->download($path, 'data-checklist-'.today()->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
