<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Управление базой данных</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Управление базой данных</h1>

        <div class="db-info">
            <span>База: <strong><?= DB_NAME ?></strong></span>
            <span>Хост: <strong><?= DB_HOST ?></strong></span>
        </div>

        <!-- Вкладки -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('restore')">Восстановление</button>
            <button class="tab" onclick="switchTab('dump')">Создание дампа</button>
            <button class="tab" onclick="switchTab('dumps-list')">Список дампов</button>
        </div>

        <!-- Восстановление -->
        <div id="tab-restore" class="tab-content active">
            <div class="info-block">
                <div class="info-row">
                    <span class="info-label">Файл дампа</span>
                    <span class="info-val"><?= basename(DUMP_FILE) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Размер файла</span>
                    <span class="info-val"><?php
                        echo file_exists(DUMP_FILE)
                            ? round(filesize(DUMP_FILE) / 1024 / 1024 / 1024, 2) . ' GB'
                            : '<span class="error-text">Файл не найден</span>';
                    ?></span>
                </div>
            </div>

            <button class="btn btn-primary" id="restoreBtn" onclick="startRestore()">Запустить восстановление</button>

            <div class="operation-block" id="restoreBlock">
                <div class="progress-wrap">
                    <div class="progress-bar">
                        <div class="progress-fill" id="restoreProgressFill"></div>
                        <span class="progress-label" id="restoreProgressLabel">0%</span>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-val" id="restoreSize">0</div>
                        <div class="stat-name">Размер БД (GB)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" id="restoreTables">0</div>
                        <div class="stat-name">Таблиц</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" id="restoreTime">0</div>
                        <div class="stat-name">Время (мин)</div>
                    </div>
                </div>
                <div class="log" id="restoreLog"></div>
            </div>
        </div>

        <!-- Создание дампа -->
        <div id="tab-dump" class="tab-content">
            <div class="info-block">
                <div class="info-row">
                    <span class="info-label">База данных</span>
                    <span class="info-val"><?= DB_NAME ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Файл будет сохранён как</span>
                    <span class="info-val">dump_YYYY-MM-DD_HH-MM-SS.sql</span>
                </div>
            </div>

            <button class="btn btn-primary" id="dumpBtn" onclick="startDump()">Создать дамп</button>

            <div class="operation-block" id="dumpBlock">
                <div class="progress-wrap">
                    <div class="progress-bar">
                        <div class="progress-fill" id="dumpProgressFill"></div>
                        <span class="progress-label" id="dumpProgressLabel">0%</span>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-val" id="dumpSize">0</div>
                        <div class="stat-name">Размер файла (MB)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" id="dumpTime">0</div>
                        <div class="stat-name">Время (сек)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" id="dumpFile">—</div>
                        <div class="stat-name">Файл</div>
                    </div>
                </div>
                <div class="log" id="dumpLog"></div>
            </div>
        </div>

        <!-- Список дампов -->
        <div id="tab-dumps-list" class="tab-content">
            <div class="list-header">
                <h3>Сохранённые дампы</h3>
                <button class="btn btn-secondary" onclick="loadDumpsList()">Обновить</button>
            </div>
            <table class="dumps-table" id="dumpsTable">
                <thead>
                    <tr>
                        <th>Файл</th>
                        <th>Размер</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="dumpsBody">
                    <tr><td colspan="4" class="empty-row">Загрузка...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>

