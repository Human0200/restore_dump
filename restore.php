<?php
// Параметры подключения
define('DB_HOST', 'localhost');
define('DB_USER', 'bitrix0');
define('DB_PASS', '+}CD{9e_&q2r-jK-vTdJ');
define('DB_NAME', 'sitemanager');
define('DUMP_FILE', __DIR__ . '/clean_dump.sql');
define('STATUS_FILE', __DIR__ . '/restore_status.json');

// Функция для обновления статуса
function updateStatus($data) {
    file_put_contents(STATUS_FILE, json_encode($data));
}

// Функция для получения статуса
function getStatus() {
    if (file_exists(STATUS_FILE)) {
        return json_decode(file_get_contents(STATUS_FILE), true);
    }
    return null;
}

// Если это AJAX запрос на получение статуса
if (isset($_GET['ajax']) && $_GET['ajax'] == 'status') {
    header('Content-Type: application/json');
    echo json_encode(getStatus());
    exit;
}

// Если это запрос на запуск восстановления
if (isset($_POST['start'])) {
    // Запускаем в фоне
    $cmd = "php " . __FILE__ . " --restore > /dev/null 2>&1 &";
    exec($cmd);
    echo json_encode(['success' => true]);
    exit;
}

// Если это CLI режим (реальное восстановление)
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === '--restore') {
    // Начинаем восстановление
    $startTime = time();
    $totalSize = filesize(DUMP_FILE);
    
    updateStatus([
        'running' => true,
        'progress' => 0,
        'tables' => 0,
        'size' => 0,
        'time' => 0,
        'current' => 'Инициализация...',
        'errors' => []
    ]);
    
    // Подключение к MySQL
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($mysqli->connect_error) {
        updateStatus(['error' => $mysqli->connect_error]);
        exit;
    }
    
    // Создаем/выбираем базу
    $mysqli->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $mysqli->select_db(DB_NAME);
    
    // Оптимизация
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
    $mysqli->query("SET UNIQUE_CHECKS = 0");
    $mysqli->query("SET AUTOCOMMIT = 0");
    
    // Читаем файл
    $handle = fopen(DUMP_FILE, 'r');
    $query = '';
    $lineNumber = 0;
    $totalQueries = 0;
    $tablesDone = 0;
    
    while (!feof($handle)) {
        $line = fgets($handle);
        $lineNumber++;
        
        // Обновляем прогресс каждые 1000 строк
        if ($lineNumber % 1000 == 0) {
            $pos = ftell($handle);
            $progress = round(($pos / $totalSize) * 100, 2);
            
            // Получаем размер базы
            $result = $mysqli->query("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 2) as size_gb
                FROM information_schema.tables 
                WHERE table_schema = '" . DB_NAME . "'
            ");
            $size = $result->fetch_assoc()['size_gb'] ?? 0;
            
            // Считаем таблицы
            $result = $mysqli->query("
                SELECT COUNT(*) as cnt
                FROM information_schema.tables 
                WHERE table_schema = '" . DB_NAME . "'
            ");
            $tables = $result->fetch_assoc()['cnt'] ?? 0;
            
            updateStatus([
                'running' => true,
                'progress' => $progress,
                'tables' => $tables,
                'size' => $size,
                'time' => time() - $startTime,
                'current' => "Обработано строк: " . number_format($lineNumber),
                'errors' => []
            ]);
        }
        
        // Пропускаем комментарии
        if (strlen(trim($line)) == 0 || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
            continue;
        }
        
        $query .= $line;
        
        // Если нашли конец запроса
        if (substr(trim($line), -1) == ';') {
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
            ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 2) as size_gb,
            COUNT(*) as tables
        FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "'
    ");
    $final = $result->fetch_assoc();
    
    updateStatus([
        'running' => false,
        'progress' => 100,
        'tables' => $final['tables'],
        'size' => $final['size_gb'],
        'time' => time() - $startTime,
        'current' => 'Завершено!',
        'errors' => $errors ?? []
    ]);
    
    exit;
}

// HTML интерфейс с AJAX
?>
<!DOCTYPE html>
<html>
<head>
    <title>Восстановление БД с прогрессом</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }
        .info {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .progress-container {
            margin: 30px 0;
            display: none;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
            width: 0%;
            position: relative;
            z-index: 1;
        }
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #333;
            font-weight: bold;
            text-shadow: 1px 1px 0 rgba(255,255,255,0.5);
            z-index: 2;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .log {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 10px;
            font-family: monospace;
            height: 200px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .log-line {
            margin: 2px 0;
            font-size: 12px;
        }
        .button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .error {
            color: #ff4444;
            background: #ffeeee;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Восстановление базы данных</h1>
        
        <div class="info">
            <strong>📊 Информация о дампе:</strong><br>
            Файл: <?= basename(DUMP_FILE) ?><br>
            Размер: <?php 
                if (file_exists(DUMP_FILE)) {
                    $size = filesize(DUMP_FILE);
                    echo round($size/1024/1024/1024, 2) . " GB";
                } else {
                    echo "<span style='color:red'>Файл не найден!</span>";
                }
            ?><br>
            База: <?= DB_NAME ?>
        </div>

        <button class="button" onclick="startRestore()" id="startBtn">
            🚀 Запустить восстановление
        </button>

        <div class="progress-container" id="progressContainer">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
                <div class="progress-text" id="progressPercent">0%</div>
            </div>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value" id="statSize">0</div>
                    <div class="stat-label">Размер БД (GB)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statTables">0</div>
                    <div class="stat-label">Таблиц</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statTime">0</div>
                    <div class="stat-label">Время (мин)</div>
                </div>
            </div>

            <div class="log" id="log">
                <div class="log-line">Ожидание запуска...</div>
            </div>
        </div>
    </div>

    <script>
        let updateInterval;

        function startRestore() {
            const startBtn = document.getElementById('startBtn');
            startBtn.disabled = true;
            startBtn.textContent = '⏳ Запуск...';
            
            document.getElementById('progressContainer').style.display = 'block';
            
            fetch('?', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'start=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLog('✅ Восстановление запущено!');
                    updateInterval = setInterval(updateStatus, 2000);
                } else {
                    addLog('❌ Ошибка запуска');
                    startBtn.disabled = false;
                }
            });
        }

        function updateStatus() {
            fetch('?ajax=status')
            .then(response => response.json())
            .then(data => {
                if (!data) return;
                
                // Обновляем прогресс
                const progress = data.progress || 0;
                document.getElementById('progressFill').style.width = progress + '%';
                document.getElementById('progressPercent').textContent = progress + '%';
                
                // Обновляем статистику
                document.getElementById('statSize').textContent = data.size || '0';
                document.getElementById('statTables').textContent = data.tables || '0';
                document.getElementById('statTime').textContent = Math.round((data.time || 0) / 60);
                
                // Добавляем в лог
                if (data.current) {
                    addLog('📌 ' + data.current);
                }
                
                // Если завершено
                if (!data.running && data.progress === 100) {
                    clearInterval(updateInterval);
                    addLog('✅ Восстановление ЗАВЕРШЕНО!');
                    addLog(`📊 Итог: ${data.size} GB, ${data.tables} таблиц`);
                    document.getElementById('startBtn').disabled = false;
                    document.getElementById('startBtn').textContent = '🚀 Запустить заново';
                }
                
                // Ошибки
                if (data.errors && data.errors.length > 0) {
                    data.errors.forEach(err => addLog('❌ ' + err));
                }
            });
        }

        function addLog(message) {
            const log = document.getElementById('log');
            const time = new Date().toLocaleTimeString();
            log.innerHTML += `<div class="log-line">[${time}] ${message}</div>`;
            log.scrollTop = log.scrollHeight;
        }
    </script>
</body>
</html>