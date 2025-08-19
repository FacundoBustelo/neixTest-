// public/assets/app.js
const WS_PATH = '/ws';
const WS_URL  = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + WS_PATH;
const qs = s => document.querySelector(s);
const api = async (url, opts = {}) => {
  const res = await fetch(url, { credentials: 'include', headers: { 'Content-Type': 'application/json' }, ...opts });
  if (!res.ok) {
    let msg = res.statusText;
    try { msg = (await res.json()).error || msg; } catch {}
    throw new Error(msg);
  }
  return res.json();
};

let socket = null;
let username = null;
let instruments = [];
let configs = {};

function addNotice(type, msg, opts = {}) {
  const { duration = 0 } = opts; // ms; 0 = permanece
  const div = document.createElement('div');
  div.className = 'notice ' + (type === 'ok' ? 'ok' : type === 'error' ? 'err' : 'info');
  div.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;

  const appVisible = !document.getElementById('appSection')?.classList.contains('hidden');
  const box = appVisible ? document.getElementById('notices')
                         : document.getElementById('noticesGlobal');

  if (box) box.prepend(div);

  if (duration > 0) {
    setTimeout(() => {
      div.classList.add('fade-out');
      div.addEventListener('animationend', () => div.remove(), { once: true });
    }, duration);
  }
}

function flashRow(symbol, ok, errorMsg) {
  const tr = document.querySelector(`tr[data-row="${symbol}"]`);
  if (!tr) return;
  tr.classList.remove('sent-ok','sent-err');
  void tr.offsetWidth; // reflow
  tr.classList.add(ok ? 'sent-ok' : 'sent-err');
  if (!ok && errorMsg) addNotice('error', `${symbol}: ${errorMsg}`);
  setTimeout(() => tr.classList.remove('sent-ok','sent-err'), 4000);
}

async function initApp() {
  try {
    const user = await api('/api/whoami.php').catch(() => null);
    if (user && user.username) {
      username = user.username;
      qs('#whoami').textContent = username;
      qs('#loginSection').classList.add('hidden');
      qs('#appSection').classList.remove('hidden');
      await loadData();
      connectWS();
    }
  } catch (e) {
    addNotice('error', 'Error iniciando app: ' + e.message);
  }
}

async function loadData() {
  instruments = await api('/api/instruments.php'); // debe devolver USD/EUR/JPY/GBP
  const cfg = await api('/api/config_get.php');
  configs = {};
  cfg.forEach(c => { configs[c.symbol] = c; });
  renderTable();
}

function renderTable() {
  const tb = qs('#tbody');
  tb.innerHTML = '';
  instruments.forEach(row => {
    const cfg = configs[row.symbol] || {};
    const tr = document.createElement('tr');
    tr.setAttribute('data-row', row.symbol);
    tr.innerHTML = `
      <td>${row.symbol}</td>
      <td><span data-price="${row.symbol}">-</span></td>
      <td><span data-mqty="${row.symbol}">-</span></td>
      <td><input data-qty="${row.symbol}" type="number" step="1" min="0" value="${cfg.quantity ?? ''}" placeholder="Qty"></td>
      <td><input data-target="${row.symbol}" type="number" step="0.00001" min="0" value="${cfg.target_price ?? ''}" placeholder="Target"></td>
      <td>
        <select data-side="${row.symbol}">
          <option value="">-</option>
          <option value="buy" ${cfg.side === 'buy' ? 'selected' : ''}>buy</option>
          <option value="sell" ${cfg.side === 'sell' ? 'selected' : ''}>sell</option>
        </select>
      </td>
      <td>
        <button class="btn-save" data-save="${row.symbol}">Guardar</button>
      </td>
    `;
    tb.appendChild(tr);

    tr.querySelector('.btn-save').addEventListener('click', async () => {
      await saveOne(row.symbol);
    });
  });
}

async function saveOne(symbol) {
  const qty = qs(`[data-qty="${symbol}"]`).value;
  const target = qs(`[data-target="${symbol}"]`).value;
  const side = qs(`[data-side="${symbol}"]`).value;
  try {
    await api('/api/config_save.php', { method: 'POST', body: JSON.stringify({ symbol, quantity: qty, target_price: target, side }) });
    addNotice('ok', `Configuración guardada para ${symbol}`);
    flashRow(symbol, true);
  } catch (e) {
    addNotice('error', `Error guardando ${symbol}: ${e.message}`);
    flashRow(symbol, false, e.message);
  }
}

function connectWS() {
  try {
    if (socket && socket.readyState === 1) socket.close();
    socket = new WebSocket(WS_URL);

    socket.onopen = () => {
      addNotice('info', 'WebSocket conectado');
      socket.send(JSON.stringify({ type: 'ping' }));
    };

    socket.onmessage = (ev) => {
      let msg; try { msg = JSON.parse(ev.data); } catch { return; }

      if (msg.type === 'price_update') {
        msg.data.forEach(p => {
          const elP = document.querySelector(`[data-price="${p.symbol}"]`);
          if (elP) elP.textContent = p.price;
          const elQ = document.querySelector(`[data-mqty="${p.symbol}"]`);
          if (elQ) elQ.textContent = p.market_qty;
        });
      } else if (msg.type === 'ack_configs') {
        (msg.results || []).forEach(r => flashRow(r.symbol, !!r.ok, r.error));
        const okCount = (msg.results || []).filter(r => r.ok).length;
        const errCount = (msg.results || []).length - okCount;
        addNotice(errCount ? 'error' : 'ok', `WS: ${okCount} guardadas · ${errCount} con error`);
        const btn = qs('#sendAllBtn');
        if (btn) btn.disabled = false; // re-habilitar botón
      } else if (msg.type === 'ack') {
        addNotice('ok', `ACK: ${msg.message}`);
      } else if (msg.type === 'error') {
        addNotice('error', msg.message);
        const btn = qs('#sendAllBtn');
        if (btn) btn.disabled = false;
      }
    };

    socket.onclose = () => {
      addNotice('error', 'WebSocket desconectado');
      const btn = qs('#sendAllBtn');
      if (btn) btn.disabled = false;
    };
    socket.onerror = () => {
      addNotice('error', 'WS error');
      const btn = qs('#sendAllBtn');
      if (btn) btn.disabled = false;
    };
  } catch {
    addNotice('error', 'No se pudo abrir WS');
  }
}

qs('#loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const u = qs('#username').value.trim();
  const p = qs('#password').value;
  try {
    await api('/api/login.php', { method: 'POST', body: JSON.stringify({ username: u, password: p }) });
    addNotice('ok', 'Login OK', { duration: 3000 }); 
    await initApp();
  } catch {
    addNotice('error', 'Login inválido', { duration: 3000 }); 
  }
});

qs('#logoutBtn').addEventListener('click', async () => {
  await api('/api/logout.php', { method: 'POST' });
  location.reload();
});

qs('#sendAllBtn').addEventListener('click', async () => {
  const btn = qs('#sendAllBtn');
  if (!socket || socket.readyState !== 1) {
    addNotice('error', 'WS no conectado');
    return;
  }
  const payload = {
    type: 'send_configs',
    user: username || 'anon',
    timestamp: Date.now(),
    configs: instruments.map(r => ({
      symbol: r.symbol,
      target_price: parseFloat(qs(`[data-target="${r.symbol}"]`).value || ''),
      quantity: parseFloat(qs(`[data-qty="${r.symbol}"]`).value || ''),
      side: (qs(`[data-side="${r.symbol}"]`).value || "")
    }))
  };

  btn.disabled = true;
  try {
    addNotice('info', 'Enviando todas por WS…');
    socket.send(JSON.stringify(payload));
  } catch (e) {
    btn.disabled = false;
    addNotice('error', 'No se pudo enviar por WS: ' + e.message);
  }
});

// Botón para limpiar notificaciones (si existe en el HTML)
const clearBtn = document.getElementById('clearNoticesBtn');
if (clearBtn) {
  clearBtn.addEventListener('click', () => {
    const box = document.getElementById('notices');
    if (box) box.innerHTML = '';
  });
}

initApp();
