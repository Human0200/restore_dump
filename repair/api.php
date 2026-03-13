<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/status.php';

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    case 'status':
        echo json_encode(getStatus('check'));
        break;

    case 'start_check':
        if (file_exists(__DIR__ . '/check.lock')) {
            echo json_encode(['success' => false, 'error' => 'Проверка уже запущена']);
            break;
        }
        $errLog = escapeshellarg(__DIR__ . '/check_worker.log');
        $cmd = "php " . escapeshellarg(__DIR__ . '/check_worker.php') . " check > {$errLog} 2>&1 &";
        exec($cmd);
        echo json_encode(['success' => true]);
        break;

    case 'start_repair':
        if (file_exists(__DIR__ . '/check.lock')) {
            echo json_encode(['success' => false, 'error' => 'Операция уже запущена']);
            break;
        }
        $errLog = escapeshellarg(__DIR__ . '/check_worker.log');
        $cmd = "php " . escapeshellarg(__DIR__ . '/check_worker.php') . " repair > {$errLog} 2>&1 &";
        exec($cmd);
        echo json_encode(['success' => true]);
        break;

    // Получить содержимое лога воркера для отладки
    case 'worker_log':
        $logFile = __DIR__ . '/check_worker.log';
        echo json_encode(['log' => file_exists($logFile) ? file_get_contents($logFile) : 'Файл лога не найден']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}