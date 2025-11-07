// JavaScript otimizado
// Compilado em: 2025-11-07 15:00:16
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    window.StoreApp = (function () {
    let e = [],
        t = new Set(),
        n = null,
        o = null;
    function l() {
        const e = document.getElementById("app-root");
        e
            ? (e.innerHTML = `\n      <header>\n        <input type="search" id="search" placeholder="Buscar na loja..." />\n      </header>\n      <div id="list" class="grid">\n        \x3c!-- App cards will be rendered here --\x3e\n      </div>\n    `)
            : console.error("App container #app-root not found!");
    }
    async function a() {
        try {
            const e = await WorkzSDK.apiGet("/apps/catalog");
            return Array.isArray(e?.data) ? e.data : [];
        } catch (e) {
            return [];
        }
    }
    async function i() {
        try {
            (n = WorkzSDK.getUser()?.id || null),
                n || !WorkzSDK.getToken() || (n = (await WorkzSDK.apiGet("/me"))?.data?.id || null),
                (o = WorkzSDK.getContext());
            if (!n) return new Set();
            const e = await WorkzSDK.apiPost("/search", {
                db: "workz_apps",
                table: "gapp",
                columns: ["ap"],
                conditions: { us: n },
                fetchAll: !0,
            });
            return new Set((Array.isArray(e?.data) ? e.data : []).map((e) => String(e.ap)));
        } catch (e) {
            return new Set();
        }
    }
    function d(e) {
        const n = document.getElementById("list"),
            o = (document.getElementById("search")?.value || "").toLowerCase().trim();
        n.innerHTML = "";
        const l = e.filter((e) => !o || (e.tt || "").toLowerCase().includes(o));
        for (const e of l) {
            const o = document.createElement("article");
            o.className = "card";
            const l = e.im || "/images/app-default.png",
                a = e.tt || "App",
                i = e.ds || "",
                d = Number(e.vl || 0) > 0 ? `R$ ${Number(e.vl).toFixed(2)}` : "Gratuito",
                r = t.has(String(e.id));
            (o.innerHTML = `\n        <header style="display:flex;align-items:center;gap:8px;">\n          <img src="${l}" alt="${a}" width="36" height="36" style="border-radius:8px;"/>\n          <div>\n            <h5>${a}</h5>\n            <div class="muted">${d}</div>\n          </div>\n        </header>\n        <p class="muted">${i}</p>\n        <footer style="display:flex;gap:8px;">\n          <button data-action="${r ? "uninstall" : "install"}" data-app-id="${e.id}">${r ? "Remover" : "Instalar"}</button>\n          ${e.embed_url ? `<button data-action="open" data-app-id="${e.id}">Abrir</button>` : ""}\n        </footer>\n      `),
                n.appendChild(o);
        }
    }
    async function r(l) {
        const a = l.target.closest("button[data-action]");
        if (!a) return;
        const i = a.dataset.action,
            r = Number(a.dataset.appId);
        if (!r) return;
        if ("install" === i) {
            n || !WorkzSDK.getToken() || (n = (await WorkzSDK.apiGet("/me"))?.data?.id || null);
            if (!n) return void alert("Faça login na plataforma para instalar.");
            const l = new Date().toISOString().slice(0, 10);
            (
                await WorkzSDK.apiPost("/insert", {
                    db: "workz_apps",
                    table: "gapp",
                    data: { us: n, ap: r, st: 1, subscription: 0, start_date: l },
                })
            )?.error || t.add(String(r)),
                d(e);
        } else if ("uninstall" === i) {
            if (!n) return;
            await WorkzSDK.apiPost("/delete", { db: "workz_apps", table: "gapp", conditions: { us: n, ap: r } }),
                t.delete(String(r)),
                d(e);
        } else if ("open" === i)
            try {
                const e = await WorkzSDK.apiPost("/apps/sso", {
                        app_id: r,
                        ctx: o || (n ? { type: "user", id: n } : null),
                    }),
                    t = (
                        await WorkzSDK.apiPost("/search", {
                            db: "workz_apps",
                            table: "apps",
                            columns: ["*"],
                            conditions: { id: r },
                        })
                    )?.data;
                let l = t?.embed_url || t?.src || null;
                if (!l) return;
                e?.token && (l = `${l}${l.includes("?") ? "&" : "?"}token=${encodeURIComponent(e.token)}`),
                    window.open(l, "_blank");
            } catch (e) {}
    }
    return {
        async bootstrap() {
            l(),
                document.getElementById("list")?.addEventListener("click", r),
                document.getElementById("search")?.addEventListener("input", () => d(e)),
                (e = await a()),
                (t = await i()),
                d(e);
        },
        bootstrap: async function () {
            l(),
                document.getElementById("list")?.addEventListener("click", r),
                document.getElementById("search")?.addEventListener("input", () => d(e)),
                (e = await a()),
                (t = await i()),
                d(e);
        },
    };
})();
    
    console.log('✅ App JavaScript executado com sucesso');
    
} catch (error) {
    console.error('❌ Erro na execução JavaScript:', error);
    
    // Mostrar erro na tela
    const container = document.getElementById('app-container') || document.body;
    container.innerHTML = `
        <div style="
            display: flex; align-items: center; justify-content: center; 
            height: 100vh; text-align: center; color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        ">
            <div>
                <h2>⚠️ Erro na Execução</h2>
                <p>${error.message}</p>
                <button onclick="location.reload()" style="
                    background: #4CAF50; color: white; border: none;
                    padding: 10px 20px; border-radius: 5px; cursor: pointer;
                    margin-top: 15px;
                ">Recarregar</button>
            </div>
        </div>
    `;
}