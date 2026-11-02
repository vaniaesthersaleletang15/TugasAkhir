<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ChecklistComplianceService
{
    private const BASE_REQUIRED_DOCUMENTS = ['STNK', 'PAJAK', 'SURAT_KEUR', 'SURAT_TERA'];

    public function validateItems(array $items): ?string
    {
        if ($items === []) {
            return 'Checklist wajib memiliki minimal satu item.';
        }

        foreach ($items as $index => $item) {
            $name = trim((string) ($item['nama'] ?? 'Item '.($index + 1)));
            $baik = filter_var($item['baik'] ?? false, FILTER_VALIDATE_BOOL);
            $tidak = filter_var($item['tidak'] ?? false, FILTER_VALIDATE_BOOL);
            if ($baik === $tidak) {
                return "Item {$name} wajib dipilih tepat satu kondisi: Baik atau Tidak.";
            }
            if ($tidak && trim((string) ($item['keterangan'] ?? '')) === '') {
                return "Item {$name} berstatus Tidak dan wajib memiliki temuan atau catatan.";
            }
        }

        return null;
    }

    public function firstApprovalError(object $form): ?string
    {
        if (blank($form->nama_pemeriksa) || blank($form->tanggal_pemeriksaan) || blank($form->ekim_valid_until)) {
            return 'Nama pemeriksa, tanggal pemeriksaan, dan E-KIM Valid Until wajib diisi.';
        }

        $items = DB::table('checklist_items')->where('formulir_id', $form->id)->get();
        if ($items->isEmpty()) {
            return 'Checklist belum memiliki item pemeriksaan.';
        }
        foreach ($items as $item) {
            if ((bool) $item->is_baik === (bool) $item->is_tidak) {
                return "Item {$item->item_name} belum memiliki tepat satu kondisi.";
            }
            if ($item->is_tidak && blank($item->keterangan)) {
                return "Item {$item->item_name} berstatus Tidak tanpa catatan.";
            }
        }

        $required = self::BASE_REQUIRED_DOCUMENTS;
        if (strtoupper((string) $form->jenis_kendaraan) === 'INDUSTRI') {
            $required[] = 'SIM';
        }
        $approved = DB::table('dokumen_kendaraan')
            ->where('terminal_id', $form->terminal_id)
            ->where('nomor_polisi', $form->nomor_polisi)
            ->where('status', 'DISETUJUI')
            ->whereIn('jenis_dokumen', $required)
            ->where(fn ($query) => $query->whereNull('tanggal_berlaku')->orWhereDate('tanggal_berlaku', '>=', $form->tanggal_pemeriksaan))
            ->pluck('jenis_dokumen')
            ->unique()
            ->all();
        $missing = array_values(array_diff($required, $approved));
        if ($missing !== []) {
            return 'Dokumen wajib belum lengkap atau kedaluwarsa: '.implode(', ', $missing).'.';
        }

        return null;
    }

    public function snapshot(int $formId): array
    {
        $form = (array) DB::table('formulir_checklist')->where('id', $formId)->first();
        $items = DB::table('checklist_items')
            ->where('formulir_id', $formId)
            ->orderBy('item_number')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
        $documents = DB::table('dokumen_kendaraan')
            ->where('nomor_polisi', $form['nomor_polisi'] ?? null)
            ->get(['id', 'jenis_dokumen', 'status', 'tanggal_berlaku', 'reviewed_by', 'reviewed_at'])
            ->map(fn ($document) => (array) $document)
            ->all();

        return ['form' => $form, 'items' => $items, 'documents' => $documents];
    }

    public function recordApproval(
        object $form,
        object $actor,
        string $decision,
        ?string $notes,
        ?string $hash,
        ?string $ipAddress,
    ): void {
        DB::table('checklist_approvals')->insert([
            'formulir_id' => $form->id,
            'terminal_id' => $form->terminal_id,
            'actor_id' => $actor->id,
            'actor_name' => $actor->full_name ?: $actor->username,
            'actor_role' => $actor->role,
            'decision' => $decision,
            'notes' => $notes,
            'document_hash' => $hash,
            'snapshot' => json_encode($this->snapshot($form->id), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
