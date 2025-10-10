// public/apps/tasks/app.js

window.TasksApp = (function(){
  const API = '/apps/tasks/api/tasks.php';

  async function callMe(){
    const out = document.getElementById('out');
    try {
      const me = await WorkzSDK.apiGet('/me');
      out.textContent = JSON.stringify(me, null, 2);
    } catch (e) {
      out.textContent = 'Erro ao chamar /api/me';
    }
  }

  function authHeaders(){
    const t = WorkzSDK.getToken();
    const h = { 'Content-Type': 'application/json' };
    if (t) h['Authorization'] = 'Bearer ' + t;
    return h;
  }

  async function listTasks(){
    const el = document.getElementById('tasks');
    if (!el) return;
    try {
      const resp = await fetch(`${API}?action=list`, { headers: authHeaders() });
      const data = await resp.json();
      const items = Array.isArray(data?.data) ? data.data : [];
      if (!items.length) { el.innerHTML = '<li class="muted">Sem tarefas ainda</li>'; return; }
      el.innerHTML = items.map(it => `
        <li data-id="${it.id}">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" ${it.status==='done'?'checked':''} data-action="toggle" data-id="${it.id}">
            <span ${it.status==='done'?'style="text-decoration:line-through;color:#6b7280;"':''}>${it.title}</span>
          </label>
          <button data-action="del" data-id="${it.id}" style="margin-left:8px;">Excluir</button>
        </li>
      `).join('');
    } catch (_) {
      el.innerHTML = '<li class="muted">Erro ao carregar tarefas</li>';
    }
  }

  async function addTask(){
    const input = document.getElementById('task-title');
    const title = (input?.value || '').trim();
    if (!title) return;
    try {
      await fetch(API, { method: 'POST', headers: authHeaders(), body: JSON.stringify({ action: 'create', title }) });
      input.value = '';
      await listTasks();
    } catch(_){}
  }

  async function onTasksClick(e){
    const tgl = e.target.closest('input[type="checkbox"][data-action="toggle"]');
    if (tgl) {
      const id = Number(tgl.dataset.id);
      try { await fetch(API, { method: 'POST', headers: authHeaders(), body: JSON.stringify({ action: 'toggle', id }) }); }
      catch(_){}
      return;
    }
    const del = e.target.closest('button[data-action="del"]');
    if (del) {
      const id = Number(del.dataset.id);
      try { await fetch(API, { method: 'POST', headers: authHeaders(), body: JSON.stringify({ action: 'delete', id }) }); await listTasks(); }
      catch(_){}
      return;
    }
  }

  function bootstrap(){
    const out = document.getElementById('out');
    if (out) {
      out.textContent = JSON.stringify({ tokenPreview: (WorkzSDK.getToken()||'').slice(0,20)+'...' , context: WorkzSDK.getContext() }, null, 2);
    }
    document.getElementById('btn-me')?.addEventListener('click', callMe);
    document.getElementById('btn-add')?.addEventListener('click', addTask);
    document.getElementById('tasks')?.addEventListener('click', onTasksClick);
    listTasks();
  }

  return { bootstrap };
})();
