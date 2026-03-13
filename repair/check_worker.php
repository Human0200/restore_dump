<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

// ── Защита от параллельного запуска ──────────────────────────────────────────
$lockFile = __DIR__ . '/check.lock';
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
$mode      = $argv[1] ?? 'check'; // check | repair

updateStatus('check', [
    'running'  => true,
    'progress' => 0,
    'time'     => 0,
    'current'  => 'Получение списка таблиц...',
    'total'    => 0,
    'done'     => 0,
    'broken'   => 0,
    'repaired' => 0,
    'failed'   => 0,
    'mode'     => $mode,
    'log'      => [],
]);

// Явный таймаут подключения через mysqli_init + options
$mysqli = mysqli_init();
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    updateStatus('check', [
        'running' => false,
        'error'   => 'Ошибка подключения: ' . $mysqli->connect_error,
        'debug'   => [
            'host' => DB_HOST,
            'user' => DB_USER,
            'db'   => DB_NAME,
            'errno' => $mysqli->connect_errno,
        ],
    ]);
    exit(1);
}

// Получаем таблицы — используем явные алиасы в нижнем регистре
// и фильтруем пустые имена на случай неожиданного поведения драйвера
$result = $mysqli->query("
    SELECT TABLE_NAME AS tname, ENGINE AS tengine
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
    ORDER BY TABLE_NAME
");

if (!$result) {
    updateStatus('check', [
        'running' => false,
        'error'   => 'Ошибка запроса к information_schema: ' . $mysqli->error,
    ]);
    exit(1);
}

$tables = [];
while ($row = $result->fetch_assoc()) {
    $tname = trim($row['tname'] ?? '');
    if ($tname === '') continue; // пропускаем пустые
    $tables[] = ['table_name' => $tname, 'engine' => $row['tengine'] ?? ''];
}

if (empty($tables)) {
    updateStatus('check', [
        'running' => false,
        'error'   => 'Таблицы не найдены в базе "' . DB_NAME . '". Проверьте имя базы в config.php.',
    ]);
    exit(1);
}

$total    = count($tables);
$done     = 0;
$broken   = 0;
$repaired = 0;
$failed   = 0;
$log      = [];

foreach ($tables as $tbl) {
    $name   = $tbl['table_name'];
    $engine = strtoupper($tbl['engine'] ?? '');
    $done++;
    $progress = round(($done / $total) * 100, 2);

    // CHECK TABLE
    $res  = $mysqli->query("CHECK TABLE `{$name}`");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $last    = end($rows);
    $msgType = strtolower($last['Msg_type'] ?? '');
    $msgText = $last['Msg_text'] ?? '';
    $isOk    = ($msgType === 'status' && strtolower($msgText) === 'ok');

    // Здоровая таблица — только обновляем прогресс, не логируем
    if ($isOk) {
        if ($done % 10 === 0 || $done === $total) {
            updateStatus('check', [
                'running'  => true,
                'progress' => $progress,
                'time'     => time() - $startTime,
                'current'  => "Проверка {$done}/{$total}: {$name}",
                'total'    => $total,
                'done'     => $done,
                'broken'   => $broken,
                'repaired' => $repaired,
                'failed'   => $failed,
                'mode'     => $mode,
                'log'      => $log,
            ]);
        }
        continue;
    }

    // Таблица повреждена
    $broken++;
    $entry = [
        'table'  => $name,
        'engine' => $engine,
        'check'  => $msgText,
        'repair' => null,
        'status' => 'broken',
    ];

    if ($mode === 'repair') {
        if (in_array($engine, ['MYISAM', 'ARCHIVE', 'CSV'])) {
            // Нативный REPAIR
            $res2  = $mysqli->query("REPAIR TABLE `{$name}`");
            $rows2 = [];
            while ($r = $res2->fetch_assoc()) $rows2[] = $r;
            $last2   = end($rows2);
            $repText = $last2['Msg_text'] ?? '';
            $repOk   = (strtolower($repText) === 'ok');
        } else {
            // InnoDB — перестраиваем через ALTER TABLE
            $mysqli->query("ALTER TABLE `{$name}` ENGINE=InnoDB");
            $repOk   = !$mysqli->errno;
            $repText = $repOk ? 'Перестроена (ALTER ENGINE=InnoDB)' : $mysqli->error;
        }
        $entry['repair'] = $repText;
        $entry['status'] = $repOk ? 'repaired' : 'failed';
        if ($repOk) $repaired++;
        else        $failed++;
    }

    $log[] = $entry;

    updateStatus('check', [
        'running'  => true,
        'progress' => $progress,
        'time'     => time() - $startTime,
        'current'  => ($mode === 'repair' ? 'Восстановление' : 'Проверка') . " {$done}/{$total}: {$name}",
        'total'    => $total,
        'done'     => $done,
        'broken'   => $broken,
        'repaired' => $repaired,
        'failed'   => $failed,
        'mode'     => $mode,
        'log'      => $log,
    ]);
}

$mysqli->close();

updateStatus('check', [
    'running'  => false,
    'progress' => 100,
    'time'     => time() - $startTime,
    'current'  => 'Завершено',
    'total'    => $total,
    'done'     => $done,
    'broken'   => $broken,
    'repaired' => $repaired,
    'failed'   => $failed,
    'mode'     => $mode,
    'log'      => $log,
]);