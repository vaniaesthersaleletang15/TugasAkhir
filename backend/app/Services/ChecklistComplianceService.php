<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ChecklistComplianceService
{
    private const BASE_REQUIRED_DOCUMENTS = ['STNK', 'PAJAK', 'SURAT_KEUR', 'SURAT_TERA'];

    private const DOCUMENT_CHECKLIST_ITEMS = [
        'STNK' => 'STNK',
        'PAJAK' => 'PAJAK',
        'SIM' => 'SIMFIT (Industri)',
        'SURAT_KEUR' => 'Surat Keur DLLAAJR',
        'SURAT_TERA' => 'Surat Tera Metrologi',
    ];

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
        $expiries = DB::table('dokumen_kendaraan')
            ->where('terminal_id', $form->terminal_id)
            ->where('nomor_polisi', $form->nomor_polisi)
            ->where('status', 'DISETUJUI')
            ->whereIn('jenis_dokumen', $required)
            ->whereNotNull('tanggal_berlaku')
            ->orderByDesc('tanggal_berlaku')
            ->get(['jenis_dokumen', 'tanggal_berlaku'])
            ->reduce(function (array $result, object $document): array {
                $result[$document->jenis_dokumen] ??= (string) $document->tanggal_berlaku;

                return $result;
            }, []);

        // Data lama PRIMA menyimpan masa berlaku surat pada item checklist.
        // Nilai itu tetap berlaku untuk jenis surat yang belum mempunyai record
        // dokumen terverifikasi. Dokumen baru yang telah disetujui selalu menang.
        $itemToDocument = array_flip(self::DOCUMENT_CHECKLIST_ITEMS);
        foreach ($items as $item) {
            $documentType = $itemToDocument[$item->item_name] ?? null;
            if ($documentType && ! isset($expiries[$documentType]) && filled($item->tanggal_expire)) {
                $expiries[$documentType] = (string) $item->tanggal_expire;
            }
        }

        $missing = array_values(array_filter(
            $required,
            fn (string $type): bool => ! isset($expiries[$type])
                || $expiries[$type] < (string) $form->tanggal_pemeriksaan,
        ));
        if ($missing !== []) {
            return 'Dokumen wajib belum lengkap atau kedaluwarsa: '.implode(', ', $missing).'.';
        }

        return null;
    }

    /**
     * Menghasilkan masa berlaku efektif per kendaraan. Dokumen terverifikasi
     * diprioritaskan; jenis yang belum mempunyai dokumen mengambil nilai
     * terakhir dari checklist sebelumnya agar surat lain tidak perlu diisi ulang.
     *
     * @param  iterable<object>  $vehicles
     * @return array<string, array<string, string>>
     */
    public function effectiveDocumentExpiries(iterable $vehicles): array
    {
        $vehicles = collect($vehicles);
        $plates = $vehicles->pluck('nomor_polisi')->filter()->unique()->values();
        if ($plates->isEmpty()) {
            return [];
        }

        $result = [];
        $documents = DB::table('dokumen_kendaraan')
            ->whereIn('nomor_polisi', $plates)
            ->where('status', 'DISETUJUI')
            ->whereIn('jenis_dokumen', array_keys(self::DOCUMENT_CHECKLIST_ITEMS))
            ->whereNotNull('tanggal_berlaku')
            ->orderByDesc('tanggal_berlaku')
            ->orderByDesc('reviewed_at')
            ->get(['nomor_polisi', 'jenis_dokumen', 'tanggal_berlaku']);
        foreach ($documents as $document) {
            $result[$document->nomor_polisi][$document->jenis_dokumen]
                ??= (string) $document->tanggal_berlaku;
        }

        $itemToDocument = array_flip(self::DOCUMENT_CHECKLIST_ITEMS);
        $history = DB::table('checklist_items as item')
            ->join('formulir_checklist as form', 'form.id', '=', 'item.formulir_id')
            ->whereIn('form.nomor_polisi', $plates)
            ->whereIn('item.item_name', array_values(self::DOCUMENT_CHECKLIST_ITEMS))
            ->whereNotNull('item.tanggal_expire')
            ->where('form.status_approval', '<>', 'rejected')
            ->orderByDesc('form.tanggal_pemeriksaan')
            ->orderByDesc('form.created_at')
            ->orderByDesc('form.id')
            ->get(['form.nomor_polisi', 'item.item_name', 'item.tanggal_expire']);
        foreach ($history as $item) {
            $documentType = $itemToDocument[$item->item_name] ?? null;
            if ($documentType) {
                $result[$item->nomor_polisi][$documentType]
                    ??= (string) $item->tanggal_expire;
            }
        }

        return $result;
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
