<?php

function dateRangeFromRequest(): array
{
    $defaultFrom = date('Y-m-01');
    $defaultTo = date('Y-m-d');
    $from = $_GET['from'] ?? $defaultFrom;
    $to = $_GET['to'] ?? $defaultTo;

    $validDate = static function ($value): bool {
        if (!is_string($value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    };

    if (!$validDate($from) || !$validDate($to) || $from > $to) {
        throw new InvalidArgumentException('Pilih rentang tanggal yang valid.');
    }

    return [$from, $to];
}

function sendCsv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');

    if ($output === false) {
        throw new RuntimeException('Ekspor CSV tidak dapat dibuat.');
    }

    fputcsv($output, $headers, ',', '"', '');
    foreach ($rows as $row) {
        foreach ($row as &$value) {
            if (
                is_string($value) &&
                preg_match('/\A[\s]*[=+\-@]/u', $value)
            ) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $row, ',', '"', '');
    }
    fclose($output);
    exit;
}

function redirectTo(
    string $path,
    ?string $message = null,
    string $title = 'Berhasil',
    string $type = 'success'
): never
{
    if ($message !== null) {
        setFlashNotification($title, $message, $type);
    }

    header('Location: ' . BASE_URL . $path);
    exit;
}
