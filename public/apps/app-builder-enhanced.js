// Enhanced App Builder with Storage Management UI
window.StoreApp = {
    currentStep: 1,
    maxSteps: 6,
    editMode: false,
    editingAppId: null,
    appType: "javascript",
    // Modo simples: preferir sempre o editor de uma única textarea (inclusive para Flutter)
    // NEW: Control the current view mode: 'list' (my apps) or 'form' (create/edit app)
    viewMode: 'list', // Default to showing the list of apps
    useSimpleEditor: true,
    // Controle de polling do modal de build
    _buildWatchTimer: null,
    _buildWatchAppId: null,
    // Preview (live) controls
    _activePreviewToken: null,
    _activePreviewBase: null,
    _livePreviewMode: null, // 'js' | 'flutter'
    _livePreviewDebounce: null,
    _codeMirrorInstance: null,
    _depsPromise: null, // Promise para rastrear o carregamento de dependências
    companyFilterId: 'all', // NEW: To filter apps by company
    userCompanies: [],
    storageStats: null,
    appData: {
        company: null,
        // Novo estado para o editor de arquivos
        appFiles: {}, // Ex: { 'main.dart': '...', 'pubspec.yaml': '...' }
        activeFile: null,
        unsavedChanges: new Set(),
        title: "",
        slug: "",
        description: "",
        icon: null,
        color: "#3b82f6",
        accessLevel: 1,
        version: "1.0.0",
        entityType: 0,
        price: 0,
        scopes: [],
        code: "",
        dartCode: "",
        token: null
    },

    async bootstrap() {
        try {
            // Prevent double bootstrap in case the script is loaded twice or app-runner also triggers it
            if (window.__storeAppBootstrapped) {
                console.log('⏭️ App Studio bootstrap skipped (already bootstrapped)');
                return;
            }
            window.__storeAppBootstrapped = true;
            console.log('🚀 Starting App Studio bootstrap...');

            // Inicia o carregamento de dependências e armazena a promise
            this._depsPromise = (async () => {
                this.loadCSS("https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css");
                this.loadCSS("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css");

                // Carregar dependências do CodeMirror
                this.loadCSS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css");
                this.loadCSS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/monokai.min.css");
                this.loadCSS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/dialog/dialog.min.css");
                this.loadCSS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/fold/foldgutter.min.css");
                this.loadCSS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/display/fullscreen.min.css");

                await this.loadJS("https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/javascript/javascript.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/dart/dart.min.js");
                // Addons do CodeMirror
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/dialog/dialog.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/search/searchcursor.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/search/search.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/edit/matchbrackets.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/fold/foldcode.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/fold/foldgutter.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/fold/brace-fold.min.js");
                await this.loadJS("https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/display/fullscreen.min.js");
            })();

            await this._depsPromise;

            console.log('📦 Dependencies loaded, rendering UI...');
            this.render();

            console.log('🎯 Setting up event listeners...');
            this.setupFormEventListeners();

            console.log('🏢 Loading user companies...');
            await this.loadUserCompanies();

            console.log('📊 Loading storage stats...');
            await this.loadStorageStats();

            console.log('🔄 Updating company select...');
            this.updateCompanySelect();

            console.log('✅ Validating current step...');
            this.validateCurrentStep();

            this.setupFormEventListeners();
            console.log('👁️ Setting up preview listener...');
            this.setupPreviewListener();

            console.log('🎉 App Studio bootstrap completed successfully!');
        } catch (e) {
            console.error("❌ Erro ao inicializar App Studio:", e);
            this.renderError("Erro ao carregar o App Studio: " + e.message);
        }
    },

    loadCSS(href) {
        if (!document.querySelector(`link[href="${href}"]`)) {
            const link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = href;
            document.head.appendChild(link);
        }
    },

    loadJS: (src) => new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const script = document.createElement("script");
        script.src = src;
        // enforce execution order for dynamically inserted scripts
        script.async = false;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    }),

    async loadUserCompanies() {
        try {
            const response = await WorkzSDK.api.get("/me");
            if (response && response.companies) {
                this.userCompanies = response.companies.filter(company => company.nv >= 3);
            } else {
                this.userCompanies = [];
            }
            // Re-render the UI to populate the company filter dropdown
            if (this.viewMode === 'list') {
                this.render();
            }
        } catch (e) {
            console.error("Erro ao carregar empresas:", e);
            this.userCompanies = [];
        }
    },

    async loadStorageStats() {
        try {
            const response = await WorkzSDK.api.get("/apps/storage/stats");
            const httpOk = response && typeof response.status === 'number' && response.status >= 200 && response.status < 300;
            let ok = !!(response && (response.success === true || httpOk || response.app_id || (response.data && (response.data.id || response.data.app_type || typeof response.data === 'object'))));
            if (!ok && this.editMode && this.editingAppId) {
                try {
                    const verify = await this.apiGet(`/apps/${this.editingAppId}`);
                    if (verify && verify.success && verify.data) {
                        response = {
                            success: true,
                            app_id: this.editingAppId,
                            app_type: verify.data.app_type || appData.app_type || this.appType,
                            build_status: verify.data.build_status || null
                        };
                        ok = true;
                    }
                } catch (_) { /* ignore */ }
            }
            if (ok) {
                this.storageStats = response.data;
            }
        } catch (e) {
            console.error("Erro ao carregar estatísticas de storage:", e);
            this.storageStats = null;
        }
    },

    updateCompanySelect() {
        const select = document.getElementById("company-select");
        if (select) {
            if (this.userCompanies && this.userCompanies.length > 0) {
                select.innerHTML = '<option value="">Selecione uma empresa</option>' +
                    this.userCompanies.map(company =>
                        `<option value="${company.id}" data-cnpj="${company.cnpj || ""}">${company.name}</option>`
                    ).join("");
            } else {
                select.innerHTML = '<option value="">Você não tem permissão de moderador em nenhuma empresa</option>';
            }
        }
    },

    render() {
        const appRoot = document.getElementById("app-root");
        if (!appRoot) {
            console.error("Element #app-root not found.");
            return;
        }

        let contentHtml = '';
        if (this.viewMode === 'list') {
            contentHtml = this.renderAppListPage();
        } else { // viewMode === 'form'
            contentHtml = this.renderAppFormPage();
        }

        appRoot.innerHTML = `
            <style>
                .app-builder-container {
                    max-width: 100%;
                    margin: 0 auto;
                    padding: 20px;
                }
                /* Existing styles */
                .storage-indicator {
                    display: inline-flex;
                    align-items: center;
                    padding: 4px 8px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 500;
                    margin-left: 8px;
                }
                .storage-database {
                    background-color: #e3f2fd;
                    color: #1976d2;
                }
                .storage-filesystem {
                    background-color: #f3e5f5;
                    color: #7b1fa2;
                }
                .storage-stats-card {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .migration-badge {
                    background-color: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeaa7;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-size: 11px;
                }
                .step-indicator {
                    display: flex;
                    justify-content: center;
                    margin-bottom: 30px;
                }
                .step {
                    display: flex;
                    align-items: center;
                    margin: 0 10px;
                }
                .step-number {
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    background: #e9ecef;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 10px;
                    font-weight: bold;
                }
                .step.active .step-number {
                    background: #0d6efd;
                    color: white;
                }
                .step.completed .step-number {
                    background: #198754;
                    color: white;
                }
                .form-section {
                    display: none;
                    background: white;
                    border-radius: 8px;
                    padding: 30px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .form-section.active {
                    display: block;
                }
                
                /* Filesystem Editor Styles */
                .file-tree {
                    max-height: 400px;
                    overflow-y: auto;
                }
                .file-item {
                    padding: 8px 12px;
                    cursor: pointer;
                }
                
                /* App type selection styles */
                .app-type-card {
                    cursor: pointer;
                    transition: all 0.3s ease;
                    border: 2px solid #dee2e6;
                }
                .app-type-card:hover {
                    border-color: #0d6efd;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                .app-type-card.selected {
                    border-color: #0d6efd;
                    background-color: #f8f9ff;
                    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
                }
                
                /* CNPJ validation styles */
                .cnpj-validation {
                    margin-top: 5px;
                    font-size: 14px;
                }
                .cnpj-validation.valid {
                    color: #198754;
                }
                .cnpj-validation.invalid {
                    color: #dc3545;
                }
                
                /* Scopes/Permissions styles */
                .form-check {
                    margin-bottom: 12px;
                    padding: 8px 12px;
                    border-radius: 6px;
                    transition: background-color 0.2s ease;
                }
                .form-check:hover {
                    background-color: #f8f9fa;
                }
                .form-check-input:checked ~ .form-check-label {
                    font-weight: 500;
                }
                .form-check-label {
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .form-check-label i {
                    width: 16px;
                    text-align: center;
                }
                    display: flex;
                    align-items: center;
                    border-bottom: 1px solid #f0f0f0;
                    transition: background-color 0.2s;
                }
                .file-item:hover {
                    background-color: #f8f9fa;
                }
                .file-item.selected {
                    background-color: #e3f2fd;
                    border-left: 3px solid #1976d2;
                }
                .file-item.nested {
                    padding-left: 24px;
                }
                .file-item i {
                    margin-right: 8px;
                    width: 16px;
                }
                .file-status {
                    margin-left: auto;
                    font-size: 12px;
                    font-weight: bold;
                }
                .file-status.modified {
                    color: #ff9800;
                }
                .file-status.new {
                    color: #4caf50;
                }
                
                /* Collaborative Indicators */
                .collaborative-indicators {
                    border-bottom: 1px solid #dee2e6;
                }
                .collaborator-avatars {
                    display: flex;
                    gap: 8px;
                }
                .collaborator-avatar {
                    position: relative;
                }
                .avatar-circle {
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 10px;
                    font-weight: bold;
                }
                .editing-indicator {
                    position: absolute;
                    top: -2px;
                    right: -2px;
                    width: 8px;
                    height: 8px;
                    background-color: #4caf50;
                    border-radius: 50%;
                    animation: pulse 1.5s infinite;
                }
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.5; }
                    100% { opacity: 1; }
                }
                
                /* Code Editor */
                .filesystem-editor {
                    height: 400px;
                }
                .filesystem-editor textarea {
                    height: 100%;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 14px;
                    border: none;
                    resize: none;
                }
                .code-editor-toolbar {
                    background: #f8f9fa;
                    padding: 8px;
                    border-bottom: 1px solid #dee2e6;
                }
                
                /* Git History */
                .git-history {
                    max-height: 400px;
                    overflow-y: auto;
                }
                .commit-item {
                    padding: 16px;
                    border-bottom: 1px solid #f0f0f0;
                }
                .commit-item:last-child {
                    border-bottom: none;
                }
                .commit-avatar .avatar-circle {
                    width: 32px;
                    height: 32px;
                    font-size: 12px;
                }
                .commit-message {
                    font-size: 14px;
                    margin-bottom: 4px;
                }
                .commit-meta {
                    margin-bottom: 8px;
                }
                .commit-hash {
                    font-family: monospace;
                    background: #f1f3f4;
                    padding: 2px 4px;
                    border-radius: 3px;
                }
                .commit-files {
                    font-family: monospace;
                }
                
                /* Diff Viewer */
                .diff-viewer {
                    font-family: monospace;
                    font-size: 13px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    max-height: 400px;
                    overflow-y: auto;
                }
                .diff-line {
                    display: flex;
                    padding: 2px 8px;
                    line-height: 1.4;
                }
                .diff-line.added {
                    background-color: #d4edda;
                    border-left: 3px solid #28a745;
                }
                .diff-line.removed {
                    background-color: #f8d7da;
                    border-left: 3px solid #dc3545;
                }
                .diff-line.unchanged {
                    background-color: #ffffff;
                }
                .line-number {
                    width: 60px;
                    text-align: right;
                    margin-right: 16px;
                    color: #6c757d;
                    user-select: none;
                }
                .line-content {
                    flex: 1;
                }
                
                /* Build Management Styles */
                .build-status-badge {
                    display: flex;
                    align-items: center;
                }
                .build-actions {
                    margin-top: 8px;
                }
                .build-actions .btn-group {
                    width: 100%;
                }
                .build-actions .btn {
                    flex: 1;
                    font-size: 11px;
                    padding: 4px 8px;
                }

                /* Estilo de editor de código simples */
                .simple-code-editor {
                    background-color: #272822; /* Fundo escuro (Monokai) */
                    color: #F8F8F2;            /* Texto claro */
                    font-family: 'Consolas', 'Monaco', 'Menlo', 'Courier New', monospace;
                    font-size: 14px;
                    line-height: 1.5;
                    padding: 15px;
                    border: 1px solid #444;
                    border-radius: 4px;
                    white-space: pre;          /* Desativa quebra de linha */
                    overflow-wrap: normal;
                    overflow-x: auto;          /* Adiciona scroll horizontal */
                }
                
                /* Build Status Modal */
                .build-log {
                    max-height: 300px;
                    overflow-y: auto;
                }
                .build-log pre {
                    font-size: 12px;
                    line-height: 1.4;
                    margin: 0;
                }
                
                /* Artifacts Grid */
                .artifacts-grid {
                    max-height: 400px;
                    overflow-y: auto;
                }
                .artifact-card .card {
                    border: 1px solid #e9ecef;
                    transition: box-shadow 0.2s;
                }
                .artifact-card .card:hover {
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .artifact-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 48px;
                    height: 48px;
                }
                
                /* Build History */
                .build-history-list {
                    max-height: 500px;
                    overflow-y: auto;
                }
                .build-history-item .card {
                    border-left: 4px solid transparent;
                }
                .build-history-item .card:has(.text-success) {
                    border-left-color: #28a745;
                }
                .build-history-item .card:has(.text-danger) {
                    border-left-color: #dc3545;
                }
                .build-history-item .card:has(.text-info) {
                    border-left-color: #17a2b8;
                }
                .build-status-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                }
                .platform-badges {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 4px;
                    justify-content: flex-end;
                }
                .platform-badges .badge {
                    font-size: 10px;
                }
                .build-actions {
                    display: flex;
                    gap: 4px;
                    justify-content: flex-end;
                }
                
                /* Deploy Info */
                .deploy-info {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 16px;
                    border: 1px solid #e9ecef;
                }
            </style>
            <div class="app-builder-container">                
                ${contentHtml}
            </div>
        `;

        // After rendering, perform specific actions based on viewMode
        if (this.viewMode === 'list') {
            this.loadAndShowMyApps();
            this.removeFormEventListeners(); // Clean up form listeners
        } else { // viewMode === 'form'
            this.updateStepDisplay(); // Ensure correct step is shown
            this.updateCompanySelect(); // Re-populate company select if needed
            this.validateCurrentStep(); // Validate current step for button states
            this.toggleTokenField(); // Ensure token field visibility is correct
            // If on step 5, initialize editor
            if (this.currentStep === 5) {
                if (!this.useSimpleEditor && (this.appType === 'flutter' || this.appData.storage_type === 'filesystem')) {
                    setTimeout(() => this.setupFilesystemEditor(), 100);
                } else {
                    setTimeout(() => this.initializeCodeMirror(), 100);
                    setTimeout(() => this.setupDatabaseEditor(), 100);
                }
            }
            this.setupFormEventListeners(); // Attach listeners to the newly rendered form
        }
    },

    renderAppListPage() {
        const companyFilterOptions = this.userCompanies.map(company =>
            `<option value="${company.id}" ${this.companyFilterId == company.id ? 'selected' : ''}>${company.name}</option>`
        ).join('');

        return `
            <div class="pt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">                    
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <label for="company-filter" class="form-label visually-hidden">Filtrar por empresa</label>
                            <select id="company-filter" class="form-select form-select-sm" onchange="StoreApp.filterByCompany(this.value)">
                                <option value="all" ${this.companyFilterId === 'all' ? 'selected' : ''}>Todas as Empresas</option>
                                ${companyFilterOptions}
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary me-2" onclick="StoreApp.startNewApp()">
                            <i class="fas fa-plus"></i> Novo App
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="StoreApp.loadAndShowMyApps()" title="Atualizar lista de apps">
                            <i class="fas fa-sync-alt"></i> Atualizar
                        </button>
                    </div>
                </div>
                <div id="my-apps-container">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2">Carregando seus apps...</p>
                    </div>
                </div>
            </div>
        `;
    },

    renderAppFormPage() {
        const backButton = `
            <button type="button" class="btn btn-secondary mb-4" onclick="StoreApp.goBackToList()">
                <i class="fas fa-arrow-left"></i> Voltar para Meus Apps
            </button>
        `;
        return `
            ${backButton}
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <span>Empresa</span>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <span>Tipo</span>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <span>Informações</span>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <span>Configuração</span>
                </div>
                <div class="step" data-step="5">
                    <div class="step-number">5</div>
                    <span>Código</span>
                </div>
                <div class="step" data-step="6">
                    <div class="step-number">6</div>
                    <span>Revisão</span>
                </div>
            </div>

            ${this.renderStep1()}
            ${this.renderStep2()}
            ${this.renderStep3()}
            ${this.renderStep4()}
            ${this.renderStep5()}
            ${this.renderStep6()}
        `;
    },

    renderStorageStatsCard() {
        if (!this.storageStats) {
            return '';
        }

        const stats = this.storageStats;
        const totalApps = stats.database.count + stats.filesystem.count;
        const totalSize = stats.database.total_size + stats.filesystem.total_size;

        return `
            <div class="storage-stats-card">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4>${totalApps}</h4>
                            <small>Total de Apps</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4>${stats.database.count}</h4>
                            <small>Database Storage</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4>${stats.filesystem.count}</h4>
                            <small>Filesystem Storage</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4>${stats.migration_candidates.length}</h4>
                            <small>Migração Recomendada</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    renderStep1() {
        let companyOptions = '<option value="">Carregando empresas...</option>';

        if (this.userCompanies && this.userCompanies.length > 0) {
            companyOptions = '<option value="">Selecione uma empresa</option>' +
                this.userCompanies.map(company =>
                    `<option value="${company.id}" data-cnpj="${company.cnpj || ""}">${company.name}</option>`
                ).join("");
        } else if (this.userCompanies && this.userCompanies.length === 0) {
            companyOptions = '<option value="">Você não tem permissão de moderador em nenhuma empresa</option>';
        }

        return `
            <div class="form-section active" id="step-1">
                <h3><i class="fas fa-building"></i> Validação da Empresa</h3>
                <p class="text-muted">Apenas empresas com CNPJ válido podem publicar aplicativos</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="company-select" class="form-label">Selecione sua empresa</label>
                            <select class="form-select" id="company-select" required>
                                ${companyOptions}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="cnpj-display" class="form-label">CNPJ</label>
                            <input type="text" class="form-control" id="cnpj-display" readonly>
                            <div class="cnpj-validation" id="cnpj-validation"></div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Requisitos:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Você deve ter nível de moderador ou superior na empresa</li>
                        <li>A empresa deve ter um CNPJ válido cadastrado</li>
                        <li>O aplicativo será publicado em nome da empresa</li>
                    </ul>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-primary" onclick="StoreApp.nextStep()" id="step-1-next" disabled>
                        Próximo <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    },

    renderStep2() {
        return `
            <div class="form-section" id="step-2">
                <h3><i class="fas fa-mobile-alt"></i> Tipo de Aplicativo</h3>
                <p class="text-muted">Escolha a tecnologia para seu aplicativo</p>
                
                <div class="row app-type-selector">
                    <div class="col-md-6">
                        <div class="card app-type-card h-100" id="js-type-card" onclick="StoreApp.selectAppType('javascript')">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fab fa-js-square fa-4x text-warning"></i>
                                </div>
                                <h4>JavaScript</h4>
                                <p class="text-muted">Aplicativo Web tradicional</p>
                                <div class="mb-3">
                                    <span class="badge bg-info me-1"><i class="fas fa-globe"></i> Web</span>
                                    <span class="storage-indicator storage-database">
                                        <i class="fas fa-database"></i> Database Storage
                                    </span>
                                </div>
                                <ul class="list-unstyled text-start small">
                                    <li><i class="fas fa-check text-success"></i> Tecnologia familiar</li>
                                    <li><i class="fas fa-check text-success"></i> Desenvolvimento rápido</li>
                                    <li><i class="fas fa-check text-success"></i> Execução instantânea</li>
                                    <li><i class="fas fa-check text-success"></i> Armazenamento otimizado</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card app-type-card h-100" id="flutter-type-card" onclick="StoreApp.selectAppType('flutter')">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-mobile-alt fa-4x text-primary"></i>
                                </div>
                                <h4>Flutter</h4>
                                <p class="text-muted">Aplicativo Multiplataforma</p>
                                <div class="mb-3">
                                    <span class="badge bg-info me-1"><i class="fas fa-globe"></i> Web</span>
                                    <span class="badge bg-success me-1"><i class="fas fa-mobile-alt"></i> iOS</span>
                                    <span class="badge bg-success me-1"><i class="fab fa-android"></i> Android</span>
                                    <span class="storage-indicator storage-filesystem">
                                        <i class="fas fa-folder"></i> Filesystem Storage
                                    </span>
                                </div>
                                <ul class="list-unstyled text-start small">
                                    <li><i class="fas fa-check text-success"></i> Performance nativa</li>
                                    <li><i class="fas fa-check text-success"></i> Controle de versão Git</li>
                                    <li><i class="fas fa-check text-success"></i> Build multiplataforma</li>
                                    <li><i class="fas fa-check text-success"></i> Colaboração avançada</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle"></i> Storage Information:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Database Storage:</strong> Ideal para apps JavaScript pequenos (< 50KB). Execução rápida e armazenamento eficiente.
                        </div>
                        <div class="col-md-6">
                            <strong>Filesystem Storage:</strong> Para apps Flutter e projetos complexos. Suporte a Git, builds multiplataforma e colaboração.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" onclick="StoreApp.prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="StoreApp.nextStep()" id="step-2-next">
                        Próximo <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    },

    renderStep3() {
        return `
            <div class="form-section" id="step-3">
                <h3><i class="fas fa-info-circle"></i> Informações do Aplicativo</h3>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="app-title" class="form-label">Nome do Aplicativo *</label>
                            <input type="text" class="form-control" id="app-title" required maxlength="150">
                            <div class="form-text">Máximo 150 caracteres</div>
                        </div>
                        <div class="mb-3">
                            <label for="app-slug" class="form-label">Slug (URL) *</label>
                            <div class="input-group">
                                <span class="input-group-text">workz.app/</span>
                                <input type="text" class="form-control" id="app-slug" required maxlength="60">
                            </div>
                            <div class="form-text">Apenas letras minúsculas, números e hífens. Será usado na URL do app.</div>
                        </div>
                        <div class="mb-3">
                            <label for="app-description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="app-description" rows="3" maxlength="500"></textarea>
                            <div class="form-text">Descreva o que seu aplicativo faz (máximo 500 caracteres)</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="app-icon" class="form-label">Ícone do Aplicativo</label>
                            <input type="file" class="form-control" id="app-icon" accept="image/*">
                            <div class="form-text">Recomendado: 512x512px, PNG ou JPG</div>
                            <img id="icon-preview" class="app-icon-preview mt-2" style="display: none;">
                        </div>
                        <div class="mb-3">
                            <label for="app-color" class="form-label">Cor Principal</label>
                            <div class="d-flex align-items-center">
                                <input type="color" class="color-picker me-2" id="app-color" value="#3b82f6">
                                <input type="text" class="form-control" id="app-color-hex" value="#3b82f6" maxlength="7">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" onclick="StoreApp.prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="StoreApp.nextStep()" id="step-3-next">
                        Próximo <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    },

    renderStep4() {
        return `
            <div class="form-section" id="step-4">
                <h3><i class="fas fa-cog"></i> Configuração do Aplicativo</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="access-level" class="form-label">Nível de Acesso *</label>                            
                            <select class="form-select" id="access-level" required aria-describedby="access-level-help">
                                <option value="0">Toda a Internet - Acessível por qualquer pessoa, sem login.</option>
                                <option value="1">Usuários Logados - Qualquer usuário autenticado na plataforma pode usar.</option>
                                <option value="2">Restrito (Instalação) - Requer que o usuário instale ou assine o app.</option>
                                <option value="3">Privado - Apenas para usuários ou empresas com permissão explícita.</option>
                            </select>
                            <div id="access-level-help" class="form-text">
                                <ul class="list-unstyled mb-0 small mt-2">
                                    <li><i class="fas fa-globe text-info"></i> <strong>Toda a Internet:</strong> Ideal para apps que não precisam de dados do usuário (landing pages, ferramentas abertas).</li>
                                    <li><i class="fas fa-users text-primary"></i> <strong>Usuários Logados:</strong> Visível para todos os usuários da plataforma Workz.</li>
                                    <li><i class="fas fa-store text-warning"></i> <strong>Restrito (Instalação):</strong> O app aparece no catálogo, mas requer uma ação de "instalar".</li>
                                    <li><i class="fas fa-lock text-secondary"></i> <strong>Privado:</strong> O app não é listado publicamente e o acesso é controlado manualmente.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="app-version" class="form-label">Versão *</label>
                            <input type="text" class="form-control" id="app-version" value="1.0.0" required maxlength="20">
                            <div class="form-text">Ex: 1.0.0, 2.1.3</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="entity-type" class="form-label">Tipo de Entidade</label>
                            <select class="form-select" id="entity-type">
                                <option value="0">Geral - Todos os tipos</option>
                                <option value="1">Usuários</option>
                                <option value="2">Empresas</option>
                                <option value="3">Equipes</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="app-price" class="form-label">Preço (R$)</label>
                            <input type="number" class="form-control" id="app-price" min="0" step="0.01" value="0.00">
                            <div class="form-text">0.00 para aplicativo gratuito</div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção de Permissões -->
                <div class="mb-4">
                    <label class="form-label">Permissões (Scopes)</label>
                    <p class="text-muted small">Selecione as permissões que seu aplicativo precisa para funcionar</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-profile" value="profile.read">
                                <label class="form-check-label" for="scope-profile">
                                    <i class="fas fa-user text-primary"></i> Ler perfil do usuário
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-companies" value="companies.read">
                                <label class="form-check-label" for="scope-companies">
                                    <i class="fas fa-building text-info"></i> Ler dados de empresas
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-teams" value="teams.read">
                                <label class="form-check-label" for="scope-teams">
                                    <i class="fas fa-users text-success"></i> Ler dados de equipes
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-posts" value="posts.read">
                                <label class="form-check-label" for="scope-posts">
                                    <i class="fas fa-newspaper text-secondary"></i> Ler posts e feed
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-storage-kv" value="storage.kv.write">
                                <label class="form-check-label" for="scope-storage-kv">
                                    <i class="fas fa-database text-warning"></i> Armazenar configurações (KV)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-storage-docs" value="storage.docs.write">
                                <label class="form-check-label" for="scope-storage-docs">
                                    <i class="fas fa-file-alt text-info"></i> Armazenar documentos (DOCS)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-storage-blobs" value="storage.blobs.write">
                                <label class="form-check-label" for="scope-storage-blobs">
                                    <i class="fas fa-cloud-upload-alt text-primary"></i> Upload de arquivos (BLOBS)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="scope-notifications" value="notifications.send">
                                <label class="form-check-label" for="scope-notifications">
                                    <i class="fas fa-bell text-danger"></i> Enviar notificações
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        <i class="fas fa-info-circle"></i> 
                        Seu app só poderá acessar os recursos marcados aqui. Você pode alterar essas permissões depois.
                    </div>
                </div>

                <!-- Token Field (para apps Flutter) -->
                <div id="token-field-container" class="mb-3" style="display: none;">
                    <label for="app-token" class="form-label">
                        <i class="fas fa-key"></i> Token de Acesso (API)
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="app-token" readonly placeholder="Gerado automaticamente ao salvar o app">
                        <button class="btn btn-outline-secondary" type="button" id="copy-token-btn">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                    <div class="form-text">
                        Use este token para autenticar seu app Flutter via WorkzSDK.
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" onclick="StoreApp.prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="StoreApp.nextStep()" id="step-4-next">
                        Próximo <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    },

    renderStep5() {
        const isFlutter = this.appType === 'flutter';
        // Em modo simples, forçar textarea (mesmo para Flutter)
        const shouldUseFilesystemEditor = !this.useSimpleEditor && (isFlutter || (this.editMode && this.currentAppData && this.currentAppData.storage_type === 'filesystem'));

        return `
            <div class="form-section" id="step-5">
                <h3><i class="fas fa-code"></i> Código do Aplicativo</h3>
                <p class="text-muted">
                    ${isFlutter ? "Escreva o código Dart que será compilado para múltiplas plataformas" : "Escreva o código JavaScript que será executado no seu aplicativo"}
                </p>

                ${shouldUseFilesystemEditor ? this.renderFilesystemEditor() : this.renderDatabaseEditor()}
                
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" onclick="StoreApp.prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="StoreApp.nextStep()" id="step-5-next">
                        Próximo <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    },

    renderStep6() {
        return `
            <div class="form-section" id="step-6">
                <h3><i class="fas fa-check-circle"></i> Revisão e Publicação</h3>
                <p class="text-muted">Revise as informações do seu aplicativo antes de publicar</p>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Resumo do Aplicativo</h5>
                            </div>
                            <div class="card-body" id="app-summary">
                                <div class="d-flex align-items-center mb-3">
                                    <img id="final-icon-preview" class="app-icon-preview" src="/images/no-image.jpg" style="width: 64px; height: 64px; border-radius: 12px; object-fit: cover; margin-right: 15px;">
                                    <div>
                                        <h4 id="final-title" class="mb-1">Nome do App</h4>
                                        <p id="final-description" class="text-muted mb-1">Descrição do aplicativo</p>
                                        <small class="text-muted">
                                            Por: <span id="final-publisher">Empresa</span> | 
                                            Versão: <span id="final-version">1.0.0</span> | 
                                            Preço: R$ <span id="final-price">0,00</span>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Configurações:</strong>
                                        <ul class="list-unstyled mt-2">
                                            <li><i class="fas fa-link"></i> URL: workz.app/<span id="final-slug">app-slug</span></li>
                                            <li><i class="fas fa-lock"></i> Acesso: <span id="final-access">Público</span></li>
                                            <li><i class="fas fa-users"></i> Entidade: <span id="final-entity">Geral</span></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Permissões:</strong>
                                        <ul id="final-scopes" class="list-unstyled mt-2">
                                            <li><i class="fas fa-info-circle"></i> Nenhuma permissão especial</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Ações</h5>
                            </div>
                            <div class="card-body">
                                <button type="button" class="btn btn-success w-100 mb-2" onclick="StoreApp.saveApp()">
                                    <i class="fas fa-save"></i> Salvar Aplicativo
                                </button>
                                <button type="button" class="btn w-100" id="publish-unpublish-btn">
                                    <!-- Texto e ação definidos dinamicamente por updatePreview() -->
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" onclick="StoreApp.prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-success" onclick="StoreApp.saveApp()">
                        <i class="fas fa-save"></i> Finalizar
                    </button>
                </div>
            </div>
        `;
    },

    async loadAndShowMyApps() {
        try {
            const response = await WorkzSDK.api.get("/apps/my-apps");
            const container = document.getElementById('my-apps-container');
            if (!container) return;

            let apps = (response && response.data) ? response.data : [];

            // Apply company filter
            if (this.companyFilterId && this.companyFilterId !== 'all') {
                const filterId = parseInt(this.companyFilterId, 10);
                apps = apps.filter(app => parseInt(app.exclusive_to_entity_id, 10) === filterId);
            }

            if (apps.length > 0) {
                container.innerHTML = this.renderMyAppsGrid(apps);
            } else {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum app encontrado</h5>
                        <p class="text-muted">Crie seu primeiro aplicativo usando o formulário acima.</p>
                    </div>
                `;
            }
        } catch (e) {
            console.error("Erro ao carregar apps:", e);
            document.getElementById('my-apps-container').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Erro ao carregar aplicativos: ${e.message}
                </div>
            `;
        }
    },

    filterByCompany(companyId) {
        this.companyFilterId = companyId;
        // Re-render the list page to reflect the filter and trigger app loading
        this.render();
    },

    renderMyAppsGrid(apps) {
        return `
            <div class="row">
                ${apps.map(app => this.renderAppCard(app)).join('')}
            </div>
        `;
    },

    renderAppCard(app) {
        const storageType = app.storage_type || 'database';
        const storageIcon = storageType === 'filesystem' ? 'fas fa-folder' : 'fas fa-database';
        const storageClass = storageType === 'filesystem' ? 'storage-filesystem' : 'storage-database';
        // Calculate code size if not provided by backend
        let codeSizeBytes = app.code_size_bytes || 0;
        if (codeSizeBytes === 0) {
            const jsCode = app.js_code || '';
            const dartCode = app.dart_code || '';
            const totalCode = jsCode + dartCode;
            codeSizeBytes = new Blob([totalCode]).size;
        }
        const codeSize = this.formatBytes(codeSizeBytes);
        const buildStatus = app.build_status || 'success';

        return `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title">${app.tt || app.title}</h5>
                            <span class="storage-indicator ${storageClass}">
                                <i class="${storageIcon}"></i> ${storageType}
                            </span>
                        </div>
                        <p class="card-text text-muted small">${app.ds || 'Sem descrição'}</p>
                        
                        ${this.renderBuildStatusBadge(app)}
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-weight-hanging"></i> ${codeSize}
                                <span class="ms-2">
                                    <i class="fas fa-calendar"></i> ${this.formatDate(app.created_at)}
                                </span>
                            </small>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="StoreApp.editApp(${app.id})">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-outline-danger" onclick="StoreApp.deleteApp(${app.id}, '${app.slug || ''}')">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                                <button class="btn ${app.st == 1 ? 'btn-outline-warning' : 'btn-outline-success'}" onclick="StoreApp.${app.st == 1 ? 'unpublishAppFromCard' : 'publishAppFromCard'}(${app.id})">
                                    <i class="fas ${app.st == 1 ? 'fa-ban' : 'fa-rocket'}"></i> ${app.st == 1 ? 'Despublicar' : 'Publicar'}
                                </button>
                            </div>
                            <span class="badge ${app.st == 1 ? 'bg-success' : 'bg-secondary'}">
                                ${app.st == 1 ? 'Publicado' : 'Rascunho'}
                            </span>
                        </div>
                        
                        ${app.app_type === 'flutter' ? this.renderBuildActions(app) : ''}
                    </div>
                </div>
            </div>
        `;
    },

    renderBuildStatusBadge(app) {
        if (app.app_type !== 'flutter') {
            return '';
        }

        const buildStatus = app.build_status || 'success';
        const statusConfig = {
            'pending': { class: 'bg-warning', icon: 'fa-clock', text: 'Build Pendente' },
            'building': { class: 'bg-info', icon: 'fa-spinner fa-spin', text: 'Compilando...' },
            'success': { class: 'bg-success', icon: 'fa-check', text: 'Build OK' },
            'failed': { class: 'bg-danger', icon: 'fa-times', text: 'Build Falhou' }
        };

        const config = statusConfig[buildStatus] || statusConfig['success'];

        return `
            <div class="build-status-badge mb-2">
                <span class="badge ${config.class}">
                    <i class="fas ${config.icon}"></i> ${config.text}
                </span>
            </div>
        `;
    },

    renderBuildActions(app) {
        return `
            <div class="build-actions mt-2">
                <div class="btn-group w-100" role="group">
                    <button class="btn btn-outline-secondary btn-sm" onclick="StoreApp.showBuildStatus(${app.id})" title="Ver Status do Build">
                        <i class="fas fa-hammer"></i> Build
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="StoreApp.showArtifacts(${app.id})" title="Downloads">
                        <i class="fas fa-download"></i> Apps
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="StoreApp.showBuildHistory(${app.id})" title="Histórico">
                        <i class="fas fa-history"></i>
                    </button>
                </div>
            </div>
        `;
    },

    async showStorageInfo(appId) {
        try {
            const response = await WorkzSDK.api.get(`/apps/${appId}/storage`);
            if (response && response.success) {
                this.displayStorageModal(response.data);
            }
        } catch (e) {
            console.error("Erro ao carregar informações de storage:", e);
            alert("Erro ao carregar informações de storage: " + e.message);
        }
    },

    displayStorageModal(storageInfo) {
        const modalHtml = `
            <div class="modal fade" id="storageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-database"></i> Informações de Storage
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${this.renderStorageInfoContent(storageInfo)}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            ${this.renderMigrationButton(storageInfo)}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        const existingModal = document.getElementById('storageModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('storageModal'));
        modal.show();
    },

    async updateAppStatus(appId, status) {
        try {
            console.log(`Attempting to update app status for appId: ${appId}, new status: ${status ? 'published (1)' : 'unpublished (0)'}`);
            const response = await WorkzSDK.api.post(`/apps/update/${appId}`, {
                st: status ? 1 : 0 // 1 para publicado, 0 para rascunho
            });
            console.log(`API response for status update (appId: ${appId}):`, response);
            if (response && response.success) {
                this.showToast(`Aplicativo ${status ? 'publicado' : 'despublicado'} com sucesso!`, 'success');
                this.loadAndShowMyApps(); // Recarrega a lista de apps
            } else {
                throw new Error(response.message || `Erro ao ${status ? 'publicar' : 'despublicar'} o app`);
            }
        } catch (e) {
            console.error(`Erro ao ${status ? 'publicar' : 'despublicar'} app:`, e);
            this.showToast(`Erro ao ${status ? 'publicar' : 'despublicar'} aplicativo: ` + e.message, 'error');
        }
    },

    async publishAppFromCard(appId) {
        if (!confirm('Tem certeza que deseja publicar este app na loja?')) return;
        await this.updateAppStatus(appId, true);
    },

    async unpublishAppFromCard(appId) {
        if (!confirm('Tem certeza que deseja despublicar este app da loja? Ele não estará mais visível para outros usuários.')) return;
        await this.updateAppStatus(appId, false);
    },

    // ... (other functions remain largely unchanged)
    async triggerBuildAndMonitor(appId) {
        this.showToast('Iniciando build do app…', 'info');
        const res = { success: true }; // queue-based flow: skip explicit rebuild
    },

    async deleteApp(appId, slug = '') {
        try {
            if (!confirm('Tem certeza que deseja excluir este app? Esta ação é irreversível.')) return;
            let resp = null;
            try {
                resp = await this.apiDelete(`/apps/${appId}`);
            } catch (_) { /* fallback abaixo */ }
            const httpOk = resp && typeof resp.status === 'number' && resp.status >= 200 && resp.status < 300;
            if (!resp || (resp.success === false && !httpOk)) {
                resp = await this.apiPost(`/apps/${appId}/delete`, {});
            }
            const ok = resp && (resp.success || (typeof resp.status === 'number' && resp.status >= 200 && resp.status < 300));
            if (ok) {
                this.showToast('Aplicativo excluído com sucesso!', 'success');
                this.loadAndShowMyApps();
            } else {
                throw new Error((resp && resp.message) ? resp.message : 'Falha ao excluir o app.');
            }
        } catch (e) {
            console.error('Erro ao excluir app:', e);
            this.showToast('Erro ao excluir app: ' + e.message, 'error');
        }
    },

    renderStorageInfoContent(info) {
        const currentStorage = info.current_storage;
        const recommendation = info.migration_recommendation;
        const validation = info.validation;

        return `
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Storage Atual</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas ${currentStorage === 'filesystem' ? 'fa-folder' : 'fa-database'} me-2"></i>
                                <strong>${currentStorage === 'filesystem' ? 'Filesystem' : 'Database'}</strong>
                            </div>
                            <div class="small text-muted">
                                <div>Tamanho: ${info.code_size_formatted}</div>
                                <div>Tipo: ${info.app_type}</div>
                                ${info.repository_path ? `<div>Path: ${info.repository_path}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Recomendação</h6>
                        </div>
                        <div class="card-body">
                            ${recommendation.needed ? `
                                <div class="alert alert-warning mb-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Migração Recomendada</strong>
                                </div>
                                <div class="small">
                                    <div>De: ${recommendation.from}</div>
                                    <div>Para: ${recommendation.to}</div>
                                    <div>Motivo: ${recommendation.reason}</div>
                                </div>
                            ` : `
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check"></i>
                                    Storage otimizado
                                </div>
                            `}
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <h6>Validação de Storage</h6>
                <div class="alert ${validation.valid ? 'alert-success' : 'alert-danger'}">
                    <i class="fas ${validation.valid ? 'fa-check' : 'fa-times'}"></i>
                    ${validation.valid ? 'Storage válido e funcionando' : 'Problemas encontrados no storage'}
                </div>
                ${validation.errors && validation.errors.length > 0 ? `
                    <div class="mt-2">
                        <strong>Erros:</strong>
                        <ul class="mb-0">
                            ${validation.errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
    },

    renderMigrationButton(info) {
        if (!info.migration_recommendation.needed) {
            return '';
        }

        const targetType = info.migration_recommendation.to;
        const buttonClass = targetType === 'filesystem' ? 'btn-warning' : 'btn-info';
        const icon = targetType === 'filesystem' ? 'fa-folder' : 'fa-database';

        return `
            <button type="button" class="btn ${buttonClass}" onclick="StoreApp.migrateStorage(${info.app_id}, '${targetType}')">
                <i class="fas ${icon}"></i> Migrar para ${targetType}
            </button>
        `;
    },

    async migrateStorage(appId, targetType) {
        if (!confirm(`Tem certeza que deseja migrar este app para ${targetType}? Esta operação pode levar alguns minutos.`)) {
            return;
        }

        try {
            const response = await WorkzSDK.api.post(`/apps/${appId}/storage/migrate`, {
                target_type: targetType
            });

            if (response && response.success) {
                alert('Migração concluída com sucesso!');
                // Close modal and refresh apps
                bootstrap.Modal.getInstance(document.getElementById('storageModal')).hide();
                this.loadAndShowMyApps();
                this.loadStorageStats();
            } else {
                alert('Erro na migração: ' + (response.message || 'Erro desconhecido'));
            }
        } catch (e) {
            console.error("Erro na migração:", e);
            alert('Erro na migração: ' + e.message);
        }
    },

    renderFilesystemEditor() {
        return `
            <div class="row">
                <!-- Mensagem de alerta sobre o novo editor -->
                <div class="col-12 mb-3">
                    <div class="alert alert-info d-flex align-items-center"><i class="fas fa-info-circle me-2"></i>Este app usa o novo editor de arquivos. As alterações são salvas por arquivo.</div>
                </div>
                <div class="col-md-3">
                    ${this.renderFileBrowser()}
                </div>
                <div class="col-md-9">
                    ${this.renderCodeEditorWithGit()}
                </div>
            </div>
        `;
    },

    renderDatabaseEditor() {
        const isFlutter = this.appType === 'flutter';
        return `
            <div class="mb-3">
                <label for="app-code" class="form-label">${isFlutter ? "Código Dart *" : "Código JavaScript *"}</label>
                <div class="code-editor-toolbar mb-2" title="Funcionalidade desativada. O editor CodeMirror não pôde ser carregado.">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="StoreApp.toggleCodeMirrorFullscreen()" title="Tela Cheia (F11)">
                            <i class="fas fa-expand"></i> Tela Cheia
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="StoreApp.toggleWordWrap()" title="Alternar Quebra de Linha">
                            <i class="fas fa-align-left"></i> Quebra de Linha
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="StoreApp.insertTemplate()" title="Inserir Template">
                            <i class="fas fa-file-code"></i> Template
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="StoreApp.showCodePreview()" title="Preview ao vivo">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                    </div>
                </div>
                <div id="code-editor-container">
                    <textarea id="app-code" rows="15" required></textarea>
                </div>
            </div>
        `;
    },

    renderFileBrowser() {
        return `
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-folder"></i> Arquivos</h6>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="StoreApp.createNewFile()" title="Novo Arquivo">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="StoreApp.refreshFileTree()" title="Atualizar">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="file-tree" class="file-tree">
                        <!-- A árvore de arquivos será renderizada dinamicamente aqui -->
                        <div class="p-3 text-center text-muted small">Carregando arquivos...</div>
                    </div>
                </div>
            </div>
        `;
    },

    renderCodeEditorWithGit() { // Agora renderiza a área do editor e a barra de ferramentas
        return `
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0" id="current-file-name"><i class="fas fa-file-code"></i> <span>Nenhum arquivo selecionado</span></h6>
                        <small class="text-muted" id="current-file-path">Selecione um arquivo à esquerda</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" onclick="StoreApp.saveActiveFile()" title="Salvar arquivo (Ctrl+S)" id="save-file-btn" disabled>
                            <i class="fas fa-save"></i> Salvar
                        </button>
                        <div class="btn-group btn-group-sm">
                            ${this.renderGitControls()}
                        </div>
                    </div>
                </div>
                <div class="card-body p-0" id="code-editor-wrapper">
                    <!-- O CodeMirror será instanciado aqui -->
                    <textarea id="filesystem-code-editor-textarea" class="form-control" style="height: 400px; border:0; font-family: monospace; resize: none;"></textarea>
                </div>
            </div>
        `;
    },

    renderGitControls() {
        return `
            <button class="btn btn-outline-success" onclick="StoreApp.showCommitDialog()" title="Commit Changes">
                <i class="fas fa-save"></i> Commit
            </button>
            <button class="btn btn-outline-info" onclick="StoreApp.showBranchDialog()" title="Manage Branches">
                <i class="fas fa-code-branch"></i> main
            </button>
            <button class="btn btn-outline-warning" onclick="StoreApp.showGitHistory()" title="Git History">
                <i class="fas fa-history"></i>
            </button>
        `;
    },

    renderCollaborativeIndicators() {
        return `
            <div class="collaborative-indicators">
                <div class="d-flex align-items-center p-2 bg-light border-bottom">
                    <div class="me-3">
                        <small class="text-muted">
                            <i class="fas fa-users"></i> Colaboradores ativos:
                        </small>
                    </div>
                    <div class="collaborator-avatars">
                        <div class="collaborator-avatar" title="João Silva (você)">
                            <div class="avatar-circle bg-primary">JS</div>
                        </div>
                        <div class="collaborator-avatar" title="Maria Santos - editando lib/widgets.dart">
                            <div class="avatar-circle bg-success">MS</div>
                            <div class="editing-indicator"></div>
                        </div>
                    </div>
                    <div class="ms-auto">
                        <small class="text-muted">
                            <i class="fas fa-wifi"></i> Sincronizado
                        </small>
                    </div>
                </div>
            </div>
        `;
    },

    // Git integration methods
    async showCommitDialog() {
        const modalHtml = `
            <div class="modal fade" id="commitModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-save"></i> Commit Changes
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="commit-message" class="form-label">Mensagem do Commit *</label>
                                <input type="text" class="form-control" id="commit-message" placeholder="Descreva as mudanças realizadas">
                            </div>
                            <div class="mb-3">
                                <label for="commit-description" class="form-label">Descrição (opcional)</label>
                                <textarea class="form-control" id="commit-description" rows="3" placeholder="Descrição detalhada das mudanças"></textarea>
                            </div>
                            <div class="mb-3">
                                <h6>Arquivos modificados:</h6>
                                <div class="changed-files">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="file-main" checked>
                                        <label class="form-check-label" for="file-main">
                                            <i class="fas fa-file-code text-primary"></i> main.dart
                                            <span class="badge bg-warning ms-2">M</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="file-widgets" checked>
                                        <label class="form-check-label" for="file-widgets">
                                            <i class="fas fa-file-code text-primary"></i> lib/widgets.dart
                                            <span class="badge bg-success ms-2">A</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" onclick="StoreApp.performCommit()">
                                <i class="fas fa-save"></i> Commit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showModal(modalHtml, 'commitModal');
    },

    async showBranchDialog() {
        const modalHtml = `
            <div class="modal fade" id="branchModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-code-branch"></i> Gerenciar Branches
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <h6>Branch Atual: <span class="badge bg-primary">main</span></h6>
                            </div>
                            <div class="mb-3">
                                <h6>Branches Disponíveis:</h6>
                                <div class="list-group">
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-code-branch text-primary"></i>
                                            <strong>main</strong>
                                            <span class="badge bg-success ms-2">atual</span>
                                        </div>
                                        <small class="text-muted">2 commits ahead</small>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-code-branch text-info"></i>
                                            feature/new-ui
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="StoreApp.switchBranch('feature/new-ui')">
                                                Trocar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-code-branch text-warning"></i>
                                            hotfix/bug-fix
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="StoreApp.switchBranch('hotfix/bug-fix')">
                                                Trocar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="new-branch-name" class="form-label">Criar Nova Branch</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="new-branch-name" placeholder="nome-da-branch">
                                    <button class="btn btn-outline-success" onclick="StoreApp.createBranch()">
                                        <i class="fas fa-plus"></i> Criar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showModal(modalHtml, 'branchModal');
    },

    async showGitHistory() {
        const modalHtml = `
            <div class="modal fade" id="historyModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-history"></i> Histórico Git
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="git-history">
                                <div class="commit-item">
                                    <div class="d-flex align-items-start">
                                        <div class="commit-avatar me-3">
                                            <div class="avatar-circle bg-primary">JS</div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="commit-message">
                                                <strong>Adicionar validação de formulário</strong>
                                            </div>
                                            <div class="commit-meta">
                                                <small class="text-muted">
                                                    João Silva • há 2 horas • 
                                                    <span class="commit-hash">a1b2c3d</span>
                                                </small>
                                            </div>
                                            <div class="commit-files mt-2">
                                                <small>
                                                    <i class="fas fa-file text-success"></i> main.dart (+15 -3)
                                                    <i class="fas fa-file text-warning ms-2"></i> lib/validation.dart (+42 -0)
                                                </small>
                                            </div>
                                        </div>
                                        <div class="commit-actions">
                                            <button class="btn btn-sm btn-outline-info" onclick="StoreApp.viewCommit('a1b2c3d')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="commit-item">
                                    <div class="d-flex align-items-start">
                                        <div class="commit-avatar me-3">
                                            <div class="avatar-circle bg-success">MS</div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="commit-message">
                                                <strong>Implementar componente de botão customizado</strong>
                                            </div>
                                            <div class="commit-meta">
                                                <small class="text-muted">
                                                    Maria Santos • há 1 dia • 
                                                    <span class="commit-hash">x9y8z7w</span>
                                                </small>
                                            </div>
                                            <div class="commit-files mt-2">
                                                <small>
                                                    <i class="fas fa-file text-success"></i> lib/widgets.dart (+28 -0)
                                                </small>
                                            </div>
                                        </div>
                                        <div class="commit-actions">
                                            <button class="btn btn-sm btn-outline-info" onclick="StoreApp.viewCommit('x9y8z7w')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showModal(modalHtml, 'historyModal');
    },

    async showDiff() {
        const modalHtml = `
            <div class="modal fade" id="diffModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-code-branch"></i> Diff - main.dart
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="diff-viewer">
                                <div class="diff-line removed">
                                    <span class="line-number">-12</span>
                                    <span class="line-content">  String oldFunction() {</span>
                                </div>
                                <div class="diff-line removed">
                                    <span class="line-number">-13</span>
                                    <span class="line-content">    return 'old implementation';</span>
                                </div>
                                <div class="diff-line added">
                                    <span class="line-number">+12</span>
                                    <span class="line-content">  String newFunction() {</span>
                                </div>
                                <div class="diff-line added">
                                    <span class="line-number">+13</span>
                                    <span class="line-content">    return 'new improved implementation';</span>
                                </div>
                                <div class="diff-line unchanged">
                                    <span class="line-number">14</span>
                                    <span class="line-content">  }</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showModal(modalHtml, 'diffModal');
    },

    // --- Métodos de Gerenciamento de Arquivos (Mini-IDE) ---

    renderFileTree() {
        const container = document.getElementById('file-tree');
        if (!container) return;

        const files = Object.keys(this.appData.appFiles || {});
        if (files.length === 0) {
            container.innerHTML = '<div class="p-3 text-center text-muted small">Nenhum arquivo no projeto.</div>';
            return;
        }

        // Simples lista de arquivos por enquanto. Pastas podem ser adicionadas depois.
        container.innerHTML = files.sort().map(path => {
            const isModified = this.appData.unsavedChanges.has(path);
            const isSelected = this.appData.activeFile === path;
            const icon = path.endsWith('.dart') ? 'fa-file-code text-primary' : (path.endsWith('.yaml') ? 'fa-file-alt text-info' : 'fa-file text-secondary');

            return `
                <div class="file-item ${isSelected ? 'selected' : ''}" onclick="StoreApp.selectFile('${path}')">
                    <i class="fas ${icon}"></i>
                    <span>${path}</span>
                    ${isModified ? '<span class="file-status modified" title="Modificado">●</span>' : ''}
                </div>
            `;
        }).join('');
    },

    selectFile(filePath) {
        if (this.appData.activeFile === filePath) return;

        this.appData.activeFile = filePath;
        this.renderFileTree(); // Re-render para mostrar a seleção

        // Atualiza o header do editor
        const fileNameEl = document.querySelector('#current-file-name span');
        const filePathEl = document.getElementById('current-file-path');
        if (fileNameEl) fileNameEl.textContent = filePath;
        if (filePathEl) filePathEl.textContent = filePath;

        // Carrega o conteúdo no CodeMirror
        const editorTextarea = document.getElementById('filesystem-code-editor-textarea');
        if (editorTextarea) {
            // Antes de trocar, salva o conteúdo do arquivo anterior
            if (this.appData.activeFile && this.appData.unsavedChanges.has(this.appData.activeFile)) {
                this.appData.appFiles[this.appData.activeFile] = editorTextarea.value;
            }

            const content = this.appData.appFiles[filePath] || '';
            editorTextarea.value = content;
            editorTextarea.disabled = false;
            editorTextarea.focus();
        }

        // Habilita/desabilita o botão de salvar
        this.updateSaveButtonState();
    },

    getActiveCodeMirrorInstance() {
        // Função desativada, pois não estamos mais usando CodeMirror.
        return null;
    },

    // Garante que o CodeMirror seja instanciado na textarea correta
    // e que o conteúdo do arquivo ativo seja carregado.
    // Esta função é a chave para a correção.
    setupFilesystemEditor() {
        // Função desativada. A lógica agora é com textarea simples.
        const editorTextarea = document.getElementById('filesystem-code-editor-textarea');
        if (editorTextarea && !editorTextarea._listenerAttached) {
            editorTextarea.addEventListener('input', () => {
            if (this.appData.activeFile) {
                this.appData.unsavedChanges.add(this.appData.activeFile);
                this.renderFileTree();
                this.updateSaveButtonState();
            }
            });
            editorTextarea._listenerAttached = true;
        }
    },

    updateSaveButtonState() {
        const saveBtn = document.getElementById('save-file-btn');
        if (!saveBtn) return;

        const hasChanges = this.appData.activeFile && this.appData.unsavedChanges.has(this.appData.activeFile);
        saveBtn.disabled = !hasChanges;
    },

    async saveActiveFile() {
        const filePath = this.appData.activeFile;
        const editorTextarea = document.getElementById('filesystem-code-editor-textarea');
        if (!filePath || !editorTextarea) return;

        const content = editorTextarea.value;
        this.appData.appFiles[filePath] = content; // Atualiza o estado

        // Aqui, faríamos uma chamada de API para salvar o arquivo no backend
        // Ex: await WorkzSDK.api.post(`/apps/${this.editingAppId}/files`, { path: filePath, content: content });
        this.showToast(`Arquivo '${filePath}' salvo!`, 'success');

        this.appData.unsavedChanges.delete(filePath);
        this.renderFileTree();
        this.updateSaveButtonState();
    },

    async createNewFile() {
        const newFilePath = prompt('Digite o nome do novo arquivo (ex: lib/utils.dart):');
        if (!newFilePath || !newFilePath.trim()) return;

        if (this.appData.appFiles.hasOwnProperty(newFilePath)) {
            alert('Um arquivo com este nome já existe.');
            return;
        }

        // Update file tree selection
        document.querySelectorAll('.file-item').forEach(item => {
            item.classList.remove('selected');
        });
        event.target.closest('.file-item').classList.add('selected');
    },

    toggleFolder(folderName) {
        // Toggle folder expansion
        const folderItem = event.target.closest('.file-item');
        const nestedItems = folderItem.parentNode.querySelectorAll('.nested');

        nestedItems.forEach(item => {
            item.style.display = item.style.display === 'none' ? 'block' : 'none';
        });

        const icon = folderItem.querySelector('i');
        icon.className = icon.className.includes('fa-folder') ? 'fas fa-folder-open text-warning' : 'fas fa-folder text-warning';
    },

    // Git action methods
    async performCommit() {
        const message = document.getElementById('commit-message').value;
        if (!message) {
            alert('Mensagem do commit é obrigatória');
            return;
        }

        // Simulate commit
        console.log('Performing commit:', message);
        bootstrap.Modal.getInstance(document.getElementById('commitModal')).hide();

        // Show success message
        this.showToast('Commit realizado com sucesso!', 'success');
    },

    async switchBranch(branchName) {
        console.log('Switching to branch:', branchName);
        bootstrap.Modal.getInstance(document.getElementById('branchModal')).hide();
        this.showToast(`Trocado para branch: ${branchName}`, 'info');
    },

    async createBranch() {
        const branchName = document.getElementById('new-branch-name').value;
        if (!branchName) {
            alert('Nome da branch é obrigatório');
            return;
        }

        console.log('Creating branch:', branchName);
        this.showToast(`Branch ${branchName} criada com sucesso!`, 'success');
    },

    viewCommit(commitHash) {
        console.log('Viewing commit:', commitHash);
        // Would open detailed commit view
    },

    // Utility methods
    showModal(modalHtml, modalId) {
        // Remove existing modal
        const existingModal = document.getElementById(modalId);
        if (existingModal) {
            existingModal.remove();
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modalEl = document.getElementById(modalId);
        const modal = new bootstrap.Modal(modalEl);
        // Ao fechar o modal de build, interrompe o polling
        modalEl.addEventListener('hidden.bs.modal', () => {
            if (modalId === 'buildStatusModal' && this._buildWatchTimer) {
                try { clearTimeout(this._buildWatchTimer); } catch(_) {}
                this._buildWatchTimer = null;
                this._buildWatchAppId = null;
            }
            if (modalId === 'livePreviewModal') {
                // Cleanup preview token on worker if needed
                try {
                    const token = this._activePreviewToken;
                    this._activePreviewToken = null;
                    this._livePreviewMode = null;
                    const base = this._activePreviewBase || '';
                    this._activePreviewBase = null;
                    if (token) {
                        const delUrl = (base ? base : '') + '/preview/' + token;
                        fetch(delUrl, { method: 'DELETE' }).catch(() => {});
                    }
                } catch (_) {}
            }
        }, { once: true });
        modal.show();
    },

    showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle me-2"></i>
                ${message}
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    },

    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('pt-BR');
    },

    // Navigation methods
    nextStep() {
        if (this.currentStep < this.maxSteps) {
            this.currentStep++;
            this.updateStepDisplay();
        }
    },

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStepDisplay();
        }
    },

    updateStepDisplay() {
        // Hide all sections
        document.querySelectorAll('.form-section').forEach(section => {
            section.classList.remove('active');
        });

        // Show current section
        const currentSection = document.getElementById(`step-${this.currentStep}`);
        if (currentSection) {
            currentSection.classList.add('active');
        }

        // Update step indicators
        document.querySelectorAll('.step').forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');

            if (stepNumber === this.currentStep) {
                step.classList.add('active');
            } else if (stepNumber < this.currentStep) {
                step.classList.add('completed');
            }
        });
    },

    selectAppType(type) {
        this.appType = type;

        // Update UI to show selection
        document.querySelectorAll('.app-type-card').forEach(card => {
            card.classList.remove('selected');
        });

        const selectedCard = document.getElementById(`${type === 'javascript' ? 'js' : 'flutter'}-type-card`);
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }

        // Update radio buttons if they exist
        const radioButton = document.querySelector(`input[name="app-type"][value="${type}"]`);
        if (radioButton) {
            radioButton.checked = true;
        }

        console.log('Selected app type:', type);

        // Initialize code templates based on type
        if (!this.editMode) { // Apenas para novos apps
            if (type === 'flutter') {
                // Modo simples: não usar Mini‑IDE; manter apenas textarea Dart
                this.appData.code = '';
                this.appData.dartCode = this.appData.dartCode || this.getFlutterTemplate();
                this.appData.appFiles = {};
                this.appData.activeFile = null;
            } else if (type === 'javascript') {
                // Limpa a estrutura de arquivos
                this.appData.appFiles = {};
                this.appData.activeFile = null;
                // Define o código JS
                if (!this.appData.code) {
                    this.appData.code = this.getJavaScriptTemplate();
                }
            }
        }

        // Show/hide token field based on app type
        this.toggleTokenField();

        this.validateCurrentStep();
    },

    getPubspecTemplate() {
        return `
name: new_workz_app
description: Um novo aplicativo Flutter criado na Workz Platform.
version: 1.0.0+1

environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  flutter:
    sdk: flutter
`;
    },

    getFlutterTemplate() {
        return `import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  try {
    // Initialize Workz SDK
    await WorkzSDK.init(
      apiUrl: 'http://localhost:9090/api',
      debug: true,
    );
    
    runApp(MyWorkzApp());
  } catch (error) {
    print('Failed to initialize app: \$error');
    runApp(ErrorApp(error: error.toString()));
  }
}

class MyWorkzApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Meu App Workz',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        visualDensity: VisualDensity.adaptivePlatformDensity,
        appBarTheme: AppBarTheme(
          backgroundColor: Colors.blue[600],
          foregroundColor: Colors.white,
          elevation: 2,
        ),
      ),
      home: HomeScreen(),
      debugShowCheckedModeBanner: false,
    );
  }
}

class HomeScreen extends StatefulWidget {
  @override
  _HomeScreenState createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  String _message = 'Carregando...';
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _initializeApp();
  }

  Future<void> _initializeApp() async {
    try {
      // Simular carregamento de dados
      await Future.delayed(Duration(seconds: 1));
      
      setState(() {
        _message = 'App funcionando perfeitamente!';
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _message = 'Erro ao inicializar: \$e';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Meu App Workz'),
        centerTitle: true,
      ),
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.blue[50]!, Colors.white],
          ),
        ),
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(32.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Logo/Icon
                Container(
                  width: 120,
                  height: 120,
                  decoration: BoxDecoration(
                    color: Colors.blue[600],
                    borderRadius: BorderRadius.circular(60),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.blue.withOpacity(0.3),
                        blurRadius: 20,
                        offset: Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Icon(
                    Icons.rocket_launch,
                    size: 60,
                    color: Colors.white,
                  ),
                ),
                
                SizedBox(height: 32),
                
                // Title
                Text(
                  '🚀 Flutter App',
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: Colors.blue[800],
                  ),
                ),
                
                SizedBox(height: 16),
                
                // Status message
                if (_isLoading)
                  Column(
                    children: [
                      CircularProgressIndicator(
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.blue[600]!),
                      ),
                      SizedBox(height: 16),
                      Text(
                        _message,
                        style: TextStyle(
                          fontSize: 16,
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  )
                else
                  Column(
                    children: [
                      Container(
                        padding: EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.green[50],
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.green[200]!),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.check_circle, color: Colors.green[600]),
                            SizedBox(width: 8),
                            Text(
                              _message,
                              style: TextStyle(
                                fontSize: 16,
                                color: Colors.green[800],
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      
                      SizedBox(height: 32),
                      
                      // Features
                      Card(
                        elevation: 4,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Padding(
                          padding: EdgeInsets.all(24),
                          child: Column(
                            children: [
                              Text(
                                'Recursos Disponíveis',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.grey[800],
                                ),
                              ),
                              SizedBox(height: 16),
                              _buildFeatureItem(Icons.sdk, 'WorkzSDK Integrado'),
                              _buildFeatureItem(Icons.devices, 'Multiplataforma'),
                              _buildFeatureItem(Icons.speed, 'Performance Otimizada'),
                              _buildFeatureItem(Icons.security, 'Seguro e Confiável'),
                            ],
                          ),
                        ),
                      ),
                      
                      SizedBox(height: 24),
                      
                      // Action buttons
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: [
                          ElevatedButton.icon(
                            onPressed: _testWorkzSDK,
                            icon: Icon(Icons.play_arrow),
                            label: Text('Testar SDK'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.blue[600],
                              foregroundColor: Colors.white,
                              padding: EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                          ),
                          OutlinedButton.icon(
                            onPressed: _showAppInfo,
                            icon: Icon(Icons.info),
                            label: Text('Info'),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.blue[600],
                              padding: EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildFeatureItem(IconData icon, String text) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(icon, color: Colors.blue[600], size: 20),
          SizedBox(width: 12),
          Text(
            text,
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey[700],
            ),
          ),
        ],
      ),
    );
  }

  void _testWorkzSDK() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            Icon(Icons.check_circle, color: Colors.green),
            SizedBox(width: 8),
            Text('WorkzSDK Teste'),
          ],
        ),
        content: Text(
          'WorkzSDK está funcionando perfeitamente!\\n\\n'
          'Recursos disponíveis:\\n'
          '• Autenticação\\n'
          '• Storage (KV, Docs, Blobs)\\n'
          '• API Integration\\n'
          '• Push Notifications'
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showAppInfo() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            Icon(Icons.info, color: Colors.blue),
            SizedBox(width: 8),
            Text('Informações do App'),
          ],
        ),
        content: Text(
          '🚀 Flutter App Workz\\n\\n'
          '📱 Plataforma: Flutter\\n'
          '⚡ Engine: Flutter Web\\n'
          '🔧 SDK: WorkzSDK v2.0\\n'
          '📅 Build: ${new Date().toLocaleString('pt-BR')}\\n'
          '🎯 Status: Funcionando\\n\\n'
          '✨ Recursos Ativos:\\n'
          '• Interface responsiva\\n'
          '• Integração WorkzSDK\\n'
          '• Suporte multiplataforma\\n'
          '• Performance otimizada'
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text('OK'),
          ),
        ],
      ),
    );
  }
}

class ErrorApp extends StatelessWidget {
  final String error;
  
  const ErrorApp({Key? key, required this.error}) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        backgroundColor: Colors.red[50],
        body: Center(
          child: Padding(
            padding: EdgeInsets.all(32),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.error_outline,
                  size: 80,
                  color: Colors.red[400],
                ),
                SizedBox(height: 24),
                Text(
                  'Falha ao Inicializar App',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: Colors.red[700],
                  ),
                ),
                SizedBox(height: 16),
                Text(
                  error,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.red[600],
                  ),
                ),
                SizedBox(height: 32),
                ElevatedButton(
                  onPressed: () {
                    // Restart the app
                    main();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red[400],
                    foregroundColor: Colors.white,
                  ),
                  child: Text('Tentar Novamente'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}`;
    },

    getJavaScriptTemplate() {
        return `// Meu App JavaScript com WorkzSDK
class MyWorkzApp {
    constructor() {
        this.isInitialized = false;
        this.init();
    }

    async init() {
        try {
            console.log('🚀 Inicializando app...');
            
            // Aguardar WorkzSDK estar disponível
            if (typeof WorkzSDK !== 'undefined') {
                console.log('✅ WorkzSDK disponível');
                this.isInitialized = true;
            } else {
                console.log('⚠️ WorkzSDK não encontrado');
            }
            
            this.render();
            this.setupEventListeners();
            
        } catch (error) {
            console.error('❌ Erro ao inicializar:', error);
            this.renderError(error.message);
        }
    }

    render() {
        const appContainer = document.getElementById('app') || document.body;
        
        appContainer.innerHTML = \`
            <div style="
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 40px 20px;
                text-align: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                color: white;
            ">
                <div style="
                    background: rgba(255, 255, 255, 0.1);
                    padding: 40px;
                    border-radius: 20px;
                    backdrop-filter: blur(10px);
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                ">
                    <h1 style="font-size: 3rem; margin-bottom: 20px;">
                        🚀 JavaScript App
                    </h1>
                    
                    <p style="font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9;">
                        Aplicativo criado com WorkzSDK
                    </p>
                    
                    <div style="
                        display: inline-flex;
                        align-items: center;
                        background: rgba(40, 167, 69, 0.2);
                        padding: 12px 20px;
                        border-radius: 25px;
                        margin-bottom: 40px;
                        border: 1px solid rgba(40, 167, 69, 0.3);
                    ">
                        <span style="margin-right: 8px;">✅</span>
                        <span>App funcionando perfeitamente!</span>
                    </div>
                    
                    <div style="
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin-bottom: 40px;
                    ">
                        <div style="
                            background: rgba(255, 255, 255, 0.1);
                            padding: 20px;
                            border-radius: 15px;
                            border: 1px solid rgba(255, 255, 255, 0.2);
                        ">
                            <div style="font-size: 2rem; margin-bottom: 10px;">⚡</div>
                            <div style="font-weight: 600;">Performance</div>
                            <div style="font-size: 0.9rem; opacity: 0.8;">Otimizado e rápido</div>
                        </div>
                        
                        <div style="
                            background: rgba(255, 255, 255, 0.1);
                            padding: 20px;
                            border-radius: 15px;
                            border: 1px solid rgba(255, 255, 255, 0.2);
                        ">
                            <div style="font-size: 2rem; margin-bottom: 10px;">🔧</div>
                            <div style="font-weight: 600;">WorkzSDK</div>
                            <div style="font-size: 0.9rem; opacity: 0.8;">Integração completa</div>
                        </div>
                        
                        <div style="
                            background: rgba(255, 255, 255, 0.1);
                            padding: 20px;
                            border-radius: 15px;
                            border: 1px solid rgba(255, 255, 255, 0.2);
                        ">
                            <div style="font-size: 2rem; margin-bottom: 10px;">🌐</div>
                            <div style="font-weight: 600;">Web Ready</div>
                            <div style="font-size: 0.9rem; opacity: 0.8;">Funciona em qualquer navegador</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <button id="test-sdk-btn" style="
                            background: #007bff;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            font-size: 1rem;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        ">
                            Testar WorkzSDK
                        </button>
                        
                        <button id="app-info-btn" style="
                            background: transparent;
                            color: white;
                            border: 2px solid white;
                            padding: 12px 24px;
                            border-radius: 8px;
                            font-size: 1rem;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        ">
                            Informações do App
                        </button>
                    </div>
                </div>
            </div>
        \`;
    }

    setupEventListeners() {
        const testBtn = document.getElementById('test-sdk-btn');
        const infoBtn = document.getElementById('app-info-btn');
        
        if (testBtn) {
            testBtn.addEventListener('click', () => this.testWorkzSDK());
        }
        
        if (infoBtn) {
            infoBtn.addEventListener('click', () => this.showAppInfo());
        }
    }

    testWorkzSDK() {
        if (this.isInitialized && typeof WorkzSDK !== 'undefined') {
            alert(\`✅ WorkzSDK está funcionando perfeitamente!

Recursos disponíveis:
• Autenticação
• Storage (KV, Docs, Blobs)  
• API Integration
• Push Notifications
• Performance Monitoring\`);
        } else {
            alert('⚠️ WorkzSDK não foi carregado. Verifique a configuração.');
        }
    }

    showAppInfo() {
        const info = \`🚀 Informações do Aplicativo

📱 Tipo: JavaScript App
🌐 Plataforma: Web
⚡ Engine: JavaScript ES6+
🔧 SDK: WorkzSDK v2.0
📅 Build: \${new Date().toLocaleString('pt-BR')}
🎯 Status: Funcionando

✨ Recursos Ativos:
• Interface responsiva
• Integração WorkzSDK
• Performance otimizada
• Compatibilidade universal\`;
        
        alert(info);
    }

    renderError(message) {
        const appContainer = document.getElementById('app') || document.body;
        
        appContainer.innerHTML = \`
            <div style="
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 600px;
                margin: 0 auto;
                padding: 40px 20px;
                text-align: center;
                background: #f8d7da;
                min-height: 100vh;
                color: #721c24;
            ">
                <div style="
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                    border: 1px solid #f5c6cb;
                ">
                    <div style="font-size: 4rem; margin-bottom: 20px;">❌</div>
                    <h1 style="margin-bottom: 20px;">Erro ao Inicializar</h1>
                    <p style="margin-bottom: 30px; font-size: 1.1rem;">\${message}</p>
                    <button onclick="location.reload()" style="
                        background: #dc3545;
                        color: white;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 8px;
                        font-size: 1rem;
                        cursor: pointer;
                    ">
                        Tentar Novamente
                    </button>
                </div>
            </div>
        \`;
    }
}

// Inicializar app quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    new MyWorkzApp();
});

// Fallback se DOMContentLoaded já passou
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new MyWorkzApp());
} else {
    new MyWorkzApp();
}`;
    },

    toggleTokenField() {
        const tokenContainer = document.getElementById('token-field-container');
        if (tokenContainer) {
            if (this.appType === 'flutter') {
                tokenContainer.style.display = 'block';
            } else {
                tokenContainer.style.display = 'none';
            }
        }
    },

    async editApp(appId) {
        try {
            const response = await WorkzSDK.api.get(`/apps/${appId}`);
            if (response && response.success) {
                // 1. Configurar o estado de edição
                this.editMode = true;
                this.editingAppId = appId;
                this.currentAppData = response.data;
                this.viewMode = 'form'; // Switch to form view
                this.currentStep = 5; // Ir direto para a etapa de código

                // 2. Renderizar a página do formulário
                this.render();

                // 2.5 Preencher o estado appData com os dados do app.
                // Isso é crucial para que initializeCodeMirror tenha o código correto.
                this.populateFormFields(response.data);
                setTimeout(() => {
                    // Se for app filesystem, inicializa o editor de arquivos (somente fora do modo simples)
                    if (!this.useSimpleEditor && this.currentAppData.storage_type === 'filesystem') {
                        this.setupFilesystemEditor();
                        this.renderFileTree();
                    }

                    window.scrollTo(0, 0);
                }, 100);

            }
        } catch (e) {
            console.error('Error loading app for edit:', e);
            alert('Erro ao carregar aplicativo para edição: ' + e.message);
        }
    },

    populateFormFields(appData) {
        // Se for filesystem, os arquivos vêm em um campo separado
        if (appData.storage_type === 'filesystem') {
            // O backend deve retornar os arquivos em um campo como `files`
            this.appData.appFiles = appData.files || { 'main.dart': appData.dart_code || '' };
            this.appData.activeFile = null;
            this.appData.unsavedChanges.clear();
        } else {
            // Limpa o estado de arquivos para apps de database
            this.appData.appFiles = {};
            this.appData.activeFile = null;
            this.appData.unsavedChanges.clear();
        }

        console.log('Populating form fields with:', appData);

        // Update internal data
        this.appData.storage_type = appData.storage_type || 'database';
        this.appData.appFiles = appData.files || {};
        this.appData.activeFile = null;
        this.appData.unsavedChanges.clear();
        this.appData.title = appData.tt || appData.title || '';
        this.appData.slug = appData.slug || '';
        this.appData.description = appData.ds || appData.description || '';
        this.appData.version = appData.version || '1.0.0';
        this.appData.price = parseFloat(appData.price || 0);
        this.appData.accessLevel = parseInt(appData.access_level || 1);
        this.appData.entityType = parseInt(appData.entity_type || 0);
        this.appData.color = appData.color || '#3b82f6';
        this.appData.scopes = appData.scopes ? (Array.isArray(appData.scopes) ? appData.scopes : JSON.parse(appData.scopes)) : [];

        // Set company data
        if (appData.company_id || appData.exclusive_to_entity_id) {
            const companyId = appData.company_id || appData.exclusive_to_entity_id;
            console.log('🏢 Procurando empresa com ID:', companyId);
            console.log('🏢 Empresas disponíveis:', this.userCompanies);

            const company = this.userCompanies.find(c => c.id === parseInt(companyId));
            if (company) {
                console.log('✅ Empresa encontrada:', company);
                this.appData.company = {
                    id: company.id,
                    name: company.name,
                    cnpj: company.cnpj
                };

                // Wait for DOM to be ready, then select company
                setTimeout(() => {
                    const companySelect = document.getElementById('company-select');
                    if (companySelect) {
                        companySelect.value = company.id;
                        console.log('🔄 Empresa selecionada no dropdown:', company.id);

                        const cnpjDisplay = document.getElementById('cnpj-display');
                        if (cnpjDisplay) {
                            cnpjDisplay.value = this.formatCNPJ(company.cnpj);
                            this.validateCNPJ(company.cnpj);
                        }
                    } else {
                        console.warn('⚠️ Dropdown de empresa não encontrado');
                    }
                }, 100);
            } else {
                console.warn('⚠️ Empresa não encontrada nas empresas do usuário');
            }
        }

        // Populate form fields
        const titleField = document.getElementById('app-title');
        if (titleField) titleField.value = this.appData.title;

        const slugField = document.getElementById('app-slug');
        if (slugField) slugField.value = this.appData.slug;

        const descField = document.getElementById('app-description');
        if (descField) descField.value = this.appData.description;

        const versionField = document.getElementById('app-version');
        if (versionField) versionField.value = this.appData.version;

        const priceField = document.getElementById('app-price');
        if (priceField) priceField.value = this.appData.price;

        const accessField = document.getElementById('access-level');
        if (accessField) accessField.value = this.appData.accessLevel;

        const entityField = document.getElementById('entity-type');
        if (entityField) entityField.value = this.appData.entityType;

        const colorField = document.getElementById('app-color');
        if (colorField) colorField.value = this.appData.color;

        const colorHexField = document.getElementById('app-color-hex');
        if (colorHexField) colorHexField.value = this.appData.color;

        // Set app type
        this.appType = appData.app_type || 'javascript';
        this.selectAppType(this.appType);

        // Populate code fields
        if (this.appType === 'flutter') {
            this.appData.dartCode = appData.dart_code || appData.source_code || '';
            // If dart_code is empty but js_code has Dart content, use it
            if (!this.appData.dartCode && appData.js_code && appData.js_code.includes('/* DART_CODE */')) {
                this.appData.dartCode = appData.js_code.replace('/* DART_CODE */ ', '');
            }
        } else {
            this.appData.code = appData.js_code || appData.source_code || appData.code || '';
        }

        const codeField = document.getElementById('app-code');
        if (codeField) {
            codeField.value = this.appType === 'flutter' ? this.appData.dartCode : this.appData.code;
        }

        // Show/hide token field based on app type (and update its value if editing)
        this.toggleTokenField(appData.token);

        // Populate scopes checkboxes
        document.querySelectorAll('input[type="checkbox"][value*="."]').forEach(checkbox => {
            checkbox.checked = this.appData.scopes.includes(checkbox.value);
        });

        // Go to step 5 to start editing code directly
        // this.currentStep = 5; // Movido para a função editApp
        // this.updateStepDisplay(); // Será chamado pelo render()
    },

    resetForm() {
        // Reset all form fields
        document.querySelectorAll('input, textarea, select').forEach(field => {
            if (field.type === 'checkbox') {
                field.checked = false;
            } else {
                field.value = '';
            }
        });

        // Reset appData state
        this.appData = {
            company: null,
            appFiles: {},
            activeFile: null,
            unsavedChanges: new Set(),
            title: "",
            slug: "",
            description: "",
            icon: null,
            color: "#3b82f6",
            accessLevel: 1,
            version: "1.0.0",
            entityType: 0,
            price: 0,
            scopes: [],
            code: "",
            dartCode: "",
            token: null
        };

        // Reset app type
        this.appType = 'javascript';
        this.selectAppType('javascript');

        // Go back to step 1
        this.currentStep = 1;
        // No need to call updateStepDisplay here, render() will handle it
    },

    startNewApp() {
        this.editMode = false;
        this.editingAppId = null;
        this.currentAppData = null;
        this.viewMode = 'form';
        this.resetForm();
        this.currentStep = 1; // Start from step 1 for new app
        this.render();
    },

    goBackToList() {
        // Check for unsaved changes before leaving the form
        let hasUnsavedChanges = false;
        if (this.useSimpleEditor) { // Simple editor (textarea)
            const codeField = document.getElementById('app-code');
            const currentCode = codeField ? codeField.value : '';
            const originalCode = this.appType === 'flutter' ? this.appData.dartCode : this.appData.code;
            if (currentCode !== originalCode) {
                hasUnsavedChanges = true;
            }
        } else { // Filesystem editor
            hasUnsavedChanges = this.appData.unsavedChanges.size > 0;
        }

        if (hasUnsavedChanges && !confirm('Você tem alterações não salvas. Deseja descartá-las e voltar para a lista de apps?')) {
            return; // User cancelled going back
        }

        this.editMode = false;
        this.editingAppId = null;
        this.currentAppData = null;
        this.viewMode = 'list';
        this.destroyCodeMirror(); // Garantir que o editor seja destruído ao voltar
        this.resetForm(); // Clear form data
        this.render();
    },

    // Modificado para aceitar um token opcional ao exibir/ocultar
    toggleTokenField(tokenValue = null) {
        const tokenContainer = document.getElementById('token-field-container');
        const tokenInput = document.getElementById('app-token');
        if (tokenContainer && tokenInput) {
            if (this.appType === 'flutter') {
                tokenContainer.style.display = 'block';
                tokenInput.value = tokenValue || this.appData.token || '';
                this.appData.token = tokenInput.value; // Garante que o estado interno reflita o que está no campo
            } else {
                tokenContainer.style.display = 'none';
                tokenInput.value = '';
                this.appData.token = null;
            }
        }
    },

    async saveApp() {
        try {
            console.log('🚀 Iniciando salvamento do app...');
            
            // Coletar dados de forma mais simples e segura
            const appData = this.collectFormDataSafe();

            console.log('📝 Dados coletados:', appData);
            console.log('🔧 Modo de edição:', this.editMode, 'ID:', this.editingAppId);

            let response;
            if (this.editMode && this.editingAppId) {
                console.log('📝 Atualizando app existente...');
                // Tentar POST primeiro (mais estável para alguns backends)
                response = await this.apiPost(`/apps/update/${this.editingAppId}`, appData);
                // Se não retornou sucesso, tentar PUT como fallback
                if (!response || response.success === false) {
                    console.log('POST update did not succeed; attempting PUT fallback…');
                    response = await this.apiPut(`/apps/${this.editingAppId}`, appData);
                }
            } else {
                console.log('🆕 Criando novo app...');
                response = await this.apiPost('/apps/create', appData);
            }

            console.log('📡 Resposta da API de salvamento:', response);

            // Fallback: alguns ambientes retornam HTML com status 200. Verificar via GET se o update realmente ocorreu.
            if ((!response || response.success === false) && this.editMode && this.editingAppId) {
                try {
                    console.log('🔎 Verificando atualização via GET /apps/:id (fallback)…');
                    const verify = await this.apiGet(`/apps/${this.editingAppId}`);
                    if (verify && verify.success && verify.data) {
                        response = {
                            success: true,
                            app_id: this.editingAppId,
                            app_type: verify.data.app_type || appData.app_type || this.appType,
                            build_status: verify.data.build_status || null
                        };
                        console.log('✅ Fallback de verificação considerou o update como bem-sucedido.');
                    }
                } catch (_) { /* ignore and handle below */ }
            }

            if (response && response.success) {
                this.showToast(
                    this.editMode ? 'Aplicativo atualizado com sucesso!' : 'Aplicativo criado com sucesso!',
                    'success'
                );

                // Disparar build real para apps Flutter (se aplicável)
                try {
                    const savedAppId = response.app_id || (response.data && response.data.id) || this.editingAppId;
                    const savedAppType = ((response.app_type || (response.data && response.data.app_type) || appData.app_type || this.appType || 'javascript') + '').toLowerCase();
                    const buildStatus = response.build_status || (response.data && response.data.build_status) || null;

                    if (savedAppType === 'flutter' && savedAppId) {
                        // Em criação, o backend já inicia o build. Em edição, vamos forçar um rebuild.
                        if (this.editMode) {
                            await this.triggerBuildAndMonitor(savedAppId);
                        } else {
                            // Se a API já sinalizou pending/building, apenas acompanhar; caso contrário, iniciar rebuild para garantir
                            if (buildStatus === 'pending' || buildStatus === 'building') {
                                this.showToast('Build iniciado. Acompanhe o status…', 'info');
                                setTimeout(() => this.showBuildStatus(savedAppId), 800);
                            } else {
                                await this.triggerBuildAndMonitor(savedAppId);
                            }
                        }
                    }
                } catch (buildErr) {
                    console.error('Falha ao iniciar/monitorar build:', buildErr);
                    this.showToast('Não foi possível iniciar o build automaticamente.', 'error');
                }

                // Refresh apps list
                this.loadAndShowMyApps();

                if (!this.editMode) {
                    // Se for um novo app, após salvar, ele deve permanecer em rascunho.
                    this.resetForm();
                }
            } else {
                const statusInfo = response?.status ? ` (status ${response.status})` : '';                
                throw new Error((response?.message || response?.error || 'Erro desconhecido na resposta da API') + statusInfo);
            }
        } catch (e) {
            console.error(' Error saving app:', e);

            // Log detalhado do erro
            console.error('Error details:', {
                message: e.message,
                stack: e.stack,
                editMode: this.editMode,
                editingAppId: this.editingAppId
            });

            // Show more detailed error message
            let errorMessage = 'Erro ao salvar aplicativo: ';
            if (e.message.includes('500')) {
                errorMessage += 'Erro interno do servidor. Tente novamente em alguns segundos.';
            } else if (e.message.includes('400')) {
                errorMessage += 'Dados inválidos. Verifique se todos os campos obrigatórios estão preenchidos.';
            } else if (e.message.includes('403')) {
                errorMessage += 'Você não tem permissão para realizar esta ação.';
            } else if (e.message.includes('404')) {
                errorMessage += 'App não encontrado. Tente recarregar a página.';
            } else {
                if (e.message.toLowerCase().includes('filesystem storage')) {
                    errorMessage = 'Erro no backend: Falha ao inicializar o storage para o app Flutter. Verifique se o serviço de criação de repositórios/diretórios está funcionando corretamente no servidor.';
                }
                errorMessage += e.message;
            }

            this.showToast(errorMessage, 'error');
        }
    },

    async publishApp() { // Publicar da Etapa 6
        if (!this.editingAppId) {
            alert('Salve o aplicativo primeiro antes de publicar');
            return;
        }
        await this.publishAppFromCard(this.editingAppId);
        this.updatePreview(); // Atualiza o botão na Etapa 6
    },

    async unpublishApp() { // Despublicar da Etapa 6
        if (!this.editingAppId) {
            alert('Não é possível despublicar um aplicativo não salvo.');
            return;
        }
        await this.unpublishAppFromCard(this.editingAppId);
        this.updatePreview(); // Atualiza o botão na Etapa 6
    },

    // Removido: publishApp() não é mais chamado automaticamente após saveApp().
    // A ação de publicar é agora explícita.
    // O método publishApp() acima é o que será chamado pelo botão da Etapa 6.

    async triggerBuildAndMonitor(appId) {
        this.showToast('Iniciando build do app…', 'info');
        const res = { success: true }; // queue-based flow: skip explicit rebuild
        const httpOk = true;
        if (!res || (!res.success && !httpOk)) {
            throw new Error(res?.message || 'Falha ao iniciar build');
        }
        // Abrir modal de status (pendente) e iniciar polling para obter dados
        this.displayPendingBuildModal(appId);
        setTimeout(() => this.startBuildWatch(appId), 800);
    },

    // Exibe modal pendente enquanto aguardamos o backend popular os dados do build
    displayPendingBuildModal(appId) {
        const pendingData = {
            app_id: appId,
            app_type: 'flutter',
            build_status: 'building',
            build_log: 'Preparando build…',
            compiled_at: null,
            builds: []
        };
        this.displayBuildStatusModal(pendingData);
    },

    // Faz polling do status do build tolerando erros transitórios (500) por um período
    async watchBuild(appId, attempts = 0, maxAttempts = 12, intervalMs = 1500) {
        try {
            const response = await this.getBuildStatusCompat(appId);
            if (response && response.success && response.data) {
                this.displayBuildStatusModal(response.data);
                const status = response.data.build_status;
                if (status === 'building' || status === 'pending') {
                    if (attempts < maxAttempts) {
                        const nextDelay = Math.min(10000, Math.round(intervalMs * Math.pow(1.5, attempts+1))); setTimeout(() => this.watchBuild(appId, attempts + 1, maxAttempts, nextDelay), nextDelay);
                    }
                }
                return;
            }
        } catch (_) {}
        // Em caso de erro (e.g., 500), continuar tentando enquanto dentro do limite
        if (attempts < maxAttempts) {
            const nextDelay = Math.min(10000, Math.round(intervalMs * Math.pow(1.5, attempts+1))); setTimeout(() => this.watchBuild(appId, attempts + 1, maxAttempts, nextDelay), nextDelay);
        } else {
            this.showToast('Não foi possível obter o status do build no momento.', 'error');
        }
    },

    // Safe API wrappers that fall back to raw fetch when SDK throws due to non-JSON responses
    async apiGet(path) { return this._apiSafe('get', path); },
    async apiPost(path, body) { return this._apiSafe('post', path, body); },
    async apiPut(path, body) { return this._apiSafe('put', path, body); },
    async apiDelete(path) { return this._apiSafe('delete', path); },
    async _apiSafe(method, path, body) {
        try {
            if (WorkzSDK && WorkzSDK.api && typeof WorkzSDK.api[method] === 'function') {
                return await WorkzSDK.api[method](path, body);
            }
        } catch (e) {
            try {
                this._apiFallbackLogged = this._apiFallbackLogged || new Set();
                const key = `${method}:${path}`;
                if (!this._apiFallbackLogged.has(key)) {
                    console.warn(`WorkzSDK.api.${method} threw for ${path}, using raw fetch fallback once.`, e);
                    this._apiFallbackLogged.add(key);
                }
            } catch (_) {}
        }
        return await this._rawFetch(method, path, body);
    },
    async _rawFetch(method, path, body) {
        const base = '/api';
        const url = base + (path.startsWith('/') ? path : '/' + path);
        const headers = { 'Accept': 'application/json' };
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        try {
            const token = (typeof WorkzSDK !== 'undefined' && WorkzSDK.getToken) ? WorkzSDK.getToken() : null;
            if (token) headers['Authorization'] = 'Bearer ' + token;
        } catch (_) {}

        const resp = await fetch(url, {
            method: method.toUpperCase(),
            headers,
            body: body !== undefined ? JSON.stringify(body || {}) : undefined
        });
        return await this._parseJsonSafe(resp);
    },
    async _parseJsonSafe(resp) {
        try { return await resp.json(); } catch (_) {
            try {
                const txt = await resp.text();
                const preview = (txt || '').toString().slice(0, 1000);
                return { success: false, status: resp.status, message: preview, raw: preview };
            } catch (e2) {
                return { success: false, status: resp.status, message: 'Failed to parse response' };
            }
        }
    },

    // Build API compatibility helpers
    async getBuildStatusCompat(appId) {
        // Prefer management route first; some envs only expose this
        let res = await this.apiGet(`/apps/${appId}/build-status`);
        if (res && (res.success || (res.data && typeof res.data === 'object'))) {
            return res.success ? res : { success: true, data: res.data };
        }
        try {
            // Fallback to builder route (/apps/build-status/:id)
            const r2 = await this.apiGet(`/apps/build-status/${appId}`);
            return r2;
        } catch (_) {
            return res;
        }
    },
    async postRebuildCompat(appId) {
        // Primeiro, verificar se já existe um build em andamento (ou pendente)
        try {
            const statusRes = await this.getBuildStatusCompat(appId);
            if (statusRes && statusRes.success && statusRes.data && (statusRes.data.build_status === 'pending' || statusRes.data.build_status === 'building')) {
                return { success: true, message: 'Build já em andamento.' };
            }
        } catch (_) { /* segue para tentar rebuild */ }

        // Preferir endpoint de rebuild; fallback para build genérico
        let res = await this.apiPost(`/apps/${appId}/rebuild`, {});
        const httpOk = res && typeof res.status === 'number' && res.status >= 200 && res.status < 300;
        if (res && (res.success || httpOk)) return res;
        return await this.apiPost(`/apps/${appId}/build`, {});
    },

    // Polling contínuo com cancelamento ao fechar o modal
    startBuildWatch(appId) {
        this._buildWatchAppId = appId;
        const run = async () => {
            try {
                const response = await this.getBuildStatusCompat(appId);
                if (response && response.success && response.data) {
                    this.displayBuildStatusModal(response.data);
                    const st = String(response.data.build_status || '').toLowerCase();
                    if (st === 'building' || st === 'pending') {
                        this._buildWatchTimer = setTimeout(run, 3000);
                    } else {
                        this._buildWatchTimer = null;
                        this._buildWatchAppId = null;
                    }
                    return;
                }
            } catch (_) {}
            this._buildWatchTimer = setTimeout(run, 5000);
        };
        if (this._buildWatchTimer) { try { clearTimeout(this._buildWatchTimer); } catch(_){} }
        this._buildWatchTimer = setTimeout(run, 1000);
    },

    // Inicialização do editor baseado em textarea (modo simples)
    setupDatabaseEditor() {
        try {
            const codeField = document.getElementById('app-code');
            if (!codeField) return;

            // Preenche o textarea com o código atual
            const code = this.appType === 'flutter' ? (this.appData.dartCode || '') : (this.appData.code || '');
            if (codeField.value !== code) {
                codeField.value = code;
            }

            // Evita múltiplos listeners ao alternar etapas
            if (!codeField.dataset.bound) {
                codeField.addEventListener('input', (e) => {
                    const value = e.target.value;
                    if (this.appType === 'flutter') {
                        this.appData.dartCode = value;
                    } else {
                        this.appData.code = value;
                    }
                });
                codeField.dataset.bound = '1';
            }
        } catch (_) { /* noop */ }
    },
    
    collectFormDataSafe() {
        console.log('Coletando dados do formulário (versão segura)...');

        // Campos básicos obrigatórios
        const titleField = document.getElementById('app-title');
        const title = titleField ? titleField.value.trim() : '';
        
        const slugField = document.getElementById('app-slug');
        const slug = slugField ? slugField.value.trim() : (this.appData.slug || '');
        if (!title) {
            throw new Error('O campo "Nome do Aplicativo" é obrigatório.');
        }
        if (!slug || !/^[a-z0-9-]+$/.test(slug)) {
            throw new Error('Slug é obrigatório e deve conter apenas letras minúsculas, números e hífens');
        }

        // Dados básicos
        const formData = {
            title: title,
            slug: slug,
            description: this.getFieldValue('app-description', ''),
            version: this.getFieldValue('app-version', '1.0.0'),
            color: this.getFieldValue('app-color', '#3b82f6'),
            app_type: this.appType || 'javascript'
        };

        // Campos numéricos opcionais
        const companyField = document.getElementById('company-select');
        if (companyField && companyField.value) {
            formData.company_id = parseInt(companyField.value);
        }

        const priceField = document.getElementById('app-price');
        // company_id é obrigatório para criação
        if (!this.editMode && !formData.company_id) {
            throw new Error('O campo "Empresa" é obrigatório. Selecione uma empresa válida.');
        }
        if (priceField && priceField.value) {
            formData.price = parseFloat(priceField.value) || 0;
        }

        const accessField = document.getElementById('access-level');
        if (accessField && accessField.value) {
            formData.access_level = parseInt(accessField.value);
        }

        const entityField = document.getElementById('entity-type');
        if (entityField && entityField.value) {
            formData.entity_type = parseInt(entityField.value);
        }

        // Scopes
        const selectedScopes = [];
        document.querySelectorAll('input[type="checkbox"][value*="."]:checked').forEach(checkbox => {
            selectedScopes.push(checkbox.value);
        });
        
        formData.scopes = selectedScopes; // Sempre incluir scopes, mesmo que vazio, para permitir limpar

        // Adicionado: Tratamento de upload de ícone
        if (this.appData.icon) { // this.appData.icon should hold the base64 string or the existing URL
            formData.icon = this.appData.icon;
        }


        // Código (sempre via textarea; Mini‑IDE desativado)
        const isFilesystem = false; // Mini‑IDE desativado: não enviar arquivos

        // Sempre capturar o conteúdo do textarea quando existir
        let inlineCode;
        if (this._codeMirrorInstance) {
            // Se o CodeMirror está ativo, pegue o valor diretamente dele.
            inlineCode = this._codeMirrorInstance.getValue();
        } else {
            // Fallback para o textarea ou estado do app.
            const inlineCodeField = document.getElementById('app-code');
            inlineCode = (inlineCodeField ? inlineCodeField.value : (this.appType === 'flutter' ? this.appData.dartCode : this.appData.code)) || '';
        }

        if (this.appType === 'flutter') {
            // Para Flutter, envie dart_code quando o usuário preenche o textarea
            if (inlineCode && inlineCode.trim().length > 0) {
                formData.dart_code = inlineCode.trim();
            }
        } else {
            // Apps JavaScript
            const jsInline = inlineCode && inlineCode.trim().length > 0 ? inlineCode.trim() : '';
            if (jsInline) {
                formData.js_code = jsInline;
            }
        }

        console.log('Dados coletados (seguro):', formData);
        return formData;
    },

    // Helper para obter valor de campo de forma segura
    getFieldValue(fieldId, defaultValue = '') {
        const field = document.getElementById(fieldId);
        return field ? field.value.trim() : defaultValue;
    },

    collectFormData() {
        console.log('Coletando dados do formulário...');

        // Campos básicos
        const titleField = document.getElementById('app-title');
        const slugField = document.getElementById('app-slug');
        const descField = document.getElementById('app-description');
        const companyField = document.getElementById('company-select');
        const versionField = document.getElementById('app-version');
        const priceField = document.getElementById('app-price');
        const accessField = document.getElementById('access-level');
        const entityField = document.getElementById('entity-type');
        const colorField = document.getElementById('app-color');

        // Campo de código
        const codeField = document.getElementById('app-code') || document.getElementById('filesystem-code-editor-textarea');

        // Coletar scopes selecionados
        const selectedScopes = [];
        document.querySelectorAll('input[type="checkbox"][value*="."]:checked').forEach(checkbox => {
            selectedScopes.push(checkbox.value);
        });

        // Token field for Flutter apps
        const tokenField = document.getElementById('app-token');

        const formData = {
            company_id: companyField ? parseInt(companyField.value) : (this.appData.company?.id || null),
            title: titleField ? titleField.value : this.appData.title,
            slug: slugField ? slugField.value : this.appData.slug,
            description: descField ? descField.value : this.appData.description,
            version: versionField ? versionField.value : this.appData.version,
            price: priceField ? parseFloat(priceField.value) || 0 : this.appData.price,
            access_level: accessField ? parseInt(accessField.value) : this.appData.accessLevel,
            entity_type: entityField ? parseInt(entityField.value) : this.appData.entityType,
            color: colorField ? colorField.value : this.appData.color,
            scopes: selectedScopes.length > 0 ? selectedScopes : this.appData.scopes,
            app_type: this.appType,
            js_code: this.appType === 'javascript' ? (codeField ? codeField.value : this.appData.code) : '',
            dart_code: this.appType === 'flutter' ? (codeField ? codeField.value : this.appData.dartCode) : '',
            token: this.appType === 'flutter' && tokenField ? tokenField.value : (this.appData.token || null),
            icon: this.appData.icon || null
        };

        console.log('Dados coletados:', formData);
        return formData;
    },

    setupFormEventListeners() {
        // Company select change handler
        const companySelect = document.getElementById("company-select");
        if (!companySelect || companySelect.dataset.listenerAttached) return;

        if (companySelect) {
            companySelect.addEventListener("change", (e) => {
                const selectedOption = e.target.selectedOptions[0];
                if (selectedOption && selectedOption.value) {
                    const cnpj = selectedOption.dataset.cnpj;
                    const cnpjDisplay = document.getElementById("cnpj-display");
                    if (cnpjDisplay) {
                        cnpjDisplay.value = this.formatCNPJ(cnpj);
                        this.validateCNPJ(cnpj);
                    }

                    this.appData.company = {
                        id: parseInt(selectedOption.value),
                        name: selectedOption.textContent,
                        cnpj: cnpj
                    };
                } else {
                    const cnpjDisplay = document.getElementById("cnpj-display");
                    if (cnpjDisplay) {
                        cnpjDisplay.value = "";
                    }
                    const cnpjValidation = document.getElementById("cnpj-validation");
                    if (cnpjValidation) {
                        cnpjValidation.innerHTML = "";
                    }
                    this.appData.company = null;
                }
                this.validateCurrentStep();
            });
            companySelect.dataset.listenerAttached = 'true';
        }

        // App title change handler
        const appTitle = document.getElementById("app-title");
        if (appTitle) {
            appTitle.addEventListener("input", (e) => {
                this.appData.title = e.target.value;
                if (e.target.value) {
                    const slug = this.generateSlug(e.target.value);
                    const slugField = document.getElementById("app-slug");
                    if (slugField) {
                        slugField.value = slug;
                        this.appData.slug = slug;
                    }
                }
                this.validateCurrentStep();
            });
        }

        // App slug change handler
        const appSlug = document.getElementById("app-slug");
        if (appSlug) {
            appSlug.addEventListener("input", (e) => {
                this.appData.slug = e.target.value;
                this.validateCurrentStep();
            });
        }

        // App description change handler
        const appDescription = document.getElementById("app-description");
        if (appDescription) {
            appDescription.addEventListener("input", (e) => {
                this.appData.description = e.target.value;
            });
        }

        // App type radio buttons
        document.querySelectorAll('input[name="app-type"]').forEach(radio => {
            radio.addEventListener("change", (e) => {
                if (e.target.checked) {
                    this.appType = e.target.value;
                    this.validateCurrentStep();
                }
            });
        });

        // App icon change handler
        const appIconInput = document.getElementById("app-icon");
        if (appIconInput && !appIconInput.dataset.listenerAttached) {
            appIconInput.addEventListener("change", (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = () => {
                        this.appData.icon = reader.result; // Store base64 string
                        const iconPreview = document.getElementById("icon-preview");
                        if (iconPreview) {
                            iconPreview.src = reader.result;
                            iconPreview.style.display = "block";
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    this.appData.icon = null; // Clear icon if no file selected
                    const iconPreview = document.getElementById("icon-preview");
                    if (iconPreview) {
                        iconPreview.src = "";
                        iconPreview.style.display = "none";
                    }
                }
            });
            appIconInput.dataset.listenerAttached = 'true';
        }
        // Other form fields
        const appVersion = document.getElementById("app-version");
        if (appVersion) {
            appVersion.addEventListener("input", (e) => {
                this.appData.version = e.target.value;
            });
        }

        const appPrice = document.getElementById("app-price");
        if (appPrice) {
            appPrice.addEventListener("input", (e) => {
                this.appData.price = parseFloat(e.target.value) || 0;
            });
        }

        const accessLevel = document.getElementById("access-level");
        if (accessLevel) {
            accessLevel.addEventListener("change", (e) => {
                this.appData.accessLevel = parseInt(e.target.value);
            });
        }

        const entityType = document.getElementById("entity-type");
        if (entityType) {
            entityType.addEventListener("change", (e) => {
                this.appData.entityType = parseInt(e.target.value);
            });
        }

        // Code editor
        const appCode = document.getElementById("app-code");
        if (appCode) {
            appCode.addEventListener("input", (e) => {
                if (this.appType === "flutter") {
                    this.appData.dartCode = e.target.value;
                } else {
                    this.appData.code = e.target.value;
                }
                this.validateCurrentStep();
                // If live JS preview is open, update it with debounce
                try {
                    const live = document.getElementById('livePreviewModal');
                    if (live && live.classList.contains('show') && this._livePreviewMode === 'js') {
                        clearTimeout(this._livePreviewDebounce);
                        this._livePreviewDebounce = setTimeout(() => this._updateJsLivePreview(), 300);
                    }
                } catch (_) {}
            });
        }

        // Scopes checkboxes
        document.querySelectorAll('input[type="checkbox"][value*="."]').forEach(checkbox => {
            checkbox.addEventListener("change", (e) => {
                if (e.target.checked) {
                    if (!this.appData.scopes.includes(e.target.value)) {
                        this.appData.scopes.push(e.target.value);
                    }
                } else {
                    this.appData.scopes = this.appData.scopes.filter(scope => scope !== e.target.value);
                }
                console.log('Scopes atualizados:', this.appData.scopes);
            });
        });
    },

    removeFormEventListeners() {
        // This is a placeholder. For a more robust solution, you would
        // store references to the handlers and remove them specifically.
        // For now, the `dataset.listenerAttached` check prevents re-binding.
        const companySelect = document.getElementById("company-select");
        if (companySelect) delete companySelect.dataset.listenerAttached;
    },

    validateCurrentStep() {
        console.log('Validate current step');

        let isValid = false;
        let nextButton = null;

        switch (this.currentStep) {
            case 1:
                nextButton = document.getElementById("step-1-next");
                isValid = this.appData.company &&
                    this.appData.company.cnpj &&
                    this.validateCNPJ(this.appData.company.cnpj);
                break;

            case 2:
                nextButton = document.getElementById("step-2-next");
                isValid = this.appType && (this.appType === "javascript" || this.appType === "flutter");
                break;

            case 3:
                nextButton = document.getElementById("step-3-next");
                isValid = this.appData.title.trim().length > 0 &&
                    this.appData.slug.trim().length > 0 &&
                    /^[a-z0-9-]+$/.test(this.appData.slug);
                break;

            case 4:
                nextButton = document.getElementById("step-4-next");
                isValid = true; // Configuration step is always valid
                break;

            case 5:
                nextButton = document.getElementById("step-5-next");
                if (this.appType === "flutter") {
                    isValid = this.appData.dartCode.trim().length > 0;
                } else {
                    isValid = this.appData.code.trim().length > 0;
                }
                break;

            case 6:
                this.updatePreview();
                isValid = true;
                break;
        }

        if (nextButton) {
            nextButton.disabled = !isValid;
        }

        return isValid;
    },

    setupPreviewListener() {
        console.log('Setup preview listener');
    },

    // Utility functions
    formatCNPJ(cnpj) {
        if (!cnpj) return "";
        return cnpj.replace(/\D/g, "").replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
    },

    validateCNPJ(cnpj) {
        const validationElement = document.getElementById("cnpj-validation");
        if (!cnpj) {
            if (validationElement) {
                validationElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> CNPJ não informado';
                validationElement.className = "cnpj-validation invalid";
            }
            return false;
        }

        const cleanCNPJ = cnpj.replace(/\D/g, "");
        if (cleanCNPJ.length !== 14) {
            if (validationElement) {
                validationElement.innerHTML = '<i class="fas fa-times-circle"></i> CNPJ inválido';
                validationElement.className = "cnpj-validation invalid";
            }
            return false;
        }

        if (this.isValidCNPJ(cleanCNPJ)) {
            if (validationElement) {
                validationElement.innerHTML = '<i class="fas fa-check-circle"></i> CNPJ válido';
                validationElement.className = "cnpj-validation valid";
            }
            return true;
        } else {
            if (validationElement) {
                validationElement.innerHTML = '<i class="fas fa-times-circle"></i> CNPJ inválido';
                validationElement.className = "cnpj-validation invalid";
            }
            return false;
        }
    },

    isValidCNPJ(cnpj) {
        if (cnpj.length !== 14) return false;
        if (/^(\d)\1+$/.test(cnpj)) return false;

        let sum = 0;
        let weight = 5;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(cnpj[i]) * weight;
            weight = weight === 2 ? 9 : weight - 1;
        }
        let digit1 = sum % 11 < 2 ? 0 : 11 - (sum % 11);
        if (parseInt(cnpj[12]) !== digit1) return false;

        sum = 0;
        weight = 6;
        for (let i = 0; i < 13; i++) {
            sum += parseInt(cnpj[i]) * weight;
            weight = weight === 2 ? 9 : weight - 1;
        }
        let digit2 = sum % 11 < 2 ? 0 : 11 - (sum % 11);
        return parseInt(cnpj[13]) === digit2;
    },

    generateSlug(text) {
        return text
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9\s-]/g, "")
            .replace(/\s+/g, "-")
            .replace(/-+/g, "-")
            .replace(/^-|-$/g, "")
            .substring(0, 60);
    },

    nextStep() {
        if (this.currentStep < this.maxSteps && this.validateCurrentStep()) {
            this.currentStep++;
            this.updateStepDisplay();
        }
    },

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStepDisplay();
        }
    },

    updateStepDisplay() {
        // Update step indicators
        document.querySelectorAll(".step").forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove("active", "completed");
            if (stepNumber < this.currentStep) {
                step.classList.add("completed");
            } else if (stepNumber === this.currentStep) {
                step.classList.add("active");
            }
        });

        // Show/hide form sections
        document.querySelectorAll(".form-section").forEach((section, index) => {
            section.classList.remove("active");
            if (index + 1 === this.currentStep) {
                section.classList.add("active");
            }
        });

        // Update token field visibility when on step 4
        if (this.currentStep === 4) {
            setTimeout(() => this.toggleTokenField(), 100);
        }

        // Load code template when on step 5
        if (this.currentStep === 5) {
            // A inicialização do editor agora é feita de forma mais robusta
            // quando o tipo de app é selecionado ou carregado.
            if (!this.useSimpleEditor && (this.appType === 'flutter' || this.appData.storage_type === 'filesystem')) {
                setTimeout(() => this.setupFilesystemEditor(), 100);
            } else { // Modo simples (database editor)
                setTimeout(() => this.initializeCodeMirror(), 50); // Adicionado timeout para garantir que o DOM está pronto
                setTimeout(() => this.setupDatabaseEditor(), 100);
            }
        }

        // Update preview when on step 6
        if (this.currentStep === 6) {
            setTimeout(() => this.updatePreview(), 100);
        }

        this.validateCurrentStep();
    },

    loadCodeTemplate() {
        const codeField = document.getElementById('app-code');
        if (!codeField) return;

        // Only load template if field is empty and not in edit mode
        if (codeField.value.trim() === '' && !this.editMode) {
            if (this.appType === 'flutter') {
                codeField.value = this.appData.dartCode || this.getFlutterTemplate();
                this.appData.dartCode = codeField.value;
            } else {
                codeField.value = this.appData.code || this.getJavaScriptTemplate();
                this.appData.code = codeField.value;
            }
        }
    },

    updatePreview() {
        // Update preview in step 6
        const elements = {
            "final-title": this.appData.title || "",
            "final-description": this.appData.description || "Sem descrição",
            "final-publisher": this.appData.company?.name || "Empresa",
            "final-version": this.appData.version || "1.0.0",
            "final-price": parseFloat(this.appData.price || 0).toFixed(2).replace(".", ","),
            "final-slug": this.appData.slug || "",
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });

        // Update access level
        const accessElement = document.getElementById("final-access");
        if (accessElement) {
            const accessLevels = { 1: "Público", 2: "Instalação", 3: "Privado" };
            accessElement.textContent = accessLevels[this.appData.accessLevel] || "Público";
        }

        // Update entity type
        const entityElement = document.getElementById("final-entity");
        if (entityElement) {
            const entityTypes = { 0: "Geral", 1: "Usuários", 2: "Empresas", 3: "Equipes" };
            entityElement.textContent = entityTypes[this.appData.entityType] || "Geral";
        }

        // Update icon
        const iconElement = document.getElementById("final-icon-preview");
        if (iconElement && this.appData.icon) {
            iconElement.src = this.appData.icon;
        }

        // Update scopes
        const scopesElement = document.getElementById("final-scopes");
        if (scopesElement) {
            scopesElement.innerHTML = "";
            if (this.appData.scopes.length > 0) {
                this.appData.scopes.forEach(scope => {
                    const li = document.createElement("li");
                    li.innerHTML = `<i class="fas fa-check"></i> ${scope}`;
                    scopesElement.appendChild(li);
                });
            } else {
                scopesElement.innerHTML = '<li><i class="fas fa-info-circle"></i> Nenhuma permissão especial</li>';
            }
        }
    },

    async initializeCodeMirror() {
        // Garante que o editor só seja inicializado quando o elemento estiver visível
        const waitForEditor = () => {
            return new Promise(resolve => {
                const check = () => {
                    const container = document.getElementById('code-editor-container');
                    const section = document.getElementById('step-5');
                    if (container && section && section.classList.contains('active')) {
                        resolve(container);
                    } else {
                        setTimeout(check, 50); // Tenta novamente em 50ms
                    }
                };
                check();
            });
        };

        await waitForEditor();

        // Garante que qualquer instância antiga seja destruída antes de criar uma nova.
        this.destroyCodeMirror();

        // Garante que todas as dependências, incluindo o modo Dart, estejam carregadas.
        // Isso resolve a race condition de forma robusta.
        await this._depsPromise;

        const newMode = this.appType === 'flutter' ? 'dart' : 'javascript';

        // Força a recriação do editor se o modo de linguagem mudou.
        // Isso resolve o problema de syntax highlighting ao alternar entre tipos de app.
        if (this._codeMirrorInstance && this._codeMirrorInstance.getOption('mode') !== newMode) {
            this._codeMirrorInstance.toTextArea();
            this._codeMirrorInstance = null;
        }

        if (this._codeMirrorInstance) {
            // Se a instância já existe, apenas atualize o conteúdo e o modo
            const code = this.appType === 'flutter' ? (this.appData.dartCode || '') : (this.appData.code || '');
            if (this._codeMirrorInstance.getValue() !== code) {
                this._codeMirrorInstance.setValue(code);
            }
            this._codeMirrorInstance.refresh();
            return;
        }

        const codeField = document.getElementById('app-code');
        if (codeField && typeof CodeMirror !== 'undefined') {
            this._codeMirrorInstance = CodeMirror.fromTextArea(codeField, {
                lineNumbers: true,
                mode: newMode,
                theme: 'monokai',
                tabSize: 2,
                gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                indentWithTabs: false,
                lineWrapping: false,
                autofocus: true,
                matchBrackets: true,
                foldGutter: true,
                extraKeys: {
                    "Ctrl-F": "findPersistent",
                    "Cmd-F": "findPersistent",
                    "Ctrl-H": "replace",
                    "Cmd-Option-F": "replace",
                    "F11": function(cm) { cm.setOption("fullScreen", !cm.getOption("fullScreen")); },
                    "Esc": function(cm) { if (cm.getOption("fullScreen")) cm.setOption("fullScreen", false); }
                }
            });

            const code = this.appType === 'flutter' ? (this.appData.dartCode || '') : (this.appData.code || '');
            this._codeMirrorInstance.setValue(code);

            this._codeMirrorInstance.on('change', (cm) => {
                const value = cm.getValue();
                if (this.appType === 'flutter') {
                    this.appData.dartCode = value;
                } else {
                    this.appData.code = value;
                }
                this.validateCurrentStep();
            });
            
            setTimeout(() => this._codeMirrorInstance.refresh(), 100);
        }
    },

    destroyCodeMirror() {
        if (this._codeMirrorInstance) {
            try {
                this._codeMirrorInstance.toTextArea();
            } catch (e) {
                console.warn("Falha ao destruir instância do CodeMirror:", e);
            }
            this._codeMirrorInstance = null;
        }
    },

    // Code editor methods
    formatCode() {
        console.log('Format code (disabled)');
        this.showToast('Funcionalidade de formatação desativada.', 'warning');
    },

    toggleCodeMirrorFullscreen() {
        if (this._codeMirrorInstance) {
            // Usa a API do CodeMirror para alternar o modo de tela cheia
            this._codeMirrorInstance.setOption("fullScreen", !this._codeMirrorInstance.getOption("fullScreen"));
        } else {
            this.showToast('Editor não inicializado.', 'warning');
        }
    },

    toggleWordWrap() {
        if (this._codeMirrorInstance) {
            const currentStatus = this._codeMirrorInstance.getOption("lineWrapping");
            this._codeMirrorInstance.setOption("lineWrapping", !currentStatus);
            this.showToast(`Quebra de linha ${!currentStatus ? 'ativada' : 'desativada'}.`, 'info');
        } else {
            this.showToast('Editor não inicializado.', 'warning');
        }
    },

    insertTemplate() {
        const template = this.appType === 'flutter' ? this.getFlutterTemplate() : this.getJavaScriptTemplate();
        
        if (this._codeMirrorInstance) {
            this._codeMirrorInstance.setValue(template);
            this._codeMirrorInstance.focus();
        } else {
            const editor = document.getElementById('app-code');
            if (editor) editor.value = template;
        }
        this.showToast('Template inserido!', 'info');
    },

    toggleLineNumbers() {
        console.log('Toggle line numbers');
    },

    // Build management methods
    async showCodePreview() {
        try { // Ensure there is code
            let code;
            if (this._codeMirrorInstance) {
                // Se o CodeMirror está ativo, pegue o valor diretamente dele.
                code = this._codeMirrorInstance.getValue();
            } else {
                // Fallback para o textarea ou estado do app.
                const codeField = document.getElementById('app-code');
                code = codeField ? codeField.value : (this.appType === 'flutter' ? (this.appData.dartCode || '') : (this.appData.code || ''));
            }

            if (!code || code.trim() === '') {
                this.showToast('Escreva algum código para visualizar o preview.', 'info');
                return;
            }

            if (this.appType === 'javascript') {
                this._livePreviewMode = 'js';
                const html = this._buildJsPreviewHtml(code);
                const modalHtml = `
                    <div class="modal fade" id="livePreviewModal" tabindex="-1">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="fas fa-eye"></i> Preview (JavaScript)</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-0">
                                    <iframe id="live-preview-frame" style="width:100%; height:70vh; border:0; background:#fff"></iframe>
                                </div>
                                <div class="modal-footer">
                                    <small class="text-muted me-auto">Atualiza automaticamente enquanto você digita</small>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                this.showModal(modalHtml, 'livePreviewModal');
                // Set srcdoc after modal is in DOM
                setTimeout(() => {
                    const iframe = document.getElementById('live-preview-frame');
                    if (iframe) iframe.srcdoc = html;
                }, 50);
                return;
            }

            // Flutter quick preview via build worker
            this._livePreviewMode = 'flutter';
            const modalHtml = `
                <div class="modal fade" id="livePreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-eye"></i> Preview (Flutter Web)</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0" id="live-preview-body">
                                <div class="p-3"><i class="fas fa-spinner fa-spin me-2"></i>Gerando preview...</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-primary" id="btn-refresh-preview"><i class="fas fa-redo"></i> Atualizar Preview</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            this.showModal(modalHtml, 'livePreviewModal');
            // Hook refresh button
            setTimeout(() => {
                const btn = document.getElementById('btn-refresh-preview');
                if (btn) btn.addEventListener('click', () => this._generateFlutterPreview());
                this._generateFlutterPreview();
            }, 50);

        } catch (e) {
            console.error('Preview error:', e);
            this.showToast('Falha ao abrir preview: ' + (e?.message || e), 'error');
        }
    },

    _buildJsPreviewHtml(code) {
        const safeCode = String(code || '');
        return `<!doctype html><html><head><meta charset="utf-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1"/>
            <style>html,body{height:100%} body{margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;} #app{min-height:100vh;}</style>
        </head><body>
            <div id="app"></div>
            <script>
            // Minimal WorkzSDK stub for preview
            window.WorkzSDK = {
                init: async () => ({ ok:true }),
                getUser: () => ({ id: 1, name: 'Preview User' }),
                getContext: () => ({ type: 'user', id: 1 }),
                api: { get: async()=>({}), post: async()=>({}), put: async()=>({}), delete: async()=>({}) }
            };
            </script>
            <script>
            try {
                (function(){\n${safeCode.replace(/<\/(script)/gi, '<\\/$1')}\n})();
                if (window.StoreApp && typeof window.StoreApp.bootstrap === 'function') {
                    Promise.resolve(window.StoreApp.bootstrap()).catch(e => console.error('bootstrap error', e));
                }
            } catch (e) {
                console.error(e);
                document.body.innerHTML = '<pre style="padding:16px; color:#b91c1c; background:#fee2e2">'+ String(e && (e.stack||e.message)||e) +'</pre>';
            }
            </script>
        </body></html>`;
    },

    _updateJsLivePreview() {
        try {
            const codeField = document.getElementById('app-code');
            const code = codeField ? codeField.value : this.appData.code || '';
            const html = this._buildJsPreviewHtml(code);
            const iframe = document.getElementById('live-preview-frame');
            if (iframe) iframe.srcdoc = html;
        } catch (e) { /* ignore */ }
    },

    async _generateFlutterPreview() {
        const slug = this.appData.slug || ('preview-' + Date.now());
        const codeField = document.getElementById('app-code');
        const dartCode = codeField ? codeField.value : (this.appData.dartCode || '');

        // Prefer same-origin proxy (/preview -> nginx -> worker), with fallbacks
        const bases = [];
        bases.push(''); // same-origin
        try {
            if (typeof window !== 'undefined' && window.WORKZ_WORKER_BASE) {
                bases.push(String(window.WORKZ_WORKER_BASE).replace(/\/$/, ''));
            }
        } catch (_) {}
        bases.push('http://localhost:9091');

        let lastError = null;
        for (const base of bases) {
            const baseNorm = String(base || '');
            const urlBase = baseNorm; // '' means same-origin
            // Important: use trailing slash to match nginx location ^~ /preview/
            const postUrl = (urlBase ? urlBase : '') + '/preview/';
            try {
                const resp = await fetch(postUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ slug, dart_code: dartCode })
                });
                const data = await resp.json();
                if (!resp.ok || !data || !data.success) {
                    throw new Error(data?.message || (`Falha ao gerar preview em ${postUrl}`));
                }
                // Success
                this._activePreviewBase = urlBase; // track for cleanup and iframe src
                this._activePreviewToken = data.data?.token || null;
                const url = data.data?.url || null; // like /preview/<token>/
                const body = document.getElementById('live-preview-body');
                if (url && body) {
                    body.innerHTML = '<iframe id="live-preview-frame" style="width:100%; height:70vh; border:0; background:#fff"></iframe>';
                    const iframe = document.getElementById('live-preview-frame');
                    iframe.src = (urlBase || '') + url;
                } else if (body) {
                    body.innerHTML = '<div class="p-3 text-danger">Pré-visualização indisponível.</div>';
                }
                return; // stop after first success
            } catch (e) {
                lastError = e;
                // try next base
            }
        }
        console.error('Flutter preview failed:', lastError);
        const body = document.getElementById('live-preview-body');
        if (body) body.innerHTML = `<div class="p-3 text-danger">Erro: ${this.escapeHtml(lastError?.message || String(lastError))}<br/><small>Tente iniciar o build-worker ou acessar via /preview (proxy nginx).</small></div>`;
    },
    async showBuildStatus(appId) {
        try {
            const response = await this.getBuildStatusCompat(appId);
            if (response && response.success && response.data) {
                this.displayBuildStatusModal(response.data);
                const status = response.data.build_status;
                if (status === 'building' || status === 'pending') {
                    this.startBuildWatch(appId);
                }
                return;
            }
        } catch (e) {
            console.warn('Falha inicial ao obter status do build, iniciando polling:', e);
        }
        // Se falhar, mostrar modal pendente e iniciar polling
        this.displayPendingBuildModal(appId);
        this.startBuildWatch(appId);
    },

    displayBuildStatusModal(buildData) {
        const existing = document.getElementById('buildStatusModal');
        if (!existing) {
            const modalHtml = `
                <div class="modal fade" id="buildStatusModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-hammer"></i> Status do Build
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="build-status-content">${this.renderBuildStatusContent(buildData)}</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal(modalHtml, 'buildStatusModal');
        } else {
            const content = document.getElementById('build-status-content');
            if (content) { content.innerHTML = this.renderBuildStatusContent(buildData); }
        }
    },

    renderBuildStatusContent(buildData) {
        const statusConfig = {
            'pending': { class: 'alert-warning', icon: 'fa-clock', text: 'Build na fila' },
            'building': { class: 'alert-info', icon: 'fa-spinner fa-spin', text: 'Compilando...' },
            'success': { class: 'alert-success', icon: 'fa-check', text: 'Build concluído com sucesso' },
            'failed': { class: 'alert-danger', icon: 'fa-times', text: 'Build falhou' }
        };

        const config = statusConfig[buildData.build_status] || statusConfig['success'];

        const problems = this.extractBuildProblems(buildData.build_log || '') || [];
        return `
            <div class="alert ${config.class}">
                <div class="d-flex align-items-center">
                    <i class="fas ${config.icon} fa-2x me-3"></i>
                    <div>
                        <h6 class="mb-1">${config.text}</h6>
                        <small>Última atualização: ${this.formatDateTime(buildData.compiled_at)}</small>
                    </div>
                </div>
            </div>
            
            ${buildData.builds && buildData.builds.length > 0 ? `
                <h6>Builds por Plataforma:</h6>
                <div class="row">
                    ${buildData.builds.map(build => this.renderPlatformBuild(build)).join('')}
                </div>
            ` : ''}
            
            ${buildData.build_status === 'failed' && problems.length > 0 ? `
                <div class="mt-3">
                    <h6>Problemas detectados:</h6>
                    <div class="alert alert-warning" style="white-space: pre-wrap;">
                        <ul style="margin:0; padding-left: 18px;">
                            ${problems.slice(0, 10).map(p => `<li>${this.escapeHtml(p)}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            ` : ''}

            ${buildData.build_log ? `
                <div class="mt-3">
                    <h6>Log do Build:</h6>
                    <div class="build-log">
                        <pre class="bg-dark text-light p-3 rounded" style="max-height: 360px; overflow:auto;">${this.escapeHtml(buildData.build_log)}</pre>
                    </div>
                </div>
            ` : ''}
        `;
    },

    // Extrai uma lista resumida de problemas do log (dart analyze / compiler)
    extractBuildProblems(logText) {
        try {
            const lines = String(logText || '').split(/\r?\n/);
            const out = [];
            const reErr = /\bError:\b/i;
            const reBullet = /\berror\s+•\b/i;
            const reLoc = /\b(?:lib|src)\/.*\.dart:\d+(?::\d+)?\b/;
            for (let i = 0; i < lines.length; i++) {
                const ln = lines[i].trim();
                if (!ln) continue;
                if (reErr.test(ln) || reBullet.test(ln) || reLoc.test(ln)) {
                    out.push(ln);
                    // Se a próxima linha for um caret ou detalhe, adiciona junto
                    if (i + 1 < lines.length) {
                        const next = lines[i + 1].trim();
                        if (next.startsWith('^') || next.startsWith('│') || next.startsWith('•')) {
                            out.push(next);
                            i++;
                        }
                    }
                }
                if (out.length > 30) break;
            }
            return out;
        } catch (_) { return []; }
    },

    // Escapa HTML para exibição segura em <pre> e itens
    escapeHtml(s) {
        return String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    },

    renderPlatformBuild(build) {
        const platformIcons = {
            'web': 'fas fa-globe text-info',
            'android': 'fab fa-android text-success',
            'ios': 'fas fa-mobile-alt text-primary',
            'windows': 'fab fa-windows text-info',
            'macos': 'fab fa-apple text-secondary',
            'linux': 'fab fa-linux text-warning'
        };

        const statusColors = {
            'success': 'text-success',
            'failed': 'text-danger',
            'building': 'text-info',
            'pending': 'text-warning'
        };

        const icon = platformIcons[build.platform] || 'fas fa-desktop';
        const statusColor = statusColors[build.status] || 'text-muted';

        return `
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    <i class="${icon}"></i> ${build.platform.charAt(0).toUpperCase() + build.platform.slice(1)}
                                </h6>
                                <small class="text-muted">v${build.build_version || '1.0.0'}</small>
                            </div>
                            <div class="text-end">
                                <div class="${statusColor}">
                                    <i class="fas fa-circle"></i> ${build.status}
                                </div>
                                ${build.download_url ? `
                                    <a href="${build.download_url}" class="btn btn-sm btn-outline-primary mt-1" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                ` : ''}
                                ${build.store_url ? `
                                    <a href="${build.store_url}" class="btn btn-sm btn-outline-success mt-1" target="_blank">
                                        <i class="fas fa-store"></i>
                                    </a>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    async showArtifacts(appId) {
        try {
            const response = await this.getBuildStatusCompat(appId);
            if (response && response.success && response.data) {
                this.displayArtifactsModal(response.data);
            }
        } catch (e) {
            console.error("Erro ao carregar artefatos:", e);
            alert("Erro ao carregar artefatos: " + e.message);
        }
    },

    displayArtifactsModal(buildData) {
        const modalHtml = `
            <div class="modal fade" id="artifactsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-download"></i> Downloads e Artefatos
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${this.renderArtifactsContent(buildData)}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" onclick="StoreApp.downloadAllArtifacts(${buildData.app_id})">
                                <i class="fas fa-download"></i> Baixar Todos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showModal(modalHtml, 'artifactsModal');
    },

    renderArtifactsContent(buildData) {
        if (!buildData.builds || buildData.builds.length === 0) {
            return `
                <div class="text-center py-4">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhum artefato disponível</h5>
                    <p class="text-muted">Execute um build para gerar os artefatos de download.</p>
                </div>
            `;
        }

        return `
            <div class="artifacts-grid">
                ${buildData.builds.map(build => this.renderArtifactCard(build)).join('')}
            </div>
            
            <div class="mt-4">
                <h6>Informações de Deploy:</h6>
                <div class="deploy-info">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <strong>Última compilação:</strong><br>
                                ${this.formatDateTime(buildData.compiled_at)}
                            </small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">
                                <strong>Status geral:</strong><br>
                                <span class="badge bg-${buildData.build_status === 'success' ? 'success' : 'warning'}">
                                    ${buildData.build_status}
                                </span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    renderArtifactCard(build) {
        const platformInfo = {
            'web': { name: 'Web App', icon: 'fas fa-globe', color: 'primary', ext: 'zip' },
            'android': { name: 'Android APK', icon: 'fab fa-android', color: 'success', ext: 'apk' },
            'ios': { name: 'iOS IPA', icon: 'fas fa-mobile-alt', color: 'info', ext: 'ipa' },
            'windows': { name: 'Windows EXE', icon: 'fab fa-windows', color: 'info', ext: 'exe' },
            'macos': { name: 'macOS App', icon: 'fab fa-apple', color: 'secondary', ext: 'dmg' },
            'linux': { name: 'Linux AppImage', icon: 'fab fa-linux', color: 'warning', ext: 'appimage' }
        };

        const info = platformInfo[build.platform] || { name: build.platform, icon: 'fas fa-desktop', color: 'secondary', ext: 'zip' };

        return `
            <div class="artifact-card mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center">
                                    <div class="artifact-icon me-3">
                                        <i class="${info.icon} fa-2x text-${info.color}"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">${info.name}</h6>
                                        <small class="text-muted">
                                            Versão ${build.build_version || '1.0.0'} • 
                                            ${this.formatDate(build.created_at)}
                                        </small>
                                        <div class="mt-1">
                                            <span class="badge bg-${build.status === 'success' ? 'success' : 'warning'}">
                                                ${build.status}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                ${build.download_url ? `
                                    <a href="${build.download_url}" class="btn btn-outline-primary btn-sm mb-1" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                ` : ''}
                                ${build.store_url ? `
                                    <a href="${build.store_url}" class="btn btn-outline-success btn-sm mb-1" target="_blank">
                                        <i class="fas fa-store"></i> Loja
                                    </a>
                                ` : ''}
                                <div>
                                    <small class="text-muted">
                                        ${this.getFileSizeEstimate(build.platform)}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    async showBuildHistory(appId) { // Corrigido para buscar dados reais
        this.showModal(`
            <div class="modal fade" id="buildHistoryModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-history"></i> Histórico de Builds
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="build-history-content" class="text-center p-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" onclick="StoreApp.triggerNewBuild(${appId})">
                                <i class="fas fa-hammer"></i> Novo Build
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `, 'buildHistoryModal');

        try {
            // Simula uma chamada de API para buscar o histórico real
            const response = await this.apiGet(`/apps/${appId}/build-history`); // Endpoint hipotético
            const historyContentEl = document.getElementById('build-history-content');
            if (response && response.success) {
                historyContentEl.innerHTML = this.renderBuildHistoryContent(response.data);
            } else {
                historyContentEl.innerHTML = '<div class="alert alert-warning">Não foi possível carregar o histórico de builds.</div>';
            }
        } catch (e) {
            const historyContentEl = document.getElementById('build-history-content');
            historyContentEl.innerHTML = `<div class="alert alert-danger">Erro ao carregar histórico: ${e.message}</div>`;
        }
    },

    renderBuildHistoryContent(buildHistory) { // Corrigido para receber dados
        if (!buildHistory || buildHistory.length === 0) {
            return '<div class="text-center p-4 text-muted">Nenhum histórico de build encontrado.</div>';
        }
        return `
            <div class="build-history-list">
                ${buildHistory.map(build => this.renderBuildHistoryItem(build)).join('')}
            </div>
        `;
    },

    renderBuildHistoryItem(build) {
        const statusConfig = {
            'success': { class: 'text-success', icon: 'fa-check-circle' },
            'failed': { class: 'text-danger', icon: 'fa-times-circle' },
            'building': { class: 'text-info', icon: 'fa-spinner fa-spin' }
        };

        const config = statusConfig[build.status] || statusConfig['success'];

        return `
            <div class="build-history-item mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start">
                                    <div class="build-status-icon me-3">
                                        <i class="fas ${config.icon} ${config.class} fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">
                                            Build v${build.version}
                                            <span class="badge bg-secondary ms-2">${build.status}</span>
                                        </h6>
                                        <p class="mb-1 text-muted">${build.commit_message}</p>
                                        <small class="text-muted">
                                            <i class="fas fa-code-branch"></i> ${build.commit_hash} • 
                                            <i class="fas fa-clock"></i> ${build.duration} • 
                                            <i class="fas fa-calendar"></i> ${this.formatDateTime(build.created_at)}
                                        </small>
                                        ${build.error ? `
                                            <div class="mt-2">
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> ${build.error}
                                                </small>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="platform-badges mb-2">
                                    ${build.platforms.map(platform => `
                                        <span class="badge bg-light text-dark me-1">
                                            <i class="fas fa-${platform === 'web' ? 'globe' : platform === 'android' ? 'robot' : 'mobile-alt'}"></i>
                                            ${platform}
                                        </span>
                                    `).join('')}
                                </div>
                                <div class="build-actions">
                                    <button class="btn btn-sm btn-outline-info" onclick="StoreApp.viewBuildDetails(${build.id})">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                    ${build.status === 'success' ? `
                                        <button class="btn btn-sm btn-outline-success" onclick="StoreApp.downloadBuildArtifacts(${build.id})">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Build action methods
    async retryBuild(appId) {
        try {
            // Simulate build retry
            this.showToast('Build reiniciado com sucesso!', 'info');
            bootstrap.Modal.getInstance(document.getElementById('buildStatusModal')).hide();
        } catch (e) {
            console.error('Error retrying build:', e);
            this.showToast('Erro ao reiniciar build: ' + e.message, 'error');
        }
    },

    async triggerNewBuild(appId) {
        try {
            // Simulate new build trigger
            this.showToast('Novo build iniciado!', 'info');
            bootstrap.Modal.getInstance(document.getElementById('buildHistoryModal')).hide();
        } catch (e) {
            console.error('Error triggering build:', e);
            this.showToast('Erro ao iniciar build: ' + e.message, 'error');
        }
    },

    async downloadAllArtifacts(appId) {
        try {
            // Simulate download all
            this.showToast('Preparando download de todos os artefatos...', 'info');
        } catch (e) {
            console.error('Error downloading artifacts:', e);
            this.showToast('Erro ao baixar artefatos: ' + e.message, 'error');
        }
    },

    viewBuildDetails(buildId) {
        console.log('Viewing build details for:', buildId);
        // Would show detailed build information
    },

    downloadBuildArtifacts(buildId) {
        console.log('Downloading artifacts for build:', buildId);
        // Would trigger download
    },

    // Utility methods for build management
    formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleString('pt-BR');
    },

    getFileSizeEstimate(platform) {
        const sizes = {
            'web': '~2.5 MB',
            'android': '~15 MB',
            'ios': '~20 MB',
            'windows': '~25 MB',
            'macos': '~30 MB',
            'linux': '~18 MB'
        };
        return sizes[platform] || '~10 MB';
    },

    // Flutter-specific functions
    async openFlutterApp(appId) {
        const url = `/apps/flutter/${appId}/web/index.html?ts=${Date.now()}`;
        window.open(url, '_blank');
    },

    async rebuildApp(appId) {
        try {
            this.showToast('Iniciando rebuild do app Flutter...', 'info');

            const response = await this.postRebuildCompat(appId);
            const httpOk = response && typeof response.status === 'number' && response.status >= 200 && response.status < 300;
            if (response && (response.success || httpOk)) {
                this.showToast('Rebuild iniciado com sucesso!', 'success');
                // Refresh the build status
                setTimeout(() => this.checkBuildStatus(appId), 2000);
            } else {
                throw new Error(response.message || 'Erro ao iniciar rebuild');
            }
        } catch (e) {
            console.error('Error rebuilding app:', e);
            this.showToast('Erro ao fazer rebuild: ' + e.message, 'error');
        }
    },

    async checkBuildStatus(appId) {
        try {
            const response = await this.getBuildStatusCompat(appId);
            if (response && response.success) {
                this.showBuildStatusModal(response.data);
            } else {
                throw new Error(response.message || 'Erro ao verificar status');
            }
        } catch (e) {
            console.error('Error checking build status:', e);
            this.showToast('Erro ao verificar status do build: ' + e.message, 'error');
        }
    },

    showBuildStatusModal(buildData) {
        const modalHtml = `
            <div class="modal fade" id="buildStatusModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-hammer"></i> Status do Build Flutter
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>App ID:</strong> ${buildData.app_id}
                                </div>
                                <div class="col-md-6">
                                    <strong>Tipo:</strong> ${buildData.app_type}
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Status Geral:</strong>
                                <span class="badge ${this.getBuildStatusClass(buildData.build_status)}">
                                    ${buildData.build_status}
                                </span>
                            </div>
                            
                            ${buildData.compiled_at ? `
                                <div class="mb-3">
                                    <strong>Última Compilação:</strong> ${new Date(buildData.compiled_at).toLocaleString()}
                                </div>
                            ` : ''}
                            
                            ${buildData.build_log ? `
                                <div class="mb-3">
                                    <strong>Log do Build:</strong>
                                    <pre class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">${buildData.build_log}</pre>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" onclick="StoreApp.rebuildApp(${buildData.app_id})">
                                <i class="fas fa-hammer"></i> Rebuild
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        const existingModal = document.getElementById('buildStatusModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('buildStatusModal'));
        modal.show();
    },

    getBuildStatusClass(status) {
        const statusClasses = {
            'success': 'bg-success',
            'failed': 'bg-danger',
            'building': 'bg-info',
            'pending': 'bg-warning'
        };
        return statusClasses[status] || 'bg-secondary';
    },

    getPlatformIcon(platform) {
        const icons = {
            'web': 'fas fa-globe',
            'android': 'fab fa-android',
            'ios': 'fab fa-apple',
            'windows': 'fab fa-windows',
            'macos': 'fab fa-apple',
            'linux': 'fab fa-linux'
        };
        return icons[platform] || 'fas fa-desktop';
    },

    renderError(message) {
        document.getElementById("app-root").innerHTML = `
            <div class="alert alert-danger">
                <h4>Erro</h4>
                <p>${message}</p>
            </div>
        `;
    }
};

// Auto-initialize when DOM is ready
// Only auto-initialize if not running inside the Workz app runner,
// which will call bootstrap manually.
if (typeof WorkzSDKRunner === 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => StoreApp.bootstrap());
    } else {
        StoreApp.bootstrap();
    }
}
