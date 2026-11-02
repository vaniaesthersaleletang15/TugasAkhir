<?php

namespace App\Domain\Checklist;

final class ChecklistFailureReasonBuilder
{
    public function build(iterable $badItems, ?string $manualReason = null): string
    {
        $items = [];
        foreach ($badItems as $item) {
            $name = trim((string) data_get($item, 'item_name', ''));
            $note = trim((string) data_get($item, 'keterangan', ''));
            if ($name === '') {
                continue;
            }
            $items[] = $note !== '' ? "$name ($note)" : $name;
        }

        $reasons = [];
        if ($items !== []) {
            $reasons[] = 'Item Tidak Baik: '.implode('; ', $items);
        }

        $manualReason = trim((string) $manualReason);
        if ($manualReason !== '') {
            $reasons[] = 'Catatan SPV HSSE: '.$manualReason;
        }

        return implode('. ', $reasons);
    }
}
