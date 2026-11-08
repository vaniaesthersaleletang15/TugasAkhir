<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ekim\EkimStatusCalculator;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegionalController extends Controller
{
    private function ok(mixed $data = null, string $message = 'OK'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function authorizeRegional(Request $request): void
    {
        if ($request->user()->role !== 'regional_manager') {
            throw new HttpException(403, 'Akses hanya tersedia untuk Regional Manager.');
        }
    }

    private function terminalIds(Request $request): array
    {
        return DB::table('integrated_terminals')
            ->where('region_code', $request->user()->region_code ?: 'SULAWESI')
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function selectedTerminal(Request $request, array $allowed): ?int
    {
        if (! $request->filled('terminal_id')) {
            return null;
        }
        $terminalId = $request->integer('terminal_id');
        if (! in_array($terminalId, $allowed, true)) {
            throw new HttpException(403, 'Integrated Terminal berada di luar wilayah kewenangan.');
        }

        return $terminalId;
    }

    private function applyPeriod(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate($column, '>=', $request->date('date_from')->toDateString());
        }
        if ($request->filled('date_to')) {
            $query->whereDate($column, '<=', $request->date('date_to')->toDateString());
        }
    }

    private function auditSpv(Request $request, int $terminalId, string $action, string $description): void
    {
        DB::table('audit_log')->insert([
            'terminal_id' => $terminalId,
            'action' => $action,
            'user_name' => $request->user()->username,
            'ip_address' => $request->ip(),
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    public function terminals(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $rows = DB::table('integrated_terminals as t')
            ->whereIn('t.id', $ids)
            ->select('t.id', 't.code', 't.name', 't.city', 't.region_code', 't.region_name', 't.is_active')
            ->selectSub(fn ($query) => $query->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->selectRaw('COUNT(*)'), 'vehicles_count')
            ->selectSub(fn ($query) => $query->from('formulir_checklist as f')->whereColumn('f.terminal_id', 't.id')->selectRaw('COUNT(*)'), 'checklists_count')
            ->orderBy('t.name')
            ->get();

        return $this->ok($rows);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $terminalId = $this->selectedTerminal($request, $ids);
        $scope = $terminalId ? [$terminalId] : $ids;
        $today = today()->toDateString();
        $warningDate = today()->addDays(30)->toDateString();
        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : null;

        $checklists = DB::table('formulir_checklist')->whereIn('terminal_id', $scope);
        $this->applyPeriod($checklists, $request, 'tanggal_pemeriksaan');
        $vehicles = DB::table('kendaraan')->whereIn('terminal_id', $scope);

        $statusCounts = (clone $checklists)
            ->select('status_approval', DB::raw('COUNT(*) as total'))
            ->groupBy('status_approval')
            ->pluck('total', 'status_approval');

        $summary = [
            'terminals' => count($scope),
            'vehicles' => (clone $vehicles)->count(),
            'checklists' => (clone $checklists)->count(),
            'pending' => (int) ($statusCounts['pending_hsse'] ?? 0) + (int) ($statusCounts['signed_hsse'] ?? 0),
            'approved' => (int) ($statusCounts['approved'] ?? 0),
            'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            'ekim_active' => (clone $vehicles)->whereNotNull('ekim_valid_until')->whereDate('ekim_valid_until', '>', $warningDate)->count(),
            'ekim_expiring' => (clone $vehicles)->whereBetween('ekim_valid_until', [$today, $warningDate])->count(),
            'ekim_expired' => (clone $vehicles)->whereNotNull('ekim_valid_until')->whereDate('ekim_valid_until', '<', $today)->count(),
        ];

        $checklistCount = function (Builder $query, ?string $status = null) use ($dateFrom, $dateTo): void {
            $query->from('formulir_checklist as f')->whereColumn('f.terminal_id', 't.id');
            if ($dateFrom) {
                $query->whereDate('f.tanggal_pemeriksaan', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('f.tanggal_pemeriksaan', '<=', $dateTo);
            }
            if ($status) {
                $query->where('f.status_approval', $status);
            }
            $query->selectRaw('COUNT(*)');
        };

        $comparison = DB::table('integrated_terminals as t')
            ->whereIn('t.id', $scope)
            ->select('t.id', 't.code', 't.name', 't.city')
            ->selectSub(fn ($query) => $query->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->selectRaw('COUNT(*)'), 'vehicles')
            ->selectSub(fn ($query) => $checklistCount($query), 'checklists')
            ->selectSub(fn ($query) => $checklistCount($query, 'approved'), 'approved')
            ->selectSub(function ($query) use ($dateFrom, $dateTo) {
                $query->from('formulir_checklist as f')->whereColumn('f.terminal_id', 't.id')
                    ->whereIn('f.status_approval', ['pending_hsse', 'signed_hsse']);
                if ($dateFrom) {
                    $query->whereDate('f.tanggal_pemeriksaan', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('f.tanggal_pemeriksaan', '<=', $dateTo);
                }
                $query->selectRaw('COUNT(*)');
            }, 'pending')
            ->selectSub(fn ($query) => $checklistCount($query, 'rejected'), 'rejected')
            ->selectSub(fn ($query) => $query->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->whereNotNull('k.ekim_valid_until')->whereDate('k.ekim_valid_until', '>', $warningDate)->selectRaw('COUNT(*)'), 'ekim_active')
            ->selectSub(fn ($query) => $query->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->whereBetween('k.ekim_valid_until', [$today, $warningDate])->selectRaw('COUNT(*)'), 'ekim_expiring')
            ->selectSub(fn ($query) => $query->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->whereNotNull('k.ekim_valid_until')->whereDate('k.ekim_valid_until', '<', $today)->selectRaw('COUNT(*)'), 'expired')
            ->orderBy('t.name')
            ->get();

        return $this->ok(['summary' => $summary, 'comparison' => $comparison]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $terminalId = $this->selectedTerminal($request, $ids);
        $query = DB::table('kendaraan as k')
            ->join('integrated_terminals as t', 't.id', '=', 'k.terminal_id')
            ->leftJoin('transporters as tr', 'tr.id', '=', 'k.transporter_id')
            ->whereIn('k.terminal_id', $terminalId ? [$terminalId] : $ids);
        if ($request->filled('transporter_id')) {
            $query->where('k.transporter_id', $request->integer('transporter_id'));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(k.nomor_polisi) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(tr.name, k.nama_transport)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(k.merk_mobil, \'\')) LIKE ?', [$needle]));
        }
        if ($request->filled('jenis') && in_array(strtoupper((string) $request->query('jenis')), ['SPBU', 'INDUSTRI'], true)) {
            $query->where('k.jenis', strtoupper((string) $request->query('jenis')));
        }
        if ($request->filled('status')) {
            $query->where('k.status', strtoupper((string) $request->query('status')));
        }
        $page = $query
            ->select('k.id', 'k.nomor_polisi', 'k.nomor_urut', 'k.jenis', 'k.merk_mobil', 'k.tahun_kendaraan', 'k.produk', 'k.kapasitas', 'k.status', 'k.approval_status', 'k.ekim_valid_until', 'k.transporter_id', DB::raw('COALESCE(tr.name, k.nama_transport) as nama_transport'), 't.id as terminal_id', 't.code as terminal_code', 't.name as terminal_name')
            ->orderBy('t.name')
            ->orderBy('k.nomor_polisi')
            ->paginate(max(1, min(100, $request->integer('limit', 25))));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => collect($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'limit' => $page->perPage(), 'total' => $page->total(), 'totalPages' => $page->lastPage()],
        ]);
    }

    public function spvs(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $rows = User::query()
            ->leftJoin('integrated_terminals as t', 't.id', '=', 'users.terminal_id')
            ->where('users.role', 'admin')
            ->whereIn('users.terminal_id', $this->terminalIds($request))
            ->orderBy('t.name')
            ->orderBy('users.full_name')
            ->get(['users.id', 'users.full_name', 'users.email', 'users.phone', 'users.status', 'users.last_login', 'users.terminal_id', 't.code as terminal_code', 't.name as terminal_name']);

        return $this->ok($rows);
    }

    public function createSpv(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $request->merge(['email' => strtolower(trim((string) $request->email))]);
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'terminal_id' => ['required', 'integer'],
            'password' => ['required', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => $validator->errors()], 422);
        }
        $terminalId = $this->selectedTerminal($request, $this->terminalIds($request));
        if (User::where('role', 'admin')->where('terminal_id', $terminalId)->where('status', 'active')->exists()) {
            return response()->json(['success' => false, 'message' => 'Terminal tersebut sudah memiliki SPV HSSE aktif.', 'data' => null], 409);
        }
        $user = User::create([
            'username' => 'spv_'.substr(hash('sha256', $request->email), 0, 32),
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => 'HSSE',
            'position' => 'SPV HSSE',
            'role' => 'admin',
            'terminal_id' => $terminalId,
            'region_code' => $request->user()->region_code ?: 'SULAWESI',
            'status' => 'active',
            'password' => $request->password,
        ]);
        $this->auditSpv($request, $terminalId, 'SPV_CREATE', "Akun SPV HSSE {$user->email} dibuat oleh Regional Manager");

        return response()->json(['success' => true, 'message' => 'Akun SPV HSSE berhasil dibuat.', 'data' => ['id' => $user->id]], 201);
    }

    public function toggleSpv(Request $request, int $id): JsonResponse
    {
        $this->authorizeRegional($request);
        $spv = User::where('id', $id)->where('role', 'admin')->whereIn('terminal_id', $this->terminalIds($request))->first();
        if (! $spv) {
            throw new HttpException(404, 'Akun SPV HSSE tidak ditemukan.');
        }
        $newStatus = $request->input('status') === 'active' ? 'active' : 'inactive';
        if ($newStatus === 'active' && User::where('role', 'admin')->where('terminal_id', $spv->terminal_id)->where('status', 'active')->where('id', '<>', $spv->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Terminal tersebut sudah memiliki SPV HSSE aktif.', 'data' => null], 409);
        }
        $spv->status = $newStatus;
        $spv->save();
        if ($spv->status === 'inactive') {
            $spv->tokens()->delete();
        }
        $this->auditSpv($request, $spv->terminal_id, 'SPV_STATUS', "Status SPV HSSE {$spv->email}: {$spv->status}");

        return $this->ok(['status' => $spv->status], 'Status SPV HSSE berhasil diperbarui.');
    }

    public function resetSpvPassword(Request $request, int $id): JsonResponse
    {
        $this->authorizeRegional($request);
        $validator = Validator::make($request->all(), ['password' => ['required', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => $validator->errors()], 422);
        }
        $spv = User::where('id', $id)->where('role', 'admin')->whereIn('terminal_id', $this->terminalIds($request))->first();
        if (! $spv) {
            throw new HttpException(404, 'Akun SPV HSSE tidak ditemukan.');
        }
        $spv->password = Hash::make($request->password);
        $spv->save();
        $spv->tokens()->delete();
        $this->auditSpv($request, $spv->terminal_id, 'SPV_PASSWORD_RESET', "Password SPV HSSE {$spv->email} direset oleh Regional Manager");

        return $this->ok(null, 'Password SPV HSSE berhasil direset.');
    }

    public function ekim(Request $request, EkimStatusCalculator $statusCalculator): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $terminalId = $this->selectedTerminal($request, $ids);
        $today = today()->toDateString();
        $warningDate = today()->addDays(30)->toDateString();

        $query = DB::table('kendaraan as k')
            ->join('integrated_terminals as t', 't.id', '=', 'k.terminal_id')
            ->leftJoin('transporters as tr', 'tr.id', '=', 'k.transporter_id')
            ->whereIn('k.terminal_id', $terminalId ? [$terminalId] : $ids);
        if ($request->filled('transporter_id')) {
            $query->where('k.transporter_id', $request->integer('transporter_id'));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(k.nomor_polisi) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(tr.name, k.nama_transport)) LIKE ?', [$needle]));
        }
        match ($request->query('status')) {
            'active' => $query->whereDate('k.ekim_valid_until', '>', $warningDate),
            'expiring' => $query->whereBetween('k.ekim_valid_until', [$today, $warningDate]),
            'expired' => $query->whereNotNull('k.ekim_valid_until')->whereDate('k.ekim_valid_until', '<', $today),
            'unavailable' => $query->whereNull('k.ekim_valid_until'),
            default => null,
        };
        $page = $query->select('k.id', 'k.nomor_polisi', DB::raw('COALESCE(tr.name, k.nama_transport) as nama_transport'), 'k.jenis', 'k.ekim_valid_until', 'k.status', 't.id as terminal_id', 't.name as terminal_name')
            ->orderByRaw('CASE WHEN k.ekim_valid_until IS NULL THEN 1 ELSE 0 END')
            ->orderBy('k.ekim_valid_until')
            ->paginate(max(1, min(100, $request->integer('limit', 25))));
        $rows = collect($page->items())->map(function ($row) use ($statusCalculator, $today) {
            $status = $statusCalculator->evaluate($row->ekim_valid_until, $today);
            $row->days_remaining = $status['days_remaining'];
            $row->ekim_status = $status['status'];

            return $row;
        });

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $rows,
            'pagination' => ['page' => $page->currentPage(), 'limit' => $page->perPage(), 'total' => $page->total(), 'totalPages' => $page->lastPage()],
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $terminalId = $this->selectedTerminal($request, $ids);
        $scope = $terminalId ? [$terminalId] : $ids;
        $from = $request->filled('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : today()->subMonths(11)->startOfMonth();
        $to = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : today()->endOfMonth();

        $checklists = DB::table('formulir_checklist')
            ->whereIn('terminal_id', $scope)
            ->whereBetween('tanggal_pemeriksaan', [$from->toDateString(), $to->toDateString()])
            ->get(['terminal_id', 'tanggal_pemeriksaan', 'status_approval']);
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to && count($months) < 36) {
            $key = $cursor->format('Y-m');
            $months[$key] = ['month' => $key, 'label' => $cursor->translatedFormat('M Y'), 'total' => 0, 'approved' => 0, 'rejected' => 0];
            $cursor->addMonth();
        }
        foreach ($checklists as $checklist) {
            $key = Carbon::parse($checklist->tanggal_pemeriksaan)->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }
            $months[$key]['total']++;
            if ($checklist->status_approval === 'approved') {
                $months[$key]['approved']++;
            }
            if ($checklist->status_approval === 'rejected') {
                $months[$key]['rejected']++;
            }
        }

        $today = today()->toDateString();
        $byTerminal = DB::table('integrated_terminals as t')
            ->whereIn('t.id', $scope)
            ->select('t.id', 't.code', 't.name')
            ->selectSub(fn ($q) => $q->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->selectRaw('COUNT(*)'), 'vehicles')
            ->selectSub(fn ($q) => $q->from('formulir_checklist as f')->whereColumn('f.terminal_id', 't.id')->whereBetween('f.tanggal_pemeriksaan', [$from->toDateString(), $to->toDateString()])->selectRaw('COUNT(*)'), 'checklists')
            ->selectSub(fn ($q) => $q->from('formulir_checklist as f')->whereColumn('f.terminal_id', 't.id')->whereBetween('f.tanggal_pemeriksaan', [$from->toDateString(), $to->toDateString()])->where('f.status_approval', 'approved')->selectRaw('COUNT(*)'), 'approved')
            ->selectSub(fn ($q) => $q->from('kendaraan as k')->whereColumn('k.terminal_id', 't.id')->whereNotNull('k.ekim_valid_until')->whereDate('k.ekim_valid_until', '<', $today)->selectRaw('COUNT(*)'), 'ekim_expired')
            ->orderBy('t.name')
            ->get();

        return $this->ok(['period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()], 'trend' => array_values($months), 'terminals' => $byTerminal]);
    }

    public function audits(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $terminalId = $this->selectedTerminal($request, $ids);
        $query = DB::table('audit_log as a')
            ->leftJoin('integrated_terminals as t', 't.id', '=', 'a.terminal_id')
            ->leftJoin('formulir_checklist as f', 'f.id', '=', 'a.formulir_id')
            ->whereIn('a.terminal_id', $terminalId ? [$terminalId] : $ids);
        $this->applyPeriod($query, $request, 'a.created_at');
        if ($search = trim((string) $request->query('search', ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(a.user_name) LIKE ?', [$needle])->orWhereRaw('LOWER(a.action) LIKE ?', [$needle])->orWhereRaw('LOWER(a.description) LIKE ?', [$needle]));
        }
        $page = $query->select('a.*', 't.name as terminal_name', 'f.nomor_polisi')
            ->latest('a.created_at')
            ->paginate(max(1, min(100, $request->integer('limit', 50))));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => collect($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'limit' => $page->perPage(), 'total' => $page->total(), 'totalPages' => $page->lastPage()],
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $this->authorizeRegional($request);
        $ids = $this->terminalIds($request);
        $terminalId = $this->selectedTerminal($request, $ids);
        $scope = $terminalId ? [$terminalId] : $ids;
        $query = DB::table('kendaraan as k')
            ->join('integrated_terminals as t', 't.id', '=', 'k.terminal_id')
            ->leftJoin('transporters as tr', 'tr.id', '=', 'k.transporter_id')
            ->whereIn('k.terminal_id', $scope);
        if ($request->filled('transporter_id')) {
            $query->where('k.transporter_id', $request->integer('transporter_id'));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(k.nomor_polisi) LIKE ?', [$needle])->orWhereRaw('LOWER(COALESCE(tr.name, k.nama_transport)) LIKE ?', [$needle]));
        }
        $vehicles = $query->get(['k.id', 'k.nomor_polisi', 'k.jenis', 'k.terminal_id', 'k.transporter_id', 't.name as terminal_name', DB::raw('COALESCE(tr.name, k.nama_transport) as transporter_name')]);
        $requiredBase = ['STNK', 'PAJAK', 'SURAT_KEUR', 'SURAT_TERA'];
        $rows = $vehicles->map(function ($vehicle) use ($requiredBase) {
            $required = $vehicle->jenis === 'INDUSTRI' ? [...$requiredBase, 'SIM'] : $requiredBase;
            $documents = DB::table('dokumen_kendaraan')
                ->where('vehicle_id', $vehicle->id)
                ->where('status', 'DISETUJUI')
                ->get(['id', 'jenis_dokumen', 'nama_file_asli', 'tanggal_berlaku', 'reviewed_at']);
            $approvedTypes = $documents->pluck('jenis_dokumen')->unique()->all();

            return [
                'vehicle_id' => $vehicle->id,
                'nomor_polisi' => $vehicle->nomor_polisi,
                'jenis' => $vehicle->jenis,
                'terminal_id' => $vehicle->terminal_id,
                'terminal_name' => $vehicle->terminal_name,
                'transporter_id' => $vehicle->transporter_id,
                'transporter_name' => $vehicle->transporter_name,
                'required_count' => count($required),
                'approved_count' => count(array_intersect($required, $approvedTypes)),
                'is_complete' => array_diff($required, $approvedTypes) === [],
                'approved_documents' => $documents,
            ];
        });

        return $this->ok($rows);
    }

    public function downloadDocument(Request $request, int $id)
    {
        $this->authorizeRegional($request);
        $document = DB::table('dokumen_kendaraan')
            ->where('id', $id)
            ->where('status', 'DISETUJUI')
            ->whereIn('terminal_id', $this->terminalIds($request))
            ->first();
        if (! $document) {
            abort(404);
        }
        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('private')->download($document->file_path, $document->nama_file_asli);
    }
}
