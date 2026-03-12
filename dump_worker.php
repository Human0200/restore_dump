<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

function formatSize(int $bytes): string {
    if ($bytes >= 1024 ** 3) return round($bytes / 1024 ** 3, 2) . ' GB';
    if ($bytes >= 1024 ** 2) return round($bytes / 1024 ** 2, 2) . ' MB';
    return round($bytes / 1024, 2) . ' KB';
}

function getFileSizeDebug(string $path): array {
    $debug = [];

    $out = []; exec('stat -c%s ' . escapeshellarg($path) . ' 2>&1', $out, $ret);
    $debug['stat'] = ['ret' => $ret, 'out' => implode('|', $out)];
    if ($ret === 0 && isset($out[0]) && is_numeric(trim($out[0]))) {
        return ['bytes' => (int)trim($out[0]), 'method' => 'stat', 'debug' => $debug];
    }

    $out = []; exec('wc -c < ' . escapeshellarg($path) . ' 2>&1', $out, $ret);
    $debug['wc'] = ['ret' => $ret, 'out' => implode('|', $out)];
    if ($ret === 0 && isset($out[0]) && is_numeric(trim($out[0]))) {
        return ['bytes' => (int)trim($out[0]), 'method' => 'wc', 'debug' => $debug];
    }

    $out = []; exec('du -b ' . escapeshellarg($path) . ' 2>&1', $out, $ret);
    $debug['du'] = ['ret' => $ret, 'out' => implode('|', $out)];
    if ($ret === 0 && isset($out[0])) {
        return ['bytes' => (int)trim(explode("\t", $out[0])[0]), 'method' => 'du', 'debug' => $debug];
    }

    clearstatcache(true, $path);
    $phpSize = file_exists($path) ? filesize($path) : -1;
    $debug['php'] = $phpSize;

    return ['bytes' => max(0, $phpSize), 'method' => 'php', 'debug' => $debug];
}


// ── Защита от параллельного запуска ──────────────────────────────────────────
$lockFile = __DIR__ . '/dump.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
ftruncate($lock, 0);
fwrite($lock, (string)getmypid());
fflush($lock);
register_shutdown_function(function() use ($lock, $lockFile) {
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
});
// ─────────────────────────────────────────────────────────────────────────────
$startTime = time();
$dumpFile  = __DIR__ . '/dump_' . date('Y-m-d_H-i-s') . '.sql';

updateStatus('dump', [
    'running'  => true,
    'progress' => 0,
    'size'     => '0 KB',
    'time'     => 0,
    'file'     => basename($dumpFile),
    'current'  => 'Инициализация...',
    'error'    => null,
]);

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    updateStatus('dump', ['running' => false, 'error' => $mysqli->connect_error]);
    exit(1);
}

$result      = $mysqli->query("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
$totalTables = (int)($result->fetch_assoc()['cnt'] ?? 0);

$tablesResult = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $tablesResult->fetch_row()) {
    $tables[] = $row[0];
}
$mysqli->close();

$userArg = escapeshellarg(DB_USER);
$passArg = escapeshellarg(DB_PASS);
$hostArg = escapeshellarg(DB_HOST);
$dbArg   = escapeshellarg(DB_NAME);
$outArg  = escapeshellarg($dumpFile);

file_put_contents($dumpFile,
    "-- Dump created: " . date('Y-m-d H:i:s') . "\n" .
    "-- Database: " . DB_NAME . "\n\n" .
    "SET FOREIGN_KEY_CHECKS = 0;\n" .
    "SET UNIQUE_CHECKS = 0;\n\n"
);

$done = 0;
foreach ($tables as $table) {
    $tableArg = escapeshellarg($table);
    $cmd = "mysqldump -h {$hostArg} -u {$userArg} -p{$passArg} "
         . "--single-transaction --quick --no-tablespaces "
         . "{$dbArg} {$tableArg} >> {$outArg} 2>/dev/null";
    exec($cmd, $cmdOut, $ret);

    $done++;
    $progress = round(($done / $totalTables) * 100, 2);
    $sizeInfo = getFileSizeDebug($dumpFile);

    updateStatus('dump', [
        'running'  => true,
        'progress' => $progress,
        'size'     => formatSize($sizeInfo['bytes']),
        'size_raw' => $sizeInfo['bytes'],
        'time'     => time() - $startTime,
        'file'     => basename($dumpFile),
        'current'  => "Таблица {$done}/{$totalTables}: {$table}",
        'error'    => $ret !== 0 ? "Ошибка дампа таблицы: {$table}" : null,
        'debug'    => $done <= 5 ? $sizeInfo : null,
    ]);
}

$finalInfo = getFileSizeDebug($dumpFile);

updateStatus('dump', [
    'running'  => false,
    'progress' => 100,
    'size'     => formatSize($finalInfo['bytes']),
    'size_raw' => $finalInfo['bytes'],
    'time'     => time() - $startTime,
    'file'     => basename($dumpFile),
    'current'  => 'Завершено',
    'error'    => null,
    'debug'    => $finalInfo,
]);