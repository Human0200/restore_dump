<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Проверка и восстановление таблиц</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Проверка и восстановление таблиц</h1>

    <div class="db-info">
        <span>База: <strong><?= DB_NAME ?></strong></span>
        <span>Хост: <strong><?= DB_HOST ?></strong></span>
    </div>

    <div class="actions">
        <div class="action-card">
            <div class="action-title">CHECK TABLE</div>
            <div class="action-desc">Проверяет все таблицы на ошибки. Повреждённые будут показаны в списке ниже. Данные не изменяются.</div>
            <button class="btn btn-secondary" id="checkBtn" onclick="start('check')">Запустить проверку</button>
        </div>
        <div class="action-card action-card--warn">
            <div class="action-title">CHECK + REPAIR TABLE</div>
            <div class="action-desc">Проверяет все таблицы и автоматически восстанавливает повреждённые. MyISAM — REPAIR TABLE, InnoDB — ALTER TABLE ENGINE=InnoDB.</div>
            <button class="btn btn-danger" id="repairBtn" onclick="start('repair')">Проверить и восстановить</button>
        </div>
    </div>

    <div class="operation-block" id="opBlock">
        <div class="progress-wrap">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
                <span class="progress-label" id="progressLabel">0%</span>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-val" id="statDone">0</div>
                <div class="stat-name">Проверено</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" id="statTotal">0</div>
                <div class="stat-name">Всего таблиц</div>
            </div>
            <div class="stat-card stat-card--warn">
                <div class="stat-val" id="statBroken">0</div>
                <div class="stat-name">Повреждено</div>
            </div>
            <div class="stat-card stat-card--ok">
                <div class="stat-val" id="statRepaired">0</div>
                <div class="stat-name">Восстановлено</div>
            </div>
            <div class="stat-card stat-card--err">
                <div class="stat-val" id="statFailed">0</div>
                <div class="stat-name">Не удалось</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" id="statTime">0</div>
                <div class="stat-name">Время (сек)</div>
            </div>
        </div>

        <div class="current-line" id="currentLine">Ожидание...</div>

        <div id="resultsWrap" style="display:none">
            <div class="results-header">
                <span id="resultsTitle">Повреждённые таблицы</span>
                <button class="btn btn-secondary btn-sm" onclick="exportLog()">Скачать отчёт</button>
            </div>
            <table class="results-table" id="resultsTable">
                <thead>
                    <tr>
                        <th>Таблица</th>
                        <th>Движок</th>
                        <th>Ошибка CHECK</th>
                        <th>Результат REPAIR</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>
