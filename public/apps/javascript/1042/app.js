// JavaScript otimizado
// Compilado em: 2025-12-30 20:54:07
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    /**
 * Painel de Curadoria de Aplicativos
 * 
 * Este aplicativo permite que usuários autorizados (da empresa com ID 104)
 * revisem, aprovem ou rejeitem novos aplicativos submetidos à loja.
 * 
 * Funcionalidades:
 * - Validação de permissão baseada na empresa do usuário.
 * - Carregamento de aplicativos com status "pendente de revisão".
 * - Interface para visualizar detalhes do app e tomar ações.
 * - Ações de Aprovar/Rejeitar com feedback visual.
 */
class CurationPanelApp {
    constructor() {
        this.CURATOR_COMPANY_ID = 104; // ID da empresa que pode fazer a curadoria
        this.appsToReview = [];
        this.isLoading = true;
        this.hasPermission = false;
        this.rootElement = null;
    }

    /**
     * Ponto de entrada do aplicativo, chamado pelo Workz!
     */
    async bootstrap() {
        console.log('🚀 Painel de Curadoria inicializado');
        this.rootElement = document.getElementById('app') || document.body;
        
        try {
            // O WorkzSDK já deve estar inicializado pelo embed.html
            // mas garantimos que temos acesso aos dados do usuário.
            if (typeof WorkzSDK === 'undefined') {
                throw new Error('WorkzSDK não está disponível.');
            }

            await this.checkPermissions();

            if (this.hasPermission) {
                await this.fetchPendingApps();
            }

            this.isLoading = false;
            this.render();
            this.setupEventListeners();

        } catch (error) {
            console.error('❌ Erro fatal no Painel de Curadoria:', error);
            this.isLoading = false;
            this.renderError(error.message);
        }
    }

    /**
     * Verifica se o usuário logado pertence à empresa de curadoria.
     */
    async checkPermissions() {
        console.log('Verificando permissões...');
        const response = await WorkzSDK.api.get('/me');
        if (response && response.companies) {
            this.hasPermission = response.companies.some(
                company => company.id === this.CURATOR_COMPANY_ID
            );
        }
        console.log(`Permissão ${this.hasPermission ? 'concedida' : 'negada'}.`);
    }

    /**
     * Busca na API os aplicativos que estão pendentes de revisão.
     * Assumimos um endpoint GET /api/apps/reviews?status=pending
     */
    async fetchPendingApps() {
        console.log('Buscando aplicativos pendentes...');
        try {
            // 1. Tentativa principal: Endpoint de revisões (ideal)
            const response = await WorkzSDK.api.get('/apps/reviews?status=pending');
            
            if (response && response.success) {
                // O endpoint ideal já retorna os detalhes do app aninhados
                this.appsToReview = response.data || [];
                console.log(`${this.appsToReview.length} apps encontrados via /apps/reviews.`);
                return; // Sucesso, não precisa do fallback
            }
            throw new Error('Endpoint /apps/reviews não encontrado ou falhou.');

        } catch (error) {
            console.warn(`[AVISO] ${error.message}. Tentando fallback via /apps?st=2...`);
            
            try {
                // 2. Tentativa de fallback: Endpoint de apps com status de revisão
                const fallbackResponse = await WorkzSDK.api.get('/apps?st=2');
                if (fallbackResponse && fallbackResponse.success) {
                    // Estrutura de fallback: precisamos simular a estrutura de "revisão"
                    this.appsToReview = (fallbackResponse.data || []).map(app => ({
                        id: app.id, // Usamos o ID do app como ID da "revisão"
                        app_id: app.id,
                        app_details: app // Aninhamos os detalhes do app
                    }));
                    console.log(`${this.appsToReview.length} apps encontrados via fallback /apps?st=2.`);
                } else {
                    this.appsToReview = [];
                    console.error('Fallback também falhou. Exibindo lista vazia.');
                }
            } catch (fallbackError) {
                console.error('Erro ao buscar apps pendentes no fallback:', fallbackError);
                this.appsToReview = [];
                this.showToast('Erro ao carregar a lista de apps para revisão.', 'error');
            }
        }
    }

    /**
     * Renderiza a interface completa do aplicativo com base no estado atual.
     */
    render() {
        let content = '';

        if (this.isLoading) {
            content = this.renderLoading();
        } else if (!this.hasPermission) {
            content = this.renderAccessDenied();
        } else if (this.appsToReview.length === 0) {
            content = this.renderEmptyState();
        } else {
            content = this.renderAppList();
        }

        this.rootElement.innerHTML = `
            <style>
                .curation-container { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #f9fafb; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                .curation-header { border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 24px; }
                .curation-header h1 { font-size: 24px; font-weight: 600; color: #111827; }
                .curation-header p { color: #6b7280; }
                .app-card { background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; transition: box-shadow 0.2s; }
                .app-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
                .app-card-body { padding: 20px; }
                .app-card-header { display: flex; align-items: center; margin-bottom: 12px; }
                .app-icon { width: 48px; height: 48px; border-radius: 8px; margin-right: 16px; object-fit: cover; background-color: #f3f4f6; }
                .app-title { font-size: 18px; font-weight: 500; color: #111827; }
                .app-publisher { font-size: 14px; color: #6b7280; }
                .app-description { color: #4b5563; margin-bottom: 16px; font-size: 14px; }
                .app-meta { display: flex; gap: 24px; font-size: 13px; color: #6b7280; border-top: 1px solid #f3f4f4; padding-top: 12px; margin-top: 16px; }
                .app-actions { display: flex; gap: 12px; margin-top: 16px; }
                .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; transition: background-color 0.2s; }
                .btn-approve { background-color: #10b981; color: white; }
                .btn-approve:hover { background-color: #059669; }
                .btn-reject { background-color: #ef4444; color: white; }
                .btn-reject:hover { background-color: #dc2626; }
                .btn-details { background-color: #3b82f6; color: white; }
                .btn-details:hover { background-color: #2563eb; }
                .centered-state { text-align: center; padding: 60px 20px; background-color: #fff; border-radius: 8px; }
                .centered-state i { font-size: 48px; color: #9ca3af; margin-bottom: 16px; }
                .toast { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
            </style>
            <div class="curation-container">
                <div class="curation-header">
                    <h1><i class="fas fa-check-double"></i> Painel de Curadoria</h1>
                    <p>Revise os aplicativos enviados para publicação na loja.</p>
                </div>
                <div id="curation-content">
                    ${content}
                </div>
            </div>
        `;
    }

    renderLoading() {
        return `<div class="centered-state"><i class="fas fa-spinner fa-spin"></i><p>Carregando...</p></div>`;
    }

    renderAccessDenied() {
        return `<div class="centered-state"><i class="fas fa-lock"></i><h2>Acesso Negado</h2><p>Você não tem permissão para acessar esta área.</p></div>`;
    }

    renderEmptyState() {
        return `<div class="centered-state"><i class="fas fa-inbox"></i><h2>Tudo em ordem!</h2><p>Nenhum aplicativo aguardando revisão no momento.</p></div>`;
    }

    renderAppList() {
        return this.appsToReview.map(review => this.renderAppCard(review)).join('');
    }

    renderAppCard(review) {
        const app = review.app_details || {}; // Assumindo que os detalhes do app vêm aninhados
        const price = parseFloat(app.vl || 0);

        return `
            <div class="app-card" id="review-${review.id}">
                <div class="app-card-body">
                    <div class="app-card-header">
                        <img src="${app.icon || '/images/no-image.jpg'}" alt="Ícone do App" class="app-icon" style="background-color: ${app.color || '#e5e7eb'};">
                        <div>
                            <div class="app-title">${app.tt || 'App sem nome'}</div>
                            <div class="app-publisher">por ${app.publisher || 'Desenvolvedor desconhecido'}</div>
                        </div>
                    </div>
                    <p class="app-description">${app.ds || '<i>Sem descrição.</i>'}</p>
                    
                    <div class="app-meta">
                        <span><strong>Versão:</strong> ${app.version || '1.0.0'}</span>
                        <span><strong>Preço:</strong> ${price > 0 ? `R$ ${price.toFixed(2).replace('.', ',')}` : 'Gratuito'}</span>
                        <span><strong>Tipo:</strong> ${app.app_type || 'javascript'}</span>
                    </div>

                    <div class="app-actions">
                        <button class="btn btn-approve" data-review-id="${review.id}" data-action="approve">
                            <i class="fas fa-check"></i> Aprovar
                        </button>
                        <button class="btn btn-reject" data-review-id="${review.id}" data-action="reject">
                            <i class="fas fa-times"></i> Rejeitar
                        </button>
                        <button class="btn btn-details" data-app-id="${app.id}" data-action="details">
                            <i class="fas fa-eye"></i> Ver Detalhes
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderError(message) {
        this.rootElement.innerHTML = `<div class="centered-state"><i class="fas fa-exclamation-triangle"></i><h2>Ocorreu um Erro</h2><p>${message}</p></div>`;
    }

    /**
     * Configura os event listeners para os botões de ação.
     */
    setupEventListeners() {
        const content = document.getElementById('curation-content');
        if (content) {
            content.addEventListener('click', (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const action = button.dataset.action;
                const reviewId = button.dataset.reviewId;
                const appId = button.dataset.appId;

                if (action === 'approve') {
                    this.handleApprove(reviewId);
                } else if (action === 'reject') {
                    this.handleReject(reviewId);
                } else if (action === 'details') {
                    // Ação futura: abrir um modal com mais detalhes do app
                    alert(`Funcionalidade "Ver Detalhes" para o App ID: ${appId} ainda não implementada.`);
                }
            });
        }
    }

    async handleApprove(reviewId) {
        if (!confirm('Tem certeza que deseja APROVAR este aplicativo? Ele será publicado na loja.')) return;

        try {
            // Assumimos um endpoint POST /api/apps/reviews/:id/approve
            const response = await WorkzSDK.api.post(`/apps/reviews/${reviewId}/approve`, {});
            if (response && response.success) {
                this.showToast('Aplicativo aprovado e publicado com sucesso!', 'success');
                document.getElementById(`review-${reviewId}`)?.remove();
                // Atualiza a lista para checar se há mais apps
                this.appsToReview = this.appsToReview.filter(r => r.id != reviewId);
                if (this.appsToReview.length === 0) {
                    this.render();
                }
            } else {
                throw new Error(response.message || 'Falha na comunicação com a API.');
            }
        } catch (error) {
            this.showToast(`Erro ao aprovar o app: ${error.message}`, 'error');
        }
    }

    async handleReject(reviewId) {
        const reason = prompt('Por favor, informe o motivo da rejeição (será enviado ao desenvolvedor):');
        if (reason === null) return; // Usuário cancelou
        if (!reason || reason.trim() === '') {
            alert('O motivo da rejeição é obrigatório.');
            return;
        }

        try {
            // Assumimos um endpoint POST /api/apps/reviews/:id/reject
            const response = await WorkzSDK.api.post(`/apps/reviews/${reviewId}/reject`, { comments: reason });
            if (response && response.success) {
                this.showToast('Aplicativo rejeitado com sucesso.', 'info');
                document.getElementById(`review-${reviewId}`)?.remove();
                this.appsToReview = this.appsToReview.filter(r => r.id != reviewId);
                if (this.appsToReview.length === 0) {
                    this.render();
                }
            } else {
                throw new Error(response.message || 'Falha na comunicação com a API.');
            }
        } catch (error) {
            this.showToast(`Erro ao rejeitar o app: ${error.message}`, 'error');
        }
    }

    /**
     * Exibe uma notificação temporária (toast).
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#3b82f6');
        toast.className = 'toast';
        toast.style.backgroundColor = bgColor;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
}

// O Workz! App Runner espera encontrar este objeto global e chamará o método bootstrap.
window.StoreApp = new CurationPanelApp();
    
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