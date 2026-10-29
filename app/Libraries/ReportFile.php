<?php

namespace App\Libraries;

class ReportFile
{
    /**
     * @param array<string, array{headers: list<string>, rows: list<list<string|int|float>>}> $sheets
     */
    public static function spreadsheet(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';

        foreach ($sheets as $name => $sheet) {
            $xml .= '<Worksheet ss:Name="' . self::sheetName((string) $name) . '"><Table>';
            $xml .= '<Row>';

            foreach ($sheet['headers'] as $header) {
                $xml .= '<Cell><Data ss:Type="String">' . self::xml((string) $header) . '</Data></Cell>';
            }

            $xml .= '</Row>';

            foreach ($sheet['rows'] as $row) {
                $xml .= '<Row>';

                foreach ($row as $cell) {
                    $type = is_int($cell) || is_float($cell) ? 'Number' : 'String';
                    $xml .= '<Cell><Data ss:Type="' . $type . '">' . self::xml((string) $cell) . '</Data></Cell>';
                }

                $xml .= '</Row>';
            }

            $xml .= '</Table></Worksheet>';
        }

        return $xml . '</Workbook>';
    }

    /**
     * @param list<array{title: string, rows: list<array{label: string, value: string, bar: int}>}> $sections
     */
    public static function pdf(string $title, string $note, array $sections): string
    {
        $pages = self::pages($title, $note, $sections);
        $font1 = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>';
        $font2 = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>';
        $kids = [];
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '',
            $font1,
            $font2,
        ];

        foreach ($pages as $stream) {
            $pageId = count($objects) + 1;
            $contentId = $pageId + 1;
            $kids[] = $pageId . ' 0 R';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ' . $contentId . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>';
            $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $objects[1] = '<< /Type /Pages /Count ' . count($pages) . ' /Kids [' . implode(' ', $kids) . '] >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $body) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }

        $start = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        return $pdf . "trailer << /Size $count /Root 1 0 R >>\nstartxref\n$start\n%%EOF";
    }

    /**
     * @param list<array{title: string, rows: list<array{label: string, value: string, bar: int}>}> $sections
     * @return list<string>
     */
    private static function pages(string $title, string $note, array $sections): array
    {
        $pages = [];
        $commands = [];
        $y = 800;
        $pageNo = 1;
        $open = static function () use (&$commands, &$y, $title, &$pageNo): void {
            $commands = [];
            $y = 800;
            $commands[] = '0.12 0.42 0.35 rg';
            $commands[] = self::text(36, $y, 16, 'F2', $title);
            $commands[] = '0.35 0.33 0.30 rg';
            $commands[] = self::text(430, $y, 10, 'F1', 'Page ' . $pageNo);
            $y -= 18;
            $commands[] = '0.78 0.76 0.72 RG';
            $commands[] = '36 ' . $y . ' m 559 ' . $y . ' l S';
            $y -= 22;
        };
        $open();
        $commands[] = '0.35 0.33 0.30 rg';
        $commands[] = self::text(36, $y, 10, 'F1', $note);
        $y -= 26;

        foreach ($sections as $section) {
            if ($y < 120) {
                $pages[] = implode("\n", $commands);
                $pageNo++;
                $open();
            }

            $commands[] = '0.16 0.15 0.13 rg';
            $commands[] = self::text(36, $y, 13, 'F2', (string) $section['title']);
            $y -= 18;
            $rows = $section['rows'];
            $max = 1;

            foreach ($rows as $row) {
                $max = max($max, (int) $row['bar']);
            }

            if ($rows === []) {
                $commands[] = '0.35 0.33 0.30 rg';
                $commands[] = self::text(36, $y, 10, 'F1', 'Nothing to show.');
                $y -= 22;
                continue;
            }

            foreach ($rows as $row) {
                if ($y < 56) {
                    $pages[] = implode("\n", $commands);
                    $pageNo++;
                    $open();
                    $commands[] = '0.16 0.15 0.13 rg';
                    $commands[] = self::text(36, $y, 13, 'F2', (string) $section['title'] . ' continued');
                    $y -= 18;
                }

                $bar = max(0, (int) round(((int) $row['bar']) / $max * 150));
                $commands[] = '0.16 0.15 0.13 rg';
                $commands[] = self::text(36, $y, 10, 'F1', self::clip((string) $row['label'], 28));
                $commands[] = self::text(430, $y, 10, 'F1', self::clip((string) $row['value'], 22));

                if ($bar > 0) {
                    $commands[] = '0.12 0.42 0.35 rg';
                    $commands[] = '250 ' . ($y - 2) . ' ' . $bar . ' 8 re f';
                }

                $y -= 16;
            }

            $y -= 12;
        }

        $pages[] = implode("\n", $commands);

        return $pages;
    }

    private static function text(int $x, int $y, int $size, string $font, string $value): string
    {
        return "BT /$font $size Tf 1 0 0 1 $x $y Tm (" . self::pdfText($value) . ') Tj ET';
    }

    /**
     * @param array<string, mixed> $board
     * @return array<string, array{headers: list<string>, rows: list<list<string|int|float>>}>
     */
    public static function sheets(array $board): array
    {
        return [
            'Lead sources' => [
                'headers' => ['Source', 'Leads'],
                'rows'    => array_map(static fn (array $row): array => [(string) ($row['label'] ?? ''), (int) ($row['total'] ?? 0)], $board['sources'] ?? []),
            ],
            'Lead status' => [
                'headers' => ['Status', 'Leads'],
                'rows'    => array_map(static fn (array $row): array => [(string) ($row['label'] ?? ''), (int) ($row['total'] ?? 0)], $board['funnel'] ?? []),
            ],
            'Staff' => [
                'headers' => ['Person', 'Leads', 'Follow-ups', 'Overdue', 'Visits', 'Bookings'],
                'rows'    => array_map(static fn (array $row): array => [$row['name'], $row['leads'], $row['followups'], $row['overdue'], $row['visits'], $row['bookings']], $board['staff'] ?? []),
            ],
            'Teams' => [
                'headers' => ['Team', 'Leads', 'Follow-ups', 'Visits', 'Bookings'],
                'rows'    => array_map(static fn (array $row): array => [$row['name'], $row['leads'], $row['followups'], $row['visits'], $row['bookings']], $board['teams'] ?? []),
            ],
            'Site visits' => [
                'headers' => ['Status', 'Visits'],
                'rows'    => array_map(static fn (array $row): array => [ucfirst(str_replace('_', ' ', (string) ($row['status'] ?? ''))), (int) ($row['total'] ?? 0)], $board['visit_status'] ?? []),
            ],
            'Projects' => [
                'headers' => ['Project', 'Available', 'Hold', 'Booked', 'Sold', 'Registered', 'Blocked', 'Sales', 'Sale value'],
                'rows'    => array_map(static fn (array $row): array => [
                    $row['name'], $row['available'], $row['hold'], $row['booked'], $row['sold'], $row['registered'], $row['blocked'], $row['sales'], ((int) $row['sale_value_minor']) / 100,
                ], $board['projects'] ?? []),
            ],
            'Conversion' => [
                'headers' => ['Stage', 'Count'],
                'rows'    => [
                    ['Leads', (int) ($board['conversion']['leads'] ?? 0)],
                    ['Visited', (int) ($board['conversion']['visited'] ?? 0)],
                    ['Booked', (int) ($board['conversion']['booked'] ?? 0)],
                ],
            ],
            'Follow-ups' => [
                'headers' => ['State', 'Count'],
                'rows'    => [
                    ['Done', (int) ($board['follow_split']['done'] ?? 0)],
                    ['Upcoming', (int) ($board['follow_split']['upcoming'] ?? 0)],
                    ['Overdue', (int) ($board['follow_split']['overdue'] ?? 0)],
                ],
            ],
            'Attendance' => [
                'headers' => ['Status', 'Days'],
                'rows'    => array_map(static fn (array $row): array => [ucfirst((string) ($row['status'] ?? '')), (int) ($row['total'] ?? 0)], $board['attendance'] ?? []),
            ],
            'Incentives' => [
                'headers' => ['Status', 'Count', 'Amount'],
                'rows'    => array_map(static fn (array $row): array => [(string) ($row['status'] ?? ''), (int) ($row['total'] ?? 0), ((int) ($row['amount_minor'] ?? 0)) / 100], $board['incentives'] ?? []),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $board
     * @return list<array{title: string, rows: list<array{label: string, value: string, bar: int}>}>
     */
    public static function sections(array $board): array
    {
        $money = static fn (int $minor): string => DeskService::money($minor, 'INR');

        return [
            self::section('Lead sources', $board['sources'] ?? [], 'label', 'total'),
            self::section('Lead status funnel', $board['funnel'] ?? [], 'label', 'total'),
            [
                'title' => 'Staff performance',
                'rows'  => array_map(static fn (array $row): array => [
                    'label' => (string) $row['name'],
                    'value' => $row['leads'] . ' leads, ' . $row['bookings'] . ' booked',
                    'bar'   => (int) $row['leads'],
                ], $board['staff'] ?? []),
            ],
            [
                'title' => 'Team performance',
                'rows'  => array_map(static fn (array $row): array => [
                    'label' => (string) $row['name'],
                    'value' => $row['leads'] . ' leads, ' . $row['bookings'] . ' booked',
                    'bar'   => (int) $row['leads'],
                ], $board['teams'] ?? []),
            ],
            self::named('Site visits', $board['visit_status'] ?? [], 'status'),
            [
                'title' => 'Project sales',
                'rows'  => array_map(static fn (array $row): array => [
                    'label' => (string) $row['name'],
                    'value' => $row['sales'] . ' / ' . $money((int) $row['sale_value_minor']),
                    'bar'   => (int) $row['sales'],
                ], $board['projects'] ?? []),
            ],
            [
                'title' => 'Conversion',
                'rows'  => [
                    ['label' => 'Leads', 'value' => (string) ($board['conversion']['leads'] ?? 0), 'bar' => (int) ($board['conversion']['leads'] ?? 0)],
                    ['label' => 'Visited', 'value' => (string) ($board['conversion']['visited'] ?? 0), 'bar' => (int) ($board['conversion']['visited'] ?? 0)],
                    ['label' => 'Booked', 'value' => (string) ($board['conversion']['booked'] ?? 0), 'bar' => (int) ($board['conversion']['booked'] ?? 0)],
                ],
            ],
            [
                'title' => 'Follow-up compliance',
                'rows'  => [
                    ['label' => 'Done', 'value' => (string) ($board['follow_split']['done'] ?? 0), 'bar' => (int) ($board['follow_split']['done'] ?? 0)],
                    ['label' => 'Upcoming', 'value' => (string) ($board['follow_split']['upcoming'] ?? 0), 'bar' => (int) ($board['follow_split']['upcoming'] ?? 0)],
                    ['label' => 'Overdue', 'value' => (string) ($board['follow_split']['overdue'] ?? 0), 'bar' => (int) ($board['follow_split']['overdue'] ?? 0)],
                ],
            ],
            self::named('Attendance this month', $board['attendance'] ?? [], 'status'),
            [
                'title' => 'Incentives',
                'rows'  => array_map(static fn (array $row): array => [
                    'label' => ucfirst(str_replace('_', ' ', (string) ($row['status'] ?? ''))),
                    'value' => $money((int) ($row['amount_minor'] ?? 0)),
                    'bar'   => (int) ($row['amount_minor'] ?? 0),
                ], $board['incentives'] ?? []),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{title: string, rows: list<array{label: string, value: string, bar: int}>}
     */
    private static function section(string $title, array $rows, string $labelKey, string $valueKey): array
    {
        return [
            'title' => $title,
            'rows'  => array_map(static fn (array $row): array => [
                'label' => (string) ($row[$labelKey] ?? ''),
                'value' => (string) (int) ($row[$valueKey] ?? 0),
                'bar'   => (int) ($row[$valueKey] ?? 0),
            ], $rows),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{title: string, rows: list<array{label: string, value: string, bar: int}>}
     */
    private static function named(string $title, array $rows, string $labelKey): array
    {
        return [
            'title' => $title,
            'rows'  => array_map(static fn (array $row): array => [
                'label' => ucfirst(str_replace('_', ' ', (string) ($row[$labelKey] ?? ''))),
                'value' => (string) (int) ($row['total'] ?? 0),
                'bar'   => (int) ($row['total'] ?? 0),
            ], $rows),
        ];
    }

    private static function pdfText(string $value): string
    {
        $value = str_replace(['₹', '·'], ['Rs. ', '-'], $value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii === false ? $value : $ascii;
        $value = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private static function clip(string $value, int $length): string
    {
        $value = str_replace(['₹', '·'], ['Rs. ', '-'], $value);

        if (strlen($value) <= $length) {
            return $value;
        }

        return substr($value, 0, $length - 3) . '...';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function sheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name) ?? $name;
        $name = trim($name);

        return htmlspecialchars(substr($name === '' ? 'Sheet' : $name, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
