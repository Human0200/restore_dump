const API = 'api.php';
let timer = null;
let fullLog = [];

async function start(mode) {
    const checkBtn  = document.getElementById('checkBtn');
    const repairBtn = document.getElementById('repairBtn');
    checkBtn.disabled  = true;
    repairBtn.disabled = true;

    const block = document.getElementById('opBlock');
    block.classList.add('visible');
    document.getElementById('resultsWrap').style.display = 'none';
    document.getElementById('resultsBody').innerHTML = '';
    fullLog = [];

    const action = mode === 'repair' ? 'start_repair' : 'start_check';
    try {
        const res  = await fetch(`${API}?action=${action}`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            setCurrentLine(mode === 'repair' ? 'Проверка и восстановление запущены...' : 'Проверка запущена...');
            timer = setInterval(pollStatus, 2000);
        } else {
            setCurrentLine('Ошибка: ' + (data.error || 'неизвестная ошибка'));
            checkBtn.disabled  = false;
            repairBtn.disabled = false;
        }
    } catch (e) {
        setCurrentLine('Сетевая ошибка: ' + e.message);
        checkBtn.disabled  = false;
        repairBtn.disabled = false;
    }
}

async function pollStatus() {
    try {
        const res  = await fetch(`${API}?action=status`);
        const data = await res.json();
        if (!data) return;

        // Прогресс
        const p = data.progress ?? 0;
        document.getElementById('progressFill').style.width = p + '%';
        document.getElementById('progressLabel').textContent = p + '%';

        // Статы
        document.getElementById('statDone').textContent     = data.done     ?? 0;
        document.getElementById('statTotal').textContent    = data.total    ?? 0;
        document.getElementById('statBroken').textContent   = data.broken   ?? 0;
        document.getElementById('statRepaired').textContent = data.repaired ?? 0;
        document.getElementById('statFailed').textContent   = data.failed   ?? 0;
        document.getElementById('statTime').textContent     = data.time     ?? 0;

        setCurrentLine(data.current ?? '');

        // Обновляем таблицу повреждённых
        if (data.log?.length) {
            fullLog = data.log;
            renderTable(data.log, data.mode);
        }

        // Завершено
        if (!data.running && data.progress === 100) {
            clearInterval(timer);
            setCurrentLine('Завершено. Проверено: ' + data.done + ', повреждено: ' + data.broken +
                (data.mode === 'repair' ? ', восстановлено: ' + data.repaired + ', не удалось: ' + data.failed : ''));
            document.getElementById('checkBtn').disabled  = false;
            document.getElementById('repairBtn').disabled = false;
        }
    } catch (e) {
        setCurrentLine('Ошибка опроса: ' + e.message);
    }
}

function renderTable(log, mode) {
    const wrap = document.getElementById('resultsWrap');
    const body = document.getElementById('resultsBody');
    const title = document.getElementById('resultsTitle');

    if (!log.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    title.textContent = 'Повреждённые таблицы (' + log.length + ')';

    body.innerHTML = log.map(entry => {
        const badgeClass = entry.status === 'repaired' ? 'badge-repaired'
                         : entry.status === 'failed'   ? 'badge-failed'
                         : 'badge-broken';
        const badgeText  = entry.status === 'repaired' ? 'Восстановлена'
                         : entry.status === 'failed'   ? 'Не удалось'
                         : 'Повреждена';
        return `<tr>
            <td><code>${escHtml(entry.table)}</code></td>
            <td>${escHtml(entry.engine)}</td>
            <td style="color:#b45309">${escHtml(entry.check)}</td>
            <td>${entry.repair ? escHtml(entry.repair) : '<span style="color:#aaa">—</span>'}</td>
            <td><span class="badge ${badgeClass}">${badgeText}</span></td>
        </tr>`;
    }).join('');
}

function exportLog() {
    if (!fullLog.length) return;
    const lines = ['Таблица\tДвижок\tОшибка CHECK\tРезультат REPAIR\tСтатус'];
    fullLog.forEach(e => {
        lines.push([e.table, e.engine, e.check, e.repair ?? '', e.status].join('\t'));
    });
    const blob = new Blob([lines.join('\n')], { type: 'text/tab-separated-values' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'db_check_report_' + new Date().toISOString().slice(0,19).replace(/[T:]/g,'-') + '.tsv';
    a.click();
}

function setCurrentLine(msg) {
    document.getElementById('currentLine').textContent = msg;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
