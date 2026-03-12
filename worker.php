<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

$startTime = time();
$totalSize = filesize(DUMP_FILE);
$errors = [];

updateStatus('restore', [
    'running'  => true,
    'progress' => 0,
    'tables'   => 0,
    'size'     => 0,
    'time'     => 0,
    'current'  => 'Инициализация...',
    'errors'   => [],
]);

// Подключение к MySQL
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($mysqli->connect_error) {
    updateStatus('restore', ['running' => false, 'error' => $mysqli->connect_error]);
    exit(1);
}

// Создаём/выбираем базу
$mysqli->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$mysqli->select_db(DB_NAME);

// Оптимизация
$mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
$mysqli->query("SET UNIQUE_CHECKS = 0");
$mysqli->query("SET AUTOCOMMIT = 0");

// Читаем дамп построчно
$handle = fopen(DUMP_FILE, 'r');
$query = '';
$lineNumber = 0;
$totalQueries = 0;

while (!feof($handle)) {
    $line = fgets($handle);
    $lineNumber++;

    // Обновляем прогресс каждые 1000 строк
    if ($lineNumber % 1000 === 0) {
        $pos      = ftell($handle);
        $progress = round(($pos / $totalSize) * 100, 2);

        $result = $mysqli->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 2) AS size_gb
            FROM information_schema.tables
            WHERE table_schema = '" . DB_NAME . "'
        ");
        $size = $result->fetch_assoc()['size_gb'] ?? 0;

        $result = $mysqli->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.tables
            WHERE table_schema = '" . DB_NAME . "'
        ");
        $tables = $result->fetch_assoc()['cnt'] ?? 0;

        updateStatus('restore', [
            'running'  => true,
            'progress' => $progress,
            'tables'   => $tables,
            'size'     => $size,
            'time'     => time() - $startTime,
            'current'  => 'Обработано строк: ' . number_format($lineNumber),
            'errors'   => $errors,
        ]);
    }

    // Пропускаем пустые строки и комментарии
    $trimmed = trim($line);
    if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
        continue;
    }

    $query .= $line;

    // Конец запроса
    if (substr($trimmed, -1) === ';') {
        if (!$mysqli->query($query)) {
            $errors[] = "Ошибка в строке $lineNumber: " . $mysqli->error;
        }
        $totalQueries++;
        $query = '';
    }
}

fclose($handle);

$mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
$mysqli->query("COMMIT");

// Финальный статус
$result = $mysqli->query("
    SELECT
        ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 2) AS size_gb,
        COUNT(*) AS tables
    FROM information_schema.tables
    WHERE table_schema = '" . DB_NAME . "'
");
$final = $result->fetch_assoc();

updateStatus('restore', [
    'running'  => false,
    'progress' => 100,
    'tables'   => $final['tables'],
    'size'     => $final['size_gb'],
    'time'     => time() - $startTime,
    'current'  => 'Завершено',
    'errors'   => $errors,
]);
