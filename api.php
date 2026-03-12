<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // --- Статус операции (restore | dump) ---
    case 'status':
        $ns = $_GET['ns'] ?? 'restore';
        echo json_encode(getStatus($ns));
        break;

    // --- Запуск восстановления ---
    case 'start_restore':
        $cmd = "php " . escapeshellarg(__DIR__ . '/worker.php') . " > /dev/null 2>&1 &";
        exec($cmd);
        echo json_encode(['success' => true]);
        break;

    // --- Запуск создания дампа ---
    case 'start_dump':
        $cmd = "php " . escapeshellarg(__DIR__ . '/dump_worker.php') . " > /dev/null 2>&1 &";
        exec($cmd);
        echo json_encode(['success' => true]);
        break;

    // --- Список готовых дампов ---
    case 'list_dumps':
        $files = glob(__DIR__ . '/dump_*.sql');
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

    // --- Скачать дамп ---
    case 'download':
        $name = basename($_GET['file'] ?? '');
        $path = __DIR__ . '/' . $name;
        if (!$name || !preg_match('/^dump_[\d_-]+\.sql$/', $name) || !file_exists($path)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            break;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        break;

    // --- Удалить дамп ---
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

