window.StoreApp = (function () {
  // Estado
  let catalog = [];
  let installedSet = new Set();
  let userId = null;
  let ctx = null; // { type: 'user'|'business', id: number } | null
  const SERVICE_OPTIONS = [
    { days: 30, label: '30 dias', multiplier: 1 },
    { days: 90, label: '90 dias', multiplier: 3 },
    { days: 180, label: '180 dias', multiplier: 6 },
  ];

  function buildServiceOptions(price) {
    const base = Number(price || 0);
    return SERVICE_OPTIONS.map(opt => ({
      ...opt,
      amount: Number((base * opt.multiplier).toFixed(2)),
    })).filter(opt => opt.amount > 0);
  }

  function entitlementIsActive(ent) {
    const status = Number(ent?.st || 0) === 1;
    const endDate = ent?.end_date ? String(ent.end_date) : null;
    if (!status) return false;
    if (!endDate) return true;
    const today = new Date().toISOString().slice(0, 10);
    return endDate >= today;
  }

  function resolveSupportDaysMeta(meta, fallback = 30) {
    let obj = {};
    if (Array.isArray(meta)) {
      obj = {};
    } else if (typeof meta === 'string' && meta !== '') {
      try { obj = JSON.parse(meta) || {}; } catch (_) { obj = {}; }
    } else if (meta && typeof meta === 'object') {
      obj = meta;
    }
    const sd = obj.support_days ?? obj.supportDays ?? obj.service_days ?? obj.serviceDays;
    const days = sd !== undefined ? parseInt(sd, 10) : null;
    if (Number.isFinite(days) && days > 0) return days;
    return fallback;
  }

  async function deriveEntitlementEnd(entitlement, app, entityType, entityId) {
    const entStart = entitlement?.start_date ? String(entitlement.start_date) : null;
    const entEndRaw = entitlement?.end_date ? String(entitlement.end_date) : null;
    const entDays = Number(entitlement?.support_days || entitlement?.supportDays || 0) || null;
    const derive = (start, days) => {
      if (!start || !Number.isFinite(days) || days <= 0) return null;
      const d = new Date(start);
      if (isNaN(d.getTime())) return null;
      d.setDate(d.getDate() + days);
      return d.toISOString().slice(0, 10);
    };
    let endDate = entEndRaw || derive(entStart, entDays);
    let days = entDays;

    if (!endDate) {
      try {
        const txConds = (entityType === 'business')
          ? { company_id: entityId, app_id: app.id, status: { op: 'IN', value: ['approved', 'succeeded'] } }
          : { user_id: entityId, app_id: app.id, status: { op: 'IN', value: ['approved', 'succeeded'] } };
        const txResp = await WorkzSDK.apiPost('/search', {
          db: 'workz_apps',
          table: 'workz_payments_transactions',
          columns: ['metadata', 'created_at'],
          conditions: txConds,
          fetchAll: false,
          order: { by: 'id', dir: 'DESC' },
          limit: 1
        });
        const tx = txResp?.data || null;
        if (tx) {
          const txDays = resolveSupportDaysMeta(tx.metadata, 30);
          const start = entStart || (tx.created_at ? String(tx.created_at).slice(0, 10) : null);
          endDate = derive(start, txDays) || endDate;
          days = txDays || days;
        }
      } catch (_) { /* ignore */ }
    }

    return {
      startDate: entStart,
      endDate,
      days,
      status: Number(entitlement?.st || 0) === 1
    };
  }

  // Helpers DOM
  function renderShell() {
    const root = document.getElementById('app-root');
    if (!root) {
      console.error('App container #app-root not found!');
      return;
    }
    root.innerHTML = `
      <header style="display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <input type="search" id="store-search" placeholder="Buscar na loja..." style="flex:1;max-width:420px;padding:8px 12px;border-radius:12px;border:1px solid #ddd;"/>
      </header>
      <div id="store-content" class="grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;"></div>
    `;
  }

  // API
  async function fetchCatalog() {
    try {
      const res = await WorkzSDK.apiGet('/apps/catalog');
      return Array.isArray(res?.data) ? res.data : [];
    } catch (_) {
      return [];
    }
  }

  async function resolveUserAndContext() {
    try {
      userId = WorkzSDK.getUser()?.id || null;
      if (!userId && WorkzSDK.getToken()) {
        const me = await WorkzSDK.apiGet('/me');
        userId = me?.data?.id || null;
      }
      ctx = WorkzSDK.getContext() || null;
    } catch (_) {
      userId = null;
      ctx = null;
    }
  }

  async function fetchInstalledSet() {
    try {
      if (!userId) return new Set();
      const set = new Set();

      // Vínculos por usuário
      const resUser = await WorkzSDK.apiPost('/search', {
        db: 'workz_apps', table: 'gapp', columns: ['ap','st','end_date'],
        conditions: { us: userId, st: 1 }, fetchAll: true
      });
      (Array.isArray(resUser?.data) ? resUser.data : []).forEach(r => {
        if (entitlementIsActive(r)) set.add(String(r.ap));
      });

      // Vínculos por empresa (se contexto)
      if (ctx && ctx.type === 'business' && ctx.id) {
        const resBiz = await WorkzSDK.apiPost('/search', {
          db: 'workz_apps', table: 'gapp', columns: ['ap','st','end_date'],
          conditions: { em: Number(ctx.id), st: 1 }, fetchAll: true
        });
        (Array.isArray(resBiz?.data) ? resBiz.data : []).forEach(r => {
          if (entitlementIsActive(r)) set.add(String(r.ap));
        });
      }
      return set;
    } catch (_) {
      return new Set();
    }
  }

  // Render lista
  function renderList() {
    const mount = document.getElementById('store-content');
    const term = (document.getElementById('store-search')?.value || '').toLowerCase().trim();
    if (!mount) return;

    const list = catalog.filter(app => !term || String(app.tt || '').toLowerCase().includes(term));
    mount.innerHTML = '';

    for (const app of list) {
      const cover = app.im || '/images/app-default.png';
      const title = app.tt || 'App';
      const desc = app.ds || '';
      const hasPrice = Number(app.vl || 0) > 0;
      const priceLabel = hasPrice ? `R$ ${Number(app.vl).toFixed(2)}` : 'Gratuito';
      const isInstalled = installedSet.has(String(app.id));

      const article = document.createElement('article');
      article.className = 'card';
      article.style = 'background:#fff;border:1px solid #eee;border-radius:16px;padding:12px;display:grid;gap:10px;';

      article.innerHTML = `
        <header style="display:flex;align-items:center;gap:10px;">
          <img src="${cover}" alt="${title}" width="36" height="36" style="border-radius:8px;"/>
          <div>
            <h5 style="margin:0;font-size:15px;">${title}</h5>
            <div class="muted" style="font-size:12px;color:#666;">${priceLabel}</div>
          </div>
        </header>
        <p class="muted" style="font-size:12px;color:#666;min-height:32px;margin:0;">${desc}</p>
        <footer style="display:flex;gap:8px;flex-wrap:wrap;">
          <button data-action="details" data-app-id="${app.id}" style="padding:8px 10px;border-radius:12px;border:1px solid #ddd;background:#fafafa;">Detalhes</button>
          ${app.embed_url ? `<button data-action="open" data-app-id="${app.id}" style="padding:8px 10px;border-radius:12px;border:1px solid #ddd;background:#fafafa;">Abrir</button>` : ''}
          ${isInstalled ? `<span style="padding:8px 10px;border-radius:12px;background:#e7f8ee;color:#207a4b;font-size:12px;">Instalado</span>` : ''}
        </footer>
      `;

      mount.appendChild(article);
    }
  }

  // Abrir embed com SSO
  async function openApp(appId) {
    try {
      const sso = await WorkzSDK.apiPost('/apps/sso', {
        app_id: appId, ctx: ctx || (userId ? { type: 'user', id: userId } : null)
      });

      const appData = (await WorkzSDK.apiPost('/search', {
        db: 'workz_apps', table: 'apps', columns: ['*'], conditions: { id: appId }, fetchAll: false
      }))?.data;
      let url = appData?.embed_url || appData?.src || null;
      if (!url) return;

      if (sso?.token) {
        url += (url.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(sso.token);
      }
      window.open(url, '_blank');
    } catch (_) { /* ignore */ }
  }

  async function fetchManagedBusinesses() {
    if (!userId) return [];
    try {
      const links = await WorkzSDK.apiPost('/search', {
        db: 'workz_companies',
        table: 'employees',
        columns: ['em','nv','st'],
        conditions: { us: userId, st: 1 },
        exists: [{ table: 'companies', local: 'em', remote: 'id', conditions: { st: 1 } }],
        fetchAll: true,
        limit: 200
      });
      const ids = new Set();
      const list = Array.isArray(links?.data) ? links.data.map(r => Number(r.em)) : [];
      list.forEach(id => ids.add(id));
      // negócios que o usuário criou
      const owned = await WorkzSDK.apiPost('/search', {
        db: 'workz_companies',
        table: 'companies',
        columns: ['id','st'],
        conditions: { us: userId, st: 1 },
        fetchAll: true,
        limit: 200
      });
      (Array.isArray(owned?.data) ? owned.data : []).forEach(r => ids.add(Number(r.id)));

      const idArr = Array.from(ids.values()).filter(n => Number.isFinite(n) && n > 0);
      if (!idArr.length) return [];
      const companies = await WorkzSDK.apiPost('/search', {
        db: 'workz_companies',
        table: 'companies',
        columns: ['id','tt','ml','stripe_customer_id'],
        conditions: { id: { op: 'IN', value: idArr } },
        fetchAll: true,
        limit: 200
      });
      return Array.isArray(companies?.data) ? companies.data.map(c => ({
        id: Number(c.id),
        name: c.tt || 'Negócio',
        email: c.ml || '',
        stripe_customer_id: c.stripe_customer_id || null
      })) : [];
    } catch (_) {
      return [];
    }
  }

  async function renderActivationArea(app, entitlement, isBusinessCtx, opts = {}) {
    const { audience, businesses = [], selectedBusinessId = null, onBusinessChange = null } = opts;
    const requiresBusiness = Number(audience) === 2;
    const area = document.getElementById('purchase-area');
    if (!area) return;
    if (!userId) {
      area.innerHTML = '<div style="padding:10px 12px;border-radius:12px;background:#fff4ed;color:#b45309;font-size:13px;">Faça login para ativar o suporte.</div>';
      return;
    }
    const options = buildServiceOptions(app.vl || 0);
    if (!options.length) {
      area.innerHTML = '<div style="padding:10px 12px;border-radius:12px;background:#fff4ed;color:#b45309;font-size:13px;">Defina um valor para este app antes de vender suporte.</div>';
      return;
    }

    if (requiresBusiness && (!selectedBusinessId || !businesses.length)) {
      area.innerHTML = '<div style="padding:10px 12px;border-radius:12px;background:#fff4ed;color:#b45309;font-size:13px;">Selecione um negócio onde você seja gestor para ativar.</div>';
      return;
    }

    const entityType = requiresBusiness ? 'business' : 'user';
    const entityId = requiresBusiness ? Number(selectedBusinessId) : userId;
    if (!entityId) {
      area.innerHTML = '<div style="padding:10px 12px;border-radius:12px;background:#fff4ed;color:#b45309;font-size:13px;">Selecione um negócio válido para continuar.</div>';
      return;
    }

    const { endDate: entEndDate, days: entDays, status: entStatus } =
      await deriveEntitlementEnd(entitlement, app, entityType, entityId);
    const today = new Date().toISOString().slice(0, 10);
    const isSupportActive = entStatus && (!entEndDate || entEndDate >= today);
    const expired = entStatus && !!entEndDate && entEndDate < today;
    const statusText = isSupportActive
      ? `Suporte ativo até ${entEndDate || 'sem prazo definido'}.`
      : (expired ? `Suporte expirado em ${entEndDate}.` : 'Suporte ainda não ativado.');

    const cardsResp = await WorkzSDK.apiGet(`/billing/payment-methods?entity=${entityType}&id=${entityId}`);
    const cards = (cardsResp?.data || []).filter(m => m.provider === 'stripe' && m.pm_type === 'card' && Number(m.status) === 1);
    if (!cards.length) {
      area.innerHTML = `
        <div style="display:grid;gap:10px;align-items:start;">
          ${requiresBusiness ? `
            <div style="display:grid;gap:6px;max-width:360px;">
              <label style="font-size:12px;color:#666;">Negócio</label>
              <select id="svc-biz" style="padding:8px 10px;border:1px solid #ddd;border-radius:12px;">
                ${businesses.map(b => `<option value="${b.id}" ${Number(selectedBusinessId)===Number(b.id)?'selected':''}>${b.name}</option>`).join('')}
              </select>
            </div>
          ` : ''}
          <div style="padding:10px 12px;border-radius:12px;background:#fff4ed;color:#b45309;font-size:13px;">
            ${requiresBusiness ? 'Selecione um negócio com cartão cadastrado em Cobrança e Recebimento para ativar.' : 'Cadastre um cartão em Cobrança e Recebimento para ativar.'}
          </div>
        </div>
      `;
      if (requiresBusiness && onBusinessChange) {
        const bizSelect = area.querySelector('#svc-biz');
        if (bizSelect && !bizSelect.dataset.listenerAttached) {
          bizSelect.addEventListener('change', (ev) => {
            const newBizId = Number(ev.target.value || 0);
            if (newBizId) onBusinessChange(newBizId);
          });
          bizSelect.dataset.listenerAttached = '1';
        }
      }
      return;
    }

    const optionButtons = options.map((opt, idx) => `
      <button data-role="svc-opt" data-days="${opt.days}" data-amount="${opt.amount}" class="svc-opt ${idx===0?'active':''}" style="padding:8px 10px;border:1px solid ${idx===0?'#2563eb':'#ddd'};background:${idx===0?'#eef2ff':'#fafafa'};border-radius:12px;font-size:12px;min-width:140px;">
        ${opt.label} • R$ ${opt.amount.toFixed(2)}
      </button>
    `).join('');
    const cardOptions = cards.map(c => `<option value="${c.id}" ${Number(c.is_default)===1?'selected':''}>${c.label || ((c.brand||'') + ' •••• ' + (c.last4||''))}</option>`).join('');

    const businessSelector = requiresBusiness ? `
      <div style="display:grid;gap:6px;max-width:360px;">
        <label style="font-size:12px;color:#666;">Negócio</label>
        <select id="svc-biz" style="padding:8px 10px;border:1px solid #ddd;border-radius:12px;">
          ${businesses.map(b => `<option value="${b.id}" ${Number(selectedBusinessId)===Number(b.id)?'selected':''}>${b.name}</option>`).join('')}
        </select>
      </div>
    ` : '';

    area.innerHTML = `
      <div style="display:grid;gap:10px;align-items:start;">
        <div style="font-size:13px;color:#444;">${statusText}</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">${optionButtons}</div>
        <div style="display:grid;gap:6px;max-width:360px;">
          ${businessSelector}
          <label style="font-size:12px;color:#666;">Cartão salvo</label>
          <select id="svc-card" style="padding:8px 10px;border:1px solid #ddd;border-radius:12px;">${cardOptions}</select>
          <button id="svc-pay" style="padding:10px 14px;border-radius:14px;border:none;background:#2563eb;color:#fff;">Ativar</button>
          <div id="svc-msg" style="font-size:12px;color:#555;"></div>
        </div>
      </div>
    `;

    let selected = options[0];
    const updateButton = () => {
      const btn = area.querySelector('#svc-pay');
      if (btn && selected) btn.textContent = `Pagar ${selected.label} • R$ ${selected.amount.toFixed(2)}`;
    };
    updateButton();

    area.querySelectorAll('[data-role="svc-opt"]').forEach(btn => {
      btn.addEventListener('click', (ev) => {
        area.querySelectorAll('[data-role="svc-opt"]').forEach(b => {
          b.classList.remove('active');
          b.style.border = '1px solid #ddd';
          b.style.background = '#fafafa';
        });
        btn.classList.add('active');
        btn.style.border = '1px solid #2563eb';
        btn.style.background = '#eef2ff';
        selected = {
          days: Number(btn.dataset.days || options[0].days),
          amount: Number(btn.dataset.amount || options[0].amount),
          label: btn.textContent.trim(),
        };
        updateButton();
      });
    });

    if (requiresBusiness && onBusinessChange) {
      const bizSelect = area.querySelector('#svc-biz');
      if (bizSelect && !bizSelect.dataset.listenerAttached) {
        bizSelect.addEventListener('change', (ev) => {
          const newBizId = Number(ev.target.value || 0);
          if (newBizId) onBusinessChange(newBizId);
        });
        bizSelect.dataset.listenerAttached = '1';
      }
    }

    area.querySelector('#svc-pay')?.addEventListener('click', async () => {
      const msg = area.querySelector('#svc-msg');
      try {
        const pmId = Number(area.querySelector('#svc-card')?.value || 0);
        if (!pmId) { msg.textContent = 'Selecione um cartão.'; msg.style.color = '#b45309'; return; }
        msg.textContent = 'Processando pagamento...'; msg.style.color = '#555';
        const charge = await WorkzSDK.apiPost('/payments/charge', {
          app_id: app.id,
          amount: selected.amount,
          currency: 'BRL',
          pm_id: pmId,
          company_id: requiresBusiness ? Number(selectedBusinessId) : null,
          support_days: selected.days,
          metadata: { support_days: selected.days, activation: 'app_support' },
        });
        if (charge?.success) {
          msg.textContent = charge.status === 'approved' ? 'Suporte ativo!' : 'Pagamento enviado. Aguarde a confirmação.';
          msg.style.color = '#207a4b';
          installedSet.add(String(app.id));
          renderDetails(app.id);
        } else {
          const detail = charge?.details || charge?.error || charge?.message || '';
          msg.textContent = 'Não foi possível concluir o pagamento.' + (detail ? ` Detalhes: ${detail}` : '');
          msg.style.color = '#b45309';
        }
      } catch (_) {
        const msgNode = area.querySelector('#svc-msg');
        if (msgNode) { msgNode.textContent = 'Erro ao processar pagamento.'; msgNode.style.color = '#b45309'; }
      }
    });
  }

  // Detalhes
  async function renderDetails(appId) {
    const mount = document.getElementById('store-content');
    if (!mount) return;

    // Busca detalhes do app
    const app = (await WorkzSDK.apiPost('/search', {
      db: 'workz_apps', table: 'apps', columns: ['*'], conditions: { id: appId }, fetchAll: false
    }))?.data || null;
    if (!app) { renderList(); return; }

    const audience = Number(app.entity_type ?? app.entityType ?? app.entity_type_id ?? 1); // 1=usuários, 2=negócios
    const cover = app.im || '/images/app-default.png';
    const title = app.tt || 'App';
    const desc = app.ds || '';
    const hasPrice = Number(app.vl || 0) > 0;
    const price = Number(app.vl || 0);
    const priceLabel = hasPrice ? `R$ ${price.toFixed(2)}` : 'Gratuito';
    const isInstalled = installedSet.has(String(app.id));

    // Seleção de negócios para apps cujo cliente é negócio
    let managedBusinesses = [];
    let selectedBusinessId = null;
    if (audience === 2) {
      managedBusinesses = await fetchManagedBusinesses();
      if (ctx && ctx.type === 'business' && ctx.id && managedBusinesses.find(b => Number(b.id) === Number(ctx.id))) {
        selectedBusinessId = Number(ctx.id);
      } else if (managedBusinesses.length) {
        selectedBusinessId = Number(managedBusinesses[0].id);
      }
    }
    const isBusinessCtx = audience === 2;

    // Verifica vínculo atual para decidir botões (assinatura ativa vs apenas instalado)
    let entitlement = null;
    const fetchEntitlement = async (businessId = null) => {
      try {
        const conds = (audience === 2 && businessId)
          ? { em: Number(businessId), ap: app.id }
          : { us: userId, ap: app.id };
        return (await WorkzSDK.apiPost('/search', { db: 'workz_apps', table: 'gapp', columns: ['*'], conditions: conds, fetchAll: false }))?.data || null;
      } catch (_) { return null; }
    };
    entitlement = await fetchEntitlement(selectedBusinessId);

    const { endDate: entEndDate, status: entStatus } =
      await deriveEntitlementEnd(entitlement, app, (audience === 2 ? 'business' : 'user'), selectedBusinessId || userId);
    const today = new Date().toISOString().slice(0, 10);
    const entFlag = !!entStatus;
    const hasEndDate = !!entEndDate;
    const activeByDate = hasEndDate && entEndDate >= today;
    const isSupportActive = (entFlag || activeByDate) && (!entEndDate || entEndDate >= today);
    const isSupportExpired = (entFlag || hasEndDate) && !!entEndDate && entEndDate < today;
    const statusLabel = isSupportActive
      ? `Ativo até ${entEndDate || 'sem prazo definido'}`
      : (isSupportExpired ? `Expirado em ${entEndDate}` : 'Inativo');
    const canOpen = !hasPrice || isSupportActive || installedSet.has(String(app.id));
    if (isSupportActive) {
      installedSet.add(String(app.id));
    } else {
      installedSet.delete(String(app.id));
    }

    mount.innerHTML = `
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;">
        <button data-action="back-list" style="padding:6px 10px;border-radius:10px;border:1px solid #ddd;background:#fafafa;">← Voltar</button>
        <h3 style="margin:0;">${title}</h3>
      </div>
      <article class="card" style="background:#fff;border:1px solid #eee;border-radius:16px;padding:16px;display:grid;grid-template-columns:100px 1fr;gap:14px;">
        <img src="${cover}" alt="${title}" width="100" height="100" style="border-radius:12px;"/>
        <div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div style="font-size:14px;color:#666;">${priceLabel}</div>
            ${hasPrice ? `<span id="status-badge" style="background:${isSupportActive ? '#e7f8ee' : '#fff4ed'};border-radius:10px;padding:6px 10px;font-size:12px;color:${isSupportActive ? '#207a4b' : '#b45309'};">${statusLabel}</span>` : ''}
            ${isBusinessCtx ? '<span style="background:#f1f1f1;border-radius:10px;padding:3px 8px;font-size:12px;color:#444;">Instalar para: Negócio</span>' : ''}
          </div>
          <p style="margin:10px 0 12px 0;color:#444;white-space:pre-wrap;">${desc}</p>
          <div id="primary-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"></div>
          <div id="purchase-area" style="margin-top:14px;"></div>
        </div>
      </article>
    `;

    // Ações locais
    mount.querySelector('[data-action="back-list"]')?.addEventListener('click', () => renderList());
    mount.querySelector('[data-action="open"]')?.addEventListener('click', (ev) => {
      const id = Number(ev.currentTarget?.dataset?.appId || 0);
      if (id) openApp(id);
    });
    mount.querySelectorAll('[data-action="open-purchase"]')?.forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('purchase-area')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    // Instalação gratuita
    mount.querySelector('[data-action="install-free"]')?.addEventListener('click', async (ev) => {
      const id = Number(ev.currentTarget?.dataset?.appId || 0);
      if (!userId || !id) { alert('Faça login para instalar.'); return; }
      const today = new Date().toISOString().slice(0,10);
      const data = isBusinessCtx
        ? { em: Number(ctx.id), ap: id, st: 1, subscription: 0, start_date: today }
        : { us: userId, ap: id, st: 1, subscription: 0, start_date: today };
      const r = await WorkzSDK.apiPost('/insert', { db: 'workz_apps', table: 'gapp', data });
      if (!r?.error) {
        installedSet.add(String(id));
        renderDetails(id);
      } else {
        alert('Falha ao instalar.');
      }
    });

    const renderPurchase = async (bizId = selectedBusinessId) => {
      const ent = await fetchEntitlement(bizId);
      await renderActivationArea(app, ent, isBusinessCtx, {
        audience,
        businesses: managedBusinesses,
        selectedBusinessId: bizId,
        onBusinessChange: async (newBizId) => {
          selectedBusinessId = newBizId;
          await renderPurchase(newBizId);
        }
      });

      // Atualiza badge e botões principais com o status mais recente
      const { endDate: entEndDate, status: entStatus } =
        await deriveEntitlementEnd(ent, app, (audience === 2 ? 'business' : 'user'), bizId || userId);
      const today = new Date().toISOString().slice(0, 10);
      const entFlag = !!entStatus;
      const hasEndDate = !!entEndDate;
      const activeByDate = hasEndDate && entEndDate >= today;
      const isSupportActiveLocal = (entFlag || activeByDate) && (!entEndDate || entEndDate >= today);
      const isSupportExpiredLocal = (entFlag || hasEndDate) && !!entEndDate && entEndDate < today;
      const statusLabelLocal = isSupportActiveLocal
        ? `Ativo até ${entEndDate || 'sem prazo definido'}`
        : (isSupportExpiredLocal ? `Expirado em ${entEndDate}` : 'Inativo');

      const statusBadge = document.getElementById('status-badge');
      if (statusBadge) {
        statusBadge.textContent = statusLabelLocal;
        statusBadge.style.background = isSupportActiveLocal ? '#e7f8ee' : '#fff4ed';
        statusBadge.style.color = isSupportActiveLocal ? '#207a4b' : '#b45309';
      }

      const primary = document.getElementById('primary-actions');
      if (primary) {
        let primaryBtnHtml = '';
        if (hasPrice) {
          if (isSupportActiveLocal) {
            primaryBtnHtml = `
              <button data-action="open" data-app-id="${app.id}" style="padding:10px 14px;border-radius:14px;border:none;background:#2563eb;color:#fff;">Abrir</button>
              <button data-action="open-purchase" data-app-id="${app.id}" style="padding:10px 14px;border-radius:14px;border:1px solid #2563eb;background:#f0f4ff;color:#1d4ed8;">Recarregar período</button>
            `;
          } else {
            const label = isSupportExpiredLocal ? 'Reativar suporte' : 'Ativar suporte';
            primaryBtnHtml = `<button data-action="open-purchase" data-app-id="${app.id}" style="padding:10px 14px;border-radius:14px;border:none;background:#2563eb;color:#fff;">${label}</button>`;
          }
        } else if (installedSet.has(String(app.id))) {
          primaryBtnHtml = `<button data-action="open" data-app-id="${app.id}" style="padding:10px 14px;border-radius:14px;border:none;background:#2563eb;color:#fff;">Abrir</button>`;
        } else {
          primaryBtnHtml = `<button data-action="install-free" data-app-id="${app.id}" style="padding:10px 14px;border-radius:14px;border:none;background:#2563eb;color:#fff;">Instalar</button>`;
        }
        if ((!hasPrice || isSupportActiveLocal) && app.embed_url) {
          primaryBtnHtml += `<button data-action="open" data-app-id="${app.id}" style="padding:10px 14px;border-radius:14px;border:1px solid #ddd;background:#fafafa;">Testar / Abrir</button>`;
        }
        primary.innerHTML = primaryBtnHtml;

        // Reanexa listeners dos botões após atualizar HTML
        primary.querySelectorAll('[data-action="open"]')?.forEach(btn => {
          btn.addEventListener('click', (ev) => {
            const id = Number(ev.currentTarget?.dataset?.appId || 0);
            if (id) openApp(id);
          });
        });
        primary.querySelectorAll('[data-action="open-purchase"]')?.forEach(btn => {
          btn.addEventListener('click', () => {
            document.getElementById('purchase-area')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          });
        });
        primary.querySelectorAll('[data-action="install-free"]')?.forEach(btn => {
          btn.addEventListener('click', async (ev) => {
            const id = Number(ev.currentTarget?.dataset?.appId || 0);
            if (!userId || !id) { alert('Faça login para instalar.'); return; }
            const today = new Date().toISOString().slice(0,10);
            const data = isBusinessCtx
              ? { em: Number(ctx.id), ap: id, st: 1, subscription: 0, start_date: today }
              : { us: userId, ap: id, st: 1, subscription: 0, start_date: today };
            const r = await WorkzSDK.apiPost('/insert', { db: 'workz_apps', table: 'gapp', data });
            if (!r?.error) {
              installedSet.add(String(id));
              renderDetails(id);
            } else {
              alert('Falha ao instalar.');
            }
          });
        });
      }
    };

    await renderPurchase(selectedBusinessId);
  }

  // Click handler principal (lista)
  async function onListClick(ev) {
    const btn = ev.target.closest('button[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const appId = Number(btn.dataset.appId || 0);
    if (!appId) return;

    if (action === 'details') return renderDetails(appId);
    if (action === 'open') return openApp(appId);
  }

  // Bootstrap
  return {
    async bootstrap() {
      renderShell();
      document.getElementById('store-content')?.addEventListener('click', onListClick);
      document.getElementById('store-search')?.addEventListener('input', renderList);
      await resolveUserAndContext();
      catalog = await fetchCatalog();
      installedSet = await fetchInstalledSet();
      renderList();
    }
  };
})();
