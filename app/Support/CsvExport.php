<?php

namespace App\Support;

use Illuminate\Http\Response;

class CsvExport
{
    /**
     * Build a CSV download response from column headings and rows.
     *
     * @param  array<int, string>  $columns
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function response(string $filename, array $columns, iterable $rows): Response
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
