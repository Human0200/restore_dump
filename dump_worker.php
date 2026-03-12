<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

$startTime = time();
$dumpFile  = __DIR__ . '/dump_' . date('Y-m-d_H-i-s') . '.sql';

updateStatus('dump', [
    'running'  => true,
    'progress' => 0,
    'size'     => 0,
    'time'     => 0,
    'file'     => basename($dumpFile),
    'current'  => 'Инициализация...',
    'error'    => null,
]);

// Считаем общий объём таблиц для прогресса
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    updateStatus('dump', ['running' => false, 'error' => $mysqli->connect_error]);
    exit(1);
}

$result = $mysqli->query("
    SELECT COUNT(*) AS cnt,
           ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_mb
    FROM information_schema.tables
    WHERE table_schema = '" . DB_NAME . "'
");
$meta       = $result->fetch_assoc();
$totalMb    = (float)($meta['total_mb'] ?? 1);
$totalTables = (int)($meta['cnt'] ?? 0);
$mysqli->close();

// Получаем список таблиц
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$tablesResult = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $tablesResult->fetch_row()) {
    $tables[] = $row[0];
}
$mysqli->close();

// Собираем аргументы для mysqldump
$userArg = escapeshellarg(DB_USER);
$passArg = escapeshellarg(DB_PASS);
$hostArg = escapeshellarg(DB_HOST);
$dbArg   = escapeshellarg(DB_NAME);
$outArg  = escapeshellarg($dumpFile);

// Дампим таблицу за таблицей, обновляя прогресс
$done = 0;
$handle = fopen($dumpFile, 'w');
fwrite($handle, "-- Dump created: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "-- Database: " . DB_NAME . "\n\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($handle, "SET UNIQUE_CHECKS = 0;\n\n");
fclose($handle);

foreach ($tables as $table) {
    $tableArg = escapeshellarg($table);
    $cmd = "mysqldump -h {$hostArg} -u {$userArg} -p{$passArg} --single-transaction --quick "
         . "--no-tablespaces {$dbArg} {$tableArg} >> {$outArg} 2>/dev/null";
    exec($cmd, $out, $ret);

    $done++;
    $progress = round(($done / $totalTables) * 100, 2);
    $sizeMb   = file_exists($dumpFile) ? round(filesize($dumpFile) / 1024 / 1024, 2) : 0;

    updateStatus('dump', [
        'running'  => true,
        'progress' => $progress,
        'size'     => $sizeMb,
        'time'     => time() - $startTime,
        'file'     => basename($dumpFile),
        'current'  => "Таблица {$done}/{$totalTables}: {$table}",
        'error'    => $ret !== 0 ? "Ошибка дампа таблицы: {$table}" : null,
    ]);
}

// Финал
$finalSizeMb = file_exists($dumpFile) ? round(filesize($dumpFile) / 1024 / 1024, 2) : 0;

updateStatus('dump', [
    'running'  => false,
    'progress' => 100,
    'size'     => $finalSizeMb,
    'time'     => time() - $startTime,
    'file'     => basename($dumpFile),
    'current'  => 'Завершено',
    'error'    => null,
]);
