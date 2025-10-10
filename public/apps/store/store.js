// public/apps/store/store.js

window.StoreApp = (function(){
  let all = [];
  let installed = new Set();
  let userId = null;
  let ctx = null;

  async function loadCatalog(){
    try {
      const res = await WorkzSDK.apiGet('/apps/catalog');
      return Array.isArray(res?.data) ? res.data : [];
    } catch(_) { return []; }
  }

  async function loadInstalled(){
    try {
      userId = WorkzSDK.getUser()?.id || null;
      if (!userId && WorkzSDK.getToken()) {
        // tenta resolver usuário via /api/me
        const me = await WorkzSDK.apiGet('/me');
        const meData = me?.data || me || null;
        userId = meData?.id || null;
      }
      ctx = WorkzSDK.getContext();
      if (!userId) return new Set();
      const res = await WorkzSDK.apiPost('/search', {
        db: 'workz_apps', table: 'gapp', columns: ['ap'], conditions: { us: userId }, fetchAll: true
      });
      const set = new Set((Array.isArray(res?.data) ? res.data : []).map(x => String(x.ap)));
      return set;
    } catch(_) { return new Set(); }
  }

  function render(list){
    const root = document.getElementById('list');
    const q = (document.getElementById('search')?.value || '').toLowerCase().trim();
    root.innerHTML = '';
    const filtered = list.filter(app => !q || (app.tt||'').toLowerCase().includes(q));
    for (const app of filtered){
      const card = document.createElement('article');
      card.className = 'card';
      const img = app.im || '/images/app-default.png';
      const name = app.tt || 'App';
      const desc = app.ds || '';
      const price = Number(app.vl||0) > 0 ? `R$ ${Number(app.vl).toFixed(2)}` : 'Gratuito';
      const isInstalled = installed.has(String(app.id));
      card.innerHTML = `
        <header style="display:flex;align-items:center;gap:8px;">
          <img src="${img}" alt="${name}" width="36" height="36" style="border-radius:8px;"/>
          <div>
            <h5>${name}</h5>
            <div class="muted">${price}</div>
          </div>
        </header>
        <p class="muted">${desc}</p>
        <footer style="display:flex;gap:8px;">
          <button data-action="${isInstalled ? 'uninstall' : 'install'}" data-app-id="${app.id}">${isInstalled ? 'Remover' : 'Instalar'}</button>
          ${app.embed_url ? `<button data-action="open" data-app-id="${app.id}">Abrir</button>` : ''}
        </footer>
      `;
      root.appendChild(card);
    }
  }

  async function onAction(e){
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const appId = Number(btn.dataset.appId);
    if (!appId) return;

    if (action === 'install'){
      if (!userId && WorkzSDK.getToken()) {
        // última tentativa de resolver user
        const me = await WorkzSDK.apiGet('/me');
        const meData = me?.data || me || null;
        userId = meData?.id || null;
      }
      if (!userId) { alert('Faça login na plataforma para instalar.'); return; }
      const today = new Date();
      const ymd = today.toISOString().slice(0,10);
      const res = await WorkzSDK.apiPost('/insert', { db: 'workz_apps', table: 'gapp', data: { us: userId, ap: appId, st: 1, subscription: 0, start_date: ymd } });
      if (res && !res.error) { installed.add(String(appId)); }
      render(all);
      return;
    }
    if (action === 'uninstall'){
      if (!userId) return;
      const res = await WorkzSDK.apiPost('/delete', { db: 'workz_apps', table: 'gapp', conditions: { us: userId, ap: appId } });
      installed.delete(String(appId));
      render(all);
      return;
    }
    if (action === 'open'){
      // Solicita SSO curto para abrir o app já instalado
      try {
        const sso = await WorkzSDK.apiPost('/apps/sso', { app_id: appId, ctx: ctx || (userId ? { type: 'user', id: userId } : null) });
        const token = sso?.token || null;
        // Buscar dados do app para obter embed_url/src
        const info = await WorkzSDK.apiPost('/search', { db: 'workz_apps', table: 'apps', columns: ['*'], conditions: { id: appId } });
        const app = Array.isArray(info?.data) ? info.data[0] : info?.data || null;
        let url = app?.embed_url || app?.src || null;
        if (!url) return;
        if (token) {
          const sep = url.includes('?') ? '&' : '?';
          url = `${url}${sep}token=${encodeURIComponent(token)}`;
        }
        window.open(url, '_blank');
      } catch(_){}
      return;
    }
  }

  async function bootstrap(){
    document.getElementById('list')?.addEventListener('click', onAction);
    document.getElementById('search')?.addEventListener('input', () => render(all));
    all = await loadCatalog();
    installed = await loadInstalled();
    render(all);
  }

  return { bootstrap };
})();
