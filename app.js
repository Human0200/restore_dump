const API = 'api.php';

let restoreTimer = null;
let dumpTimer    = null;

// ─── Tab switching ────────────────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', t.getAttribute('onclick').includes(`'${name}'`));
    });
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.toggle('active', el.id === 'tab-' + name);
    });
    if (name === 'dumps-list') loadDumpsList();
}

// ─── Restore ──────────────────────────────────────────────────────────────────
async function startRestore() {
    const btn = document.getElementById('restoreBtn');
    btn.disabled = true;
    btn.textContent = 'Запуск...';
    showBlock('restoreBlock');

    try {
        const res  = await fetch(API + '?action=start_restore', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            log('restore', 'Восстановление запущено');
            restoreTimer = setInterval(() => pollStatus('restore'), 2000);
        } else {
            log('restore', data.error || 'Ошибка запуска', true);
            btn.disabled = false;
            btn.textContent = 'Запустить восстановление';
        }
    } catch (e) {
        log('restore', 'Сетевая ошибка: ' + e.message, true);
        btn.disabled = false;
    }
}

// ─── Dump ─────────────────────────────────────────────────────────────────────
async function startDump() {
    const btn = document.getElementById('dumpBtn');
    btn.disabled = true;
    btn.textContent = 'Запуск...';
    showBlock('dumpBlock');

    try {
        const res  = await fetch(API + '?action=start_dump', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            log('dump', 'Создание дампа запущено');
            dumpTimer = setInterval(() => pollStatus('dump'), 2000);
        } else {
            log('dump', data.error || 'Ошибка запуска', true);
            btn.disabled = false;
            btn.textContent = 'Создать дамп';
        }
    } catch (e) {
        log('dump', 'Сетевая ошибка: ' + e.message, true);
        btn.disabled = false;
    }
}

// ─── Poll status ──────────────────────────────────────────────────────────────
async function pollStatus(ns) {
    try {
        const res  = await fetch(`${API}?action=status&ns=${ns}`);
        const data = await res.json();
        if (!data) return;

        if (ns === 'restore') {
            setProgress('restore', data.progress);
            document.getElementById('restoreSize').textContent   = data.size   ?? '0';
            document.getElementById('restoreTables').textContent = data.tables ?? '0';
            document.getElementById('restoreTime').textContent   = Math.round((data.time ?? 0) / 60);
            if (data.current) log('restore', data.current);
            if (data.errors?.length) data.errors.forEach(e => log('restore', e, true));

            if (!data.running && data.progress == 100) {
                clearInterval(restoreTimer);
                log('restore', `Завершено. Размер: ${data.size} GB, таблиц: ${data.tables}`);
                const btn = document.getElementById('restoreBtn');
                btn.disabled = false;
                btn.textContent = 'Запустить заново';
            }
        }

        if (ns === 'dump') {
            setProgress('dump', data.progress);
            document.getElementById('dumpSize').textContent = data.size ?? '0';
            document.getElementById('dumpTime').textContent = data.time ?? '0';
            document.getElementById('dumpFile').textContent = data.file ?? '—';
            if (data.current) log('dump', data.current);
            if (data.error)   log('dump', data.error, true);

            if (!data.running && data.progress == 100) {
                clearInterval(dumpTimer);
                log('dump', `Завершено. Файл: ${data.file}, размер: ${data.size} MB`);
                const btn = document.getElementById('dumpBtn');
                btn.disabled = false;
                btn.textContent = 'Создать дамп';
            }
        }
    } catch (e) {
        log(ns, 'Ошибка опроса: ' + e.message, true);
    }
}

// ─── Dumps list ───────────────────────────────────────────────────────────────
async function loadDumpsList() {
    const tbody = document.getElementById('dumpsBody');
    tbody.innerHTML = '<tr><td colspan="4" class="empty-row">Загрузка...</td></tr>';

    try {
        const res   = await fetch(API + '?action=list_dumps');
        const dumps = await res.json();

        if (!dumps.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="empty-row">Дампы не найдены</td></tr>';
            return;
        }

        tbody.innerHTML = dumps.map(d => `
            <tr>
                <td>${d.name}</td>
                <td>${d.size_mb} MB</td>
                <td>${d.date}</td>
                <td>
                    <div class="action-btns">
                        <a href="${API}?action=download&file=${encodeURIComponent(d.name)}" class="btn btn-link">Скачать</a>
                        <button class="btn btn-danger" onclick="deleteDump('${d.name}')">Удалить</button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="4" class="empty-row">Ошибка загрузки: ${e.message}</td></tr>`;
    }
}

async function deleteDump(name) {
    if (!confirm(`Удалить файл ${name}?`)) return;
    const form = new URLSearchParams({ action: 'delete_dump', file: name });
    await fetch(API, { method: 'POST', body: form });
    loadDumpsList();
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function setProgress(ns, pct) {
    const p = pct ?? 0;
    document.getElementById(ns + 'ProgressFill').style.width = p + '%';
    document.getElementById(ns + 'ProgressLabel').textContent = p + '%';
}

function showBlock(id) {
    document.getElementById(id).classList.add('visible');
}

function log(ns, msg, isErr = false) {
    const logEl = document.getElementById(ns + 'Log');
    const div   = document.createElement('div');
    div.className = 'log-line' + (isErr ? ' err' : '');
    div.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    logEl.appendChild(div);
    logEl.scrollTop = logEl.scrollHeight;
}