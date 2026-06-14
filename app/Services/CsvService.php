<?php
/**
 * SynDrasi - CSV export service (UTF-8 with BOM for Greek Excel compatibility).
 */
class CsvService
{
    /**
     * Stream a CSV download and exit.
     *
     * @param string $filename e.g. 'events.csv'
     * @param array  $headers  column titles
     * @param array  $rows     array of row arrays
     */
    public static function download($filename, array $headers, array $rows)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens Greek text correctly
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }
}
