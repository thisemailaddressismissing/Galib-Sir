<?php

namespace Tests\Unit;

use App\Support\SimplePdf;
use PHPUnit\Framework\TestCase;

class SimplePdfTest extends TestCase
{
    public function test_output_is_a_structurally_valid_pdf(): void
    {
        $pdf = new SimplePdf();
        $pdf->heading('Title');
        $pdf->text('Hello (world) \\ test');
        $rows = [];
        for ($i = 1; $i <= 120; $i++) {
            $rows[] = ["Row $i", (string) ($i * 10)];
        }
        $pdf->table(['Name', 'No'], $rows, [400, 115], [false, true]);
        $out = $pdf->output();

        $this->assertStringStartsWith('%PDF-1.4', $out);
        $this->assertStringEndsWith('%%EOF', $out);

        // Every xref entry points at its object header.
        preg_match_all('/^(\d{10}) \d{5} [fn] $/m', $out, $entries);
        $this->assertGreaterThan(1, count($entries[1]));
        foreach (array_slice($entries[1], 1) as $i => $offset) {
            $objectNumber = $i + 1;
            $header = "$objectNumber 0 obj";
            $this->assertSame($header, substr($out, (int) $offset, strlen($header)), "xref entry $objectNumber");
        }
        $maxObject = count($entries[1]) - 1;

        // Every indirect reference resolves to a written object.
        preg_match_all('/(\d+) 0 R/', $out, $refs);
        $this->assertNotEmpty($refs[1]);
        foreach ($refs[1] as $ref) {
            $this->assertGreaterThanOrEqual(1, (int) $ref);
            $this->assertLessThanOrEqual($maxObject, (int) $ref);
        }

        // Declared page count matches page objects, and content spilled past page one.
        preg_match('/\/Count (\d+)/', $out, $count);
        $pageObjects = preg_match_all('/\/Type \/Page(?!s)/', $out);
        $this->assertSame((int) $count[1], $pageObjects);
        $this->assertGreaterThan(1, $pageObjects);

        // Declared stream lengths match actual stream bytes.
        preg_match_all('/<< \/Length (\d+) >>\nstream\n(.*?)\nendstream/s', $out, $streams);
        $this->assertNotEmpty($streams[1]);
        foreach ($streams[1] as $i => $length) {
            $this->assertSame((int) $length, strlen($streams[2][$i]));
        }

        // Special characters in text are escaped, not raw.
        $this->assertStringContainsString('(Hello \\(world\\) \\\\ test)', $out);
    }

    public function test_sanitize_keeps_output_to_printable_ascii(): void
    {
        $this->assertSame('Tk. 100', SimplePdf::sanitize('৳ 100'));
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]*$/', SimplePdf::sanitize('’“quote”—… নাম'));
    }
}
