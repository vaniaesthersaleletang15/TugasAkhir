<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChecklistComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChecklistDocumentValidityPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function terminalId(): int
    {
        return (int) DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
    }

    /** @param array<string, string> $expiries */
    private function checklist(string $plate, array $expiries, string $status = 'pending_hsse'): int
    {
        $id = (int) DB::table('formulir_checklist')->insertGetId([
            'terminal_id' => $this->terminalId(),
            'jenis_kendaraan' => 'INDUSTRI',
            'nomor_polisi' => $plate,
            'tanggal_pemeriksaan' => today(),
            'ekim_valid_until' => today()->addYear(),
            'nama_pemeriksa' => 'Petugas HSSE',
            'status_approval' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $documentItems = [
            'STNK' => 'STNK',
            'PAJAK' => 'PAJAK',
            'SIM' => 'SIMFIT (Industri)',
            'SURAT_KEUR' => 'Surat Keur DLLAAJR',
            'SURAT_TERA' => 'Surat Tera Metrologi',
        ];
        $rows = [];
        foreach ($documentItems as $type => $itemName) {
            $rows[] = [
                'formulir_id' => $id,
                'item_number' => count($rows) + 1,
                'item_name' => $itemName,
                'is_baik' => true,
                'is_tidak' => false,
                'tanggal_expire' => $expiries[$type] ?? null,
            ];
        }
        DB::table('checklist_items')->insert($rows);

        return $id;
    }

    public function test_an_updated_document_does_not_require_other_valid_checklist_dates_to_be_uploaded_again(): void
    {
        $plate = 'DB 8562 LI';
        $valid = today()->addMonths(8)->toDateString();
        $formId = $this->checklist($plate, [
            'STNK' => today()->addMonth()->toDateString(), 'PAJAK' => $valid,
            'SIM' => $valid, 'SURAT_KEUR' => $valid, 'SURAT_TERA' => $valid,
        ]);
        DB::table('dokumen_kendaraan')->insert([
            'terminal_id' => $this->terminalId(), 'nomor_polisi' => $plate,
            'jenis_dokumen' => 'STNK', 'tanggal_berlaku' => '2027-01-12',
            'status' => 'DISETUJUI', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $form = DB::table('formulir_checklist')->where('id', $formId)->first();
        $this->assertNull(app(ChecklistComplianceService::class)->firstApprovalError($form));
    }

    public function test_expired_date_from_checklist_is_still_rejected(): void
    {
        $valid = today()->addMonths(8)->toDateString();
        $formId = $this->checklist('DB 9000 XX', [
            'STNK' => $valid, 'PAJAK' => today()->subDay()->toDateString(),
            'SIM' => $valid, 'SURAT_KEUR' => $valid, 'SURAT_TERA' => $valid,
        ]);
        $form = DB::table('formulir_checklist')->where('id', $formId)->first();

        $this->assertSame(
            'Dokumen wajib belum lengkap atau kedaluwarsa: PAJAK.',
            app(ChecklistComplianceService::class)->firstApprovalError($form),
        );
    }

    public function test_vehicle_expiries_use_approved_document_then_fall_back_to_previous_checklist(): void
    {
        $plate = 'DB 9111 YY';
        $fallback = today()->addMonths(6)->toDateString();
        $this->checklist($plate, [
            'STNK' => today()->addMonth()->toDateString(), 'PAJAK' => $fallback,
            'SIM' => $fallback, 'SURAT_KEUR' => $fallback, 'SURAT_TERA' => $fallback,
        ], 'approved');
        DB::table('dokumen_kendaraan')->insert([
            'terminal_id' => $this->terminalId(), 'nomor_polisi' => $plate,
            'jenis_dokumen' => 'STNK', 'tanggal_berlaku' => '2027-01-12',
            'status' => 'DISETUJUI', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $expiries = app(ChecklistComplianceService::class)
            ->effectiveDocumentExpiries([(object) ['nomor_polisi' => $plate]]);
        $this->assertSame('2027-01-12', $expiries[$plate]['STNK']);
        foreach (['PAJAK', 'SIM', 'SURAT_KEUR', 'SURAT_TERA'] as $type) {
            $this->assertSame($fallback, $expiries[$plate][$type]);
        }
    }

    public function test_approving_updated_document_refreshes_pending_hsse_checklist(): void
    {
        $plate = 'DB 9222 ZZ';
        $oldExpiry = today()->addMonth()->toDateString();
        $formId = $this->checklist($plate, [
            'STNK' => $oldExpiry, 'PAJAK' => $oldExpiry, 'SIM' => $oldExpiry,
            'SURAT_KEUR' => $oldExpiry, 'SURAT_TERA' => $oldExpiry,
        ]);
        $admin = User::create([
            'username' => 'document_review_admin', 'full_name' => 'SPV HSSE Pengujian',
            'email' => 'document-review@example.test', 'password' => 'Secret123!',
            'role' => 'admin', 'terminal_id' => $this->terminalId(), 'status' => 'active',
        ]);
        $newExpiry = today()->addYear()->toDateString();
        $documentId = (int) DB::table('dokumen_kendaraan')->insertGetId([
            'terminal_id' => $this->terminalId(), 'nomor_polisi' => $plate,
            'jenis_dokumen' => 'STNK', 'tanggal_berlaku' => $newExpiry,
            'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withToken($admin->createToken('document-review')->plainTextToken)
            ->postJson("/api/v1/documents/{$documentId}/review", [
                'action' => 'approve', 'catatan_admin' => 'Dokumen pengganti telah diperiksa.',
            ])->assertOk()->assertJsonPath('data.updated_draft_items', 1);
        $this->assertDatabaseHas('checklist_items', [
            'formulir_id' => $formId, 'item_name' => 'STNK', 'tanggal_expire' => $newExpiry,
        ]);
    }
}
