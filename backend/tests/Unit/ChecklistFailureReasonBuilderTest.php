<?php

namespace Tests\Unit;

use App\Domain\Checklist\ChecklistFailureReasonBuilder;
use PHPUnit\Framework\TestCase;

class ChecklistFailureReasonBuilderTest extends TestCase
{
    private ChecklistFailureReasonBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ChecklistFailureReasonBuilder;
    }

    public function test_empty_input_produces_empty_reason(): void
    {
        $this->assertSame('', $this->builder->build([]));
    }

    public function test_bad_item_without_note_uses_item_name(): void
    {
        $this->assertSame(
            'Item Tidak Baik: Kondisi Ban',
            $this->builder->build([['item_name' => 'Kondisi Ban', 'keterangan' => '']]),
        );
    }

    public function test_bad_item_note_is_included(): void
    {
        $this->assertSame(
            'Item Tidak Baik: APAR (Masa berlaku habis)',
            $this->builder->build([(object) ['item_name' => 'APAR', 'keterangan' => ' Masa berlaku habis ']]),
        );
    }

    public function test_multiple_items_and_manual_reason_are_combined(): void
    {
        $reason = $this->builder->build([
            ['item_name' => 'Ban', 'keterangan' => 'Tipis'],
            ['item_name' => 'Lampu', 'keterangan' => null],
        ], 'Perlu perbaikan');

        $this->assertSame(
            'Item Tidak Baik: Ban (Tipis); Lampu. Catatan SPV HSSE: Perlu perbaikan',
            $reason,
        );
    }

    public function test_blank_items_and_whitespace_reason_are_ignored(): void
    {
        $this->assertSame('', $this->builder->build([
            ['item_name' => '  ', 'keterangan' => 'catatan'],
        ], '   '));
    }
}
