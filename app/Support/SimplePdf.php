<?php

namespace App\Support;

/**
 * Minimal dependency-free PDF writer: A4 pages with headings, text,
 * rules and simple tables with automatic page breaks. Uses the core
 * Helvetica fonts only, so text is limited to printable ASCII
 * (see sanitize()).
 */
class SimplePdf
{
    private const PAGE_WIDTH = 595; // A4, points
    private const PAGE_HEIGHT = 842;
    private const MARGIN = 40;
    private const BOTTOM = 50;
    private const ROW_HEIGHT = 18;

    /** Adobe Type 1 Helvetica character widths in 1/1000 em for ASCII 32..126 */
    private const HELVETICA_WIDTHS = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 222, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
        112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
        120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
    ];

    /** Adobe Type 1 Helvetica-Bold character widths in 1/1000 em for ASCII 32..126 */
    private const HELVETICA_BOLD_WIDTHS = [
        32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
        64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
        96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
        104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611,
        112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611, 118 => 556, 119 => 778,
        120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 260, 125 => 389, 126 => 584,
    ];

    /** @var array<int, array<int, string>> content operations per page */
    private array $pages = [];
    private float $y = 0;

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = [];
        $this->y = self::PAGE_HEIGHT - self::MARGIN;
    }

    public function heading(string $text, int $size = 16): void
    {
        $this->ensureSpace($size + 20);
        $this->y -= 6;
        $this->emit(sprintf(
            'BT /F2 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $size, self::MARGIN, $this->y - $size, self::escape(self::sanitize($text))
        ));
        $this->y -= ($size + 8);
    }

    public function text(string $text, int $size = 10, bool $bold = false): void
    {
        $this->ensureSpace($size + 6);
        $this->emit(sprintf(
            'BT /%s %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $bold ? 'F2' : 'F1', $size, self::MARGIN, $this->y - $size, self::escape(self::sanitize($text))
        ));
        $this->y -= ($size + 4);
    }

    public function gap(float $height = 8): void
    {
        $this->y -= $height;
    }

    public function rule(): void
    {
        $this->ensureSpace(12);
        $this->y -= 4;
        $this->line(self::MARGIN, $this->y, self::PAGE_WIDTH - self::MARGIN, $this->y);
        $this->y -= 8;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, float>  $widths  column widths in points, must sum to 515
     * @param  array<int, bool>  $rightAlign  per-column right alignment
     */
    public function table(array $headers, array $rows, array $widths, array $rightAlign = []): void
    {
        $header = function () use ($headers, $widths, $rightAlign): void {
            $this->tableRow($headers, $widths, $rightAlign, true);
            $this->line(self::MARGIN, $this->y + 4, self::PAGE_WIDTH - self::MARGIN, $this->y + 4);
            $this->y -= 2;
        };

        $this->ensureSpace(self::ROW_HEIGHT + 6);
        $header();
        foreach ($rows as $row) {
            $this->ensureSpace(self::ROW_HEIGHT + 4, $header);
            $this->tableRow($row, $widths, $rightAlign, false);
        }
        $this->line(self::MARGIN, $this->y + 4, self::PAGE_WIDTH - self::MARGIN, $this->y + 4);
        $this->y -= 14;
    }

    /** Full PDF document bytes. */
    public function output(): string
    {
        $pageRefs = [];
        $next = 3;
        foreach ($this->pages as $operations) {
            $pageRefs[] = [$next, $next + 1]; // [page object, contents object]
            $next += 2;
        }
        $font1 = $next++;
        $font2 = $next++;

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = implode(' ', array_map(fn ($ref) => "{$ref[0]} 0 R", $pageRefs));
        $objects[2] = '<< /Type /Pages /Kids ['.$kids.'] /Count '.count($this->pages).' >>';
        foreach ($this->pages as $i => $operations) {
            [$page, $contents] = $pageRefs[$i];
            $objects[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.']'
                ." /Resources << /Font << /F1 $font1 0 R /F2 $font2 0 R >> >> /Contents $contents 0 R >>";
            $stream = implode("\n", $operations);
            $objects[$contents] = "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";
        }
        $objects[$font1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$font2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $max = max(array_keys($objects));
        for ($i = 1; $i <= $max; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "$i 0 obj\n{$objects[$i]}\nendobj\n";
        }
        $xrefAt = strlen($pdf);
        $pdf .= 'xref'."\n0 ".($max + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer'."\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n$xrefAt\n%%EOF";

        return $pdf;
    }

    private function tableRow(array $cells, array $widths, array $rightAlign, bool $bold): void
    {
        $size = $bold ? 10 : 9;
        $pad = 6;
        $x = self::MARGIN;
        foreach ($widths as $i => $width) {
            $isRight = $rightAlign[$i] ?? false;
            $availWidth = max(0, $width - ($pad * 2));
            $cell = self::fit((string) ($cells[$i] ?? ''), $availWidth, $size, $bold);
            $w = self::textWidth($cell, $size, $bold);

            $tx = $isRight
                ? $x + $width - $pad - $w
                : $x + $pad;

            $this->emit(sprintf(
                'BT /%s %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
                $bold ? 'F2' : 'F1', $size, $tx, $this->y - 12, self::escape($cell)
            ));
            $x += $width;
        }
        $this->y -= self::ROW_HEIGHT;
    }

    private function ensureSpace(float $need, ?callable $onNewPage = null): void
    {
        if ($this->y - $need < self::BOTTOM) {
            $this->addPage();
            if ($onNewPage) {
                $onNewPage();
            }
        }
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->emit(sprintf('0.5 w %.2F %.2F m %.2F %.2F l S', $x1, $y1, $x2, $y2));
    }

    private function emit(string $operation): void
    {
        $this->pages[count($this->pages) - 1][] = $operation;
    }

    public static function textWidth(string $text, int $size, bool $bold = false): float
    {
        $table = $bold ? self::HELVETICA_BOLD_WIDTHS : self::HELVETICA_WIDTHS;
        $total = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($text[$i]);
            $total += $table[$c] ?? 556;
        }

        return ($total * $size) / 1000;
    }

    public static function fit(string $text, float $maxWidth, int $size, bool $bold = false): string
    {
        $text = self::sanitize($text);
        if (self::textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        while (strlen($text) > 0 && self::textWidth($text.'...', $size, $bold) > $maxWidth) {
            $text = substr($text, 0, -1);
        }

        return strlen($text) > 0 ? $text.'...' : '';
    }

    public static function sanitize(string $text): string
    {
        $text = str_replace(
            ['৳', '’', '‘', '“', '”', '–', '—', '…'],
            ['Tk.', "'", "'", '"', '"', '-', '-', '...'],
            $text
        );
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: '';
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
