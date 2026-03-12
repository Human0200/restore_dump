<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

// Bitrix (prolog_before.php) запускает буферизацию и может выводить HTML до нашего кода.
// Очищаем весь вложенный буфер и выставляем правильный Content-Type.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

// Скачивание файла — бинарный вывод, особый случай
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'download') {
    $name = basename($_GET['file'] ?? '');
    $path = __DIR__ . '/' . $name;
    if (!$name || !preg_match('/^dump_[\d_-]+\.sql$/', $name) || !file_exists($path)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

switch ($action) {

    case 'status':
        $ns = $_GET['ns'] ?? 'restore';
        echo json_encode(getStatus($ns));
        break;

    case 'start_restore':
        if (file_exists(__DIR__ . '/restore.lock')) {
            echo json_encode(['success' => false, 'error' => 'Восстановление уже запущено']);
            break;
        }
        $cmd = "php " . escapeshellarg(__DIR__ . '/worker.php') . " > /dev/null 2>&1 &";
        exec($cmd);
        echo json_encode(['success' => true]);
        break;

    case 'start_dump':
        if (file_exists(__DIR__ . '/dump.lock')) {
            echo json_encode(['success' => false, 'error' => 'Создание дампа уже запущено']);
            break;
        }
        $cmd = "php " . escapeshellarg(__DIR__ . '/dump_worker.php') . " > /dev/null 2>&1 &";
        exec($cmd);
        echo json_encode(['success' => true]);
        break;

    case 'list_dumps':
        $files = glob(__DIR__ . '/dump_*.sql') ?: [];
        $dumps = [];
        foreach ($files as $f) {
            $dumps[] = [
                'name'    => basename($f),
                'size_mb' => round(filesize($f) / 1024 / 1024, 2),
                'date'    => date('d.m.Y H:i:s', filemtime($f)),
            ];
        }
        usort($dumps, fn($a, $b) => strcmp($b['date'], $a['date']));
        echo json_encode($dumps);
        break;

    case 'delete_dump':
        $name = basename($_POST['file'] ?? '');
        $path = __DIR__ . '/' . $name;
        if (!$name || !preg_match('/^dump_[\d_-]+\.sql$/', $name) || !file_exists($path)) {
            echo json_encode(['error' => 'File not found']);
            break;
        }
        unlink($path);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}