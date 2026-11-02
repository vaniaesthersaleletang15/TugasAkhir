<?php

namespace Tests\Unit;

use App\Support\SimpleXlsx;
use Tests\TestCase;
use ZipArchive;

class SimpleXlsxTest extends TestCase
{
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_xlsx_contains_required_workbook_entries(): void
    {
        $entries = $this->entries($this->create(['Nama'], [['Vania']]));

        $this->assertContains('[Content_Types].xml', $entries);
        $this->assertContains('xl/workbook.xml', $entries);
        $this->assertContains('xl/worksheets/sheet1.xml', $entries);
        $this->assertContains('xl/styles.xml', $entries);
    }

    public function test_headers_rows_and_xml_special_characters_are_written_safely(): void
    {
        $sheet = $this->sheet($this->create(['Nama & Unit'], [['A < B & "C"']]));

        $this->assertStringContainsString('r="A1"', $sheet);
        $this->assertStringContainsString('Nama &amp; Unit', $sheet);
        $this->assertStringContainsString('r="A2"', $sheet);
        $this->assertStringContainsString('A &lt; B &amp; &quot;C&quot;', $sheet);
    }

    public function test_column_reference_supports_columns_after_z(): void
    {
        $headers = array_map(fn (int $number) => 'Kolom '.$number, range(1, 27));
        $sheet = $this->sheet($this->create($headers, []));

        $this->assertStringContainsString('r="Z1"', $sheet);
        $this->assertStringContainsString('r="AA1"', $sheet);
    }

    public function test_empty_dataset_still_has_header_filter_and_frozen_pane(): void
    {
        $sheet = $this->sheet($this->create(['A', 'B'], []));

        $this->assertStringContainsString('autoFilter ref="A1:G1"', $sheet);
        $this->assertStringContainsString('state="frozen"', $sheet);
    }

    private function create(array $headers, iterable $rows): string
    {
        $path = SimpleXlsx::create($headers, $rows);
        $this->createdFiles[] = $path;

        return $path;
    }

    private function entries(string $path): array
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }
        $zip->close();

        return $entries;
    }

    private function sheet(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $sheet;
    }
}
