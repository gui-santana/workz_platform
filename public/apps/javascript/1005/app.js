// JavaScript otimizado
// Compilado em: 2025-11-07 15:31:15
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    // App Tarefas - Workz Platform
// Sistema completo de gerenciamento de tarefas com pastas e status

window.StoreApp = {
    currentView: 'tarefas', // tarefas, pastas, competencias
    currentFilter: 'todas', // todas, pasta específica
    selectedFolder: null,
    tasks: [],
    folders: [],
    customStatuses: ['Em execução', 'Iniciadas', 'Pendentes'],
    defaultFoldersCreated: false,

    async bootstrap() {
        try {
            await WorkzSDK.init({ mode: 'embed' });
            const user = WorkzSDK.getUser();

            if (!user) {
                this.showError('Usuário não autenticado');
                return;
            }

            // Teste simples do storage
            await this.testStorage();

            await this.loadData();
            this.renderApp();
            this.bindEvents();
        } catch (error) {
            console.error('Erro ao inicializar:', error);
            this.showError('Erro ao carregar o aplicativo');
        }
    },

    async testStorage() {
        try {
            console.log('=== TESTE DE STORAGE ===');

            // Informações do SDK
            const user = WorkzSDK.getUser();
            const context = WorkzSDK.getContext();
            console.log('SDK Info:', {
                version: WorkzSDK._version,
                ready: WorkzSDK._ready,
                token: WorkzSDK._token ? 'presente' : 'ausente',
                user: user,
                userId: user?.id,
                context: context
            });

            // Decodificar JWT para ver o audience
            if (WorkzSDK._token) {
                try {
                    const tokenParts = WorkzSDK._token.split('.');
                    const payload = JSON.parse(atob(tokenParts[1]));
                    console.log('JWT Payload:', payload);
                    console.log('JWT Audience:', payload.aud);
                } catch (e) {
                    console.error('Erro ao decodificar JWT:', e);
                }
            }

            // Verificar se o usuário tem ID
            if (!user || !user.id) {
                console.error('PROBLEMA: Usuário sem ID válido!', user);
                return;
            }

            // Teste 1: Query simples
            console.log('Teste 1: Query simples');
            const queryResult = await WorkzSDK.storage.docs.query({});
            console.log('Resultado da query:', queryResult);

            // Teste 2: Save simples
            console.log('Teste 2: Save simples');
            const testData = {
                test: true,
                timestamp: new Date().toISOString(),
                message: 'Teste de storage'
            };
            const saveResult = await WorkzSDK.storage.docs.save('test_' + Date.now(), testData);
            console.log('Resultado do save:', saveResult);

            console.log('=== FIM DO TESTE ===');
        } catch (error) {
            console.error('Erro no teste de storage:', error);
            console.error('Detalhes do erro:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
        }
    },

    async loadData() {
        try {
            // Tentar carregar do storage primeiro
            try {
                console.log('Carregando dados do storage...');
                const tasksResponse = await WorkzSDK.storage.docs.query({});
                console.log('Resposta das tarefas:', tasksResponse);

                if (tasksResponse.success) {
                    const documents = tasksResponse.data || tasksResponse.documents || [];
                    this.tasks = documents
                        .filter(doc => doc.id && doc.id.startsWith('task_'))
                        .map(doc => ({ id: doc.id, ...doc.document }));
                }

                const foldersResponse = await WorkzSDK.storage.docs.query({});
                console.log('Resposta das pastas:', foldersResponse);

                if (foldersResponse.success) {
                    const documents = foldersResponse.data || foldersResponse.documents || [];
                    this.folders = documents
                        .filter(doc => doc.id && doc.id.startsWith('folder_'))
                        .map(doc => ({ id: doc.id, ...doc.document }));
                }
            } catch (storageError) {
                console.error('Storage indisponível, carregando dados locais:', storageError);
                this.loadFromLocalStorage();
            }

            // Criar pastas padrão localmente se não existirem
            if (this.folders.length === 0 && !this.defaultFoldersCreated) {
                this.createDefaultFoldersLocally();
            }

            console.log('Dados carregados:', { tasks: this.tasks.length, folders: this.folders.length });
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.loadFromLocalStorage();
            // Criar pastas padrão localmente em caso de erro
            if (this.folders.length === 0 && !this.defaultFoldersCreated) {
                this.createDefaultFoldersLocally();
            }
        }
    },

    createDefaultFoldersLocally() {
        if (this.defaultFoldersCreated) return;
        this.defaultFoldersCreated = true;

        this.folders = [
            { id: 'folder_1', name: 'IBH - Entrevias', description: 'Peça destinada ao acompanhamento das obrigações de Entrevias', color: '#FFD700' },
            { id: 'folder_2', name: 'IBH - CART', description: 'Cartório de Registro de Títulos', color: '#FF4444' },
            { id: 'folder_3', name: 'IBH - EXO', description: 'Exame de Ordem', color: '#4444FF' },
            { id: 'folder_4', name: 'Educação', description: 'Tarefas relacionadas à educação', color: '#FFB6C1' },
            { id: 'folder_5', name: 'Finanças Pessoais', description: 'Controle financeiro pessoal', color: '#FF69B4' }
        ];

        // Criar algumas tarefas de exemplo
        this.tasks = [
            { id: 'task_1', title: 'B3 - Atualização Cadastral da Entrevias', description: 'Tarefa de exemplo', folderId: 'folder_1', status: 'Em execução', priority: 27 },
            { id: 'task_2', title: 'Pentágono - CART Apólice de Seguro', description: 'Tarefa de exemplo', folderId: 'folder_2', status: 'Em execução', priority: 15 },
            { id: 'task_3', title: 'CVM - Anúncio de Encerramento', description: 'Tarefa de exemplo', folderId: 'folder_1', status: 'Iniciadas', priority: 12 },
            { id: 'task_4', title: 'Workz! - Registro de Marca', description: 'Tarefa de exemplo', folderId: 'folder_4', status: 'Iniciadas', priority: 70 },
            { id: 'task_5', title: 'Pátria - Debenture Tracking', description: 'Tarefa de exemplo', folderId: 'folder_5', status: 'Pendentes', priority: 12 },
            { id: 'task_6', title: 'Prefeitura - Acompanhar recurso', description: 'Tarefa de exemplo', folderId: 'folder_2', status: 'Pendentes', priority: 8 }
        ];
    },

    // Métodos de armazenamento local
    saveToLocalStorage(id, data, type) {
        try {
            const key = `tarefas_app_${type}s`;
            const existing = JSON.parse(localStorage.getItem(key) || '{}');
            existing[id] = data;
            localStorage.setItem(key, JSON.stringify(existing));
        } catch (error) {
            console.error('Erro ao salvar no localStorage:', error);
        }
    },

    loadFromLocalStorage() {
        try {
            // Carregar tarefas do localStorage
            const tasksData = JSON.parse(localStorage.getItem('tarefas_app_tasks') || '{}');
            this.tasks = Object.keys(tasksData).map(id => ({ id, ...tasksData[id] }));

            // Carregar pastas do localStorage
            const foldersData = JSON.parse(localStorage.getItem('tarefas_app_folders') || '{}');
            this.folders = Object.keys(foldersData).map(id => ({ id, ...foldersData[id] }));
        } catch (error) {
            console.error('Erro ao carregar do localStorage:', error);
            this.tasks = [];
            this.folders = [];
        }
    },

    deleteFromLocalStorage(id, type) {
        try {
            const key = `tarefas_app_${type}s`;
            const existing = JSON.parse(localStorage.getItem(key) || '{}');
            delete existing[id];
            localStorage.setItem(key, JSON.stringify(existing));
        } catch (error) {
            console.error('Erro ao deletar do localStorage:', error);
        }
    },

    renderApp() {
        const user = WorkzSDK.getUser();
        const appHTML = `
      <div class="tarefas-app">
        <!-- Header -->
        <div class="app-header">
          <div class="header-content">
            <div class="header-left">
              <div class="app-title">
                <i class="fas fa-tasks"></i>
                <span>Tarefas</span>
              </div>
              <div class="user-level">
                <span class="level-badge">Nível 5</span>               
              </div>
            </div>
            <div class="header-right">
              <div class="user-avatar">
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiM2MzY2RjEiLz4KPHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDEyQzE0LjIwOTEgMTIgMTYgMTAuMjA5MSAxNiA4QzE2IDUuNzkwODYgMTQuMjA5MSA0IDEyIDRDOS43OTA4NiA0IDggNS43OTA4NiA4IDhDOCAxMC4yMDkxIDkuNzkwODYgMTIgMTIgMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIgMTRDOC42ODYyOSAxNCA2IDE2LjY4NjMgNiAyMFYyMkgxOFYyMEMxOCAxNi42ODYzIDE1LjMxMzcgMTQgMTIgMTRaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4KPC9zdmc+" alt="${user.name}">
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation -->
        <div class="bottom-nav">
          <button class="nav-item ${this.currentView === 'tarefas' ? 'active' : ''}" onclick="StoreApp.switchView('tarefas')">
            <i class="fas fa-list"></i>
            <span>Tarefas</span>
          </button>
          <button class="nav-item ${this.currentView === 'pastas' ? 'active' : ''}" onclick="StoreApp.switchView('pastas')">
            <i class="fas fa-folder"></i>
            <span>Pastas</span>
          </button>
          <button class="nav-item ${this.currentView === 'competencias' ? 'active' : ''}" onclick="StoreApp.switchView('competencias')">
            <i class="fas fa-chart-bar"></i>
            <span>Competências</span>
          </button>
        </div>

        <!-- Main Content -->
        <div class="main-content">
          ${this.renderCurrentView()}
        </div>

        <!-- Floating Action Button -->
        <div class="fab-container">
          <button class="fab" onclick="StoreApp.showNewTaskModal()">
            <i class="fas fa-plus"></i>
          </button>
        </div>

        <!-- Modals -->
        ${this.renderModals()}
      </div>

      ${this.renderStyles()}
    `;

        document.getElementById('app-root').innerHTML = appHTML;
    },

    renderCurrentView() {
        switch (this.currentView) {
            case 'tarefas':
                return this.renderTasksView();
            case 'pastas':
                return this.renderFoldersView();
            case 'competencias':
                return this.renderCompetenciasView();
            default:
                return this.renderTasksView();
        }
    },

    renderTasksView() {
        const filteredTasks = this.getFilteredTasks();
        const tasksByStatus = this.groupTasksByStatus(filteredTasks);

        return `
      <div class="tasks-view">
        <!-- Filter Header -->
        <div class="filter-header">
          <div class="filter-dropdown">
            <select id="folder-filter" onchange="StoreApp.changeFilter(this.value)">
              <option value="todas">Todas</option>
              ${this.folders.map(folder =>
            `<option value="${folder.id}" ${this.currentFilter === folder.id ? 'selected' : ''}>${folder.name}</option>`
        ).join('')}
            </select>
          </div>
          ${this.selectedFolder ? `
            <div class="folder-info">
              <div class="folder-icon" style="background-color: ${this.selectedFolder.color}">
                <i class="fas fa-folder"></i>
              </div>
              <div class="folder-details">
                <h3>${this.selectedFolder.name}</h3>
                <p>${this.selectedFolder.description}</p>
              </div>
            </div>
          ` : ''}
        </div>

        <!-- Tasks by Status -->
        <div class="tasks-container">
          ${this.customStatuses.map(status => `
            <div class="status-section">
              <h4 class="status-title">${status} (${(tasksByStatus[status] || []).length})</h4>
              <div class="tasks-grid">
                ${(tasksByStatus[status] || []).map(task => this.renderTaskCard(task)).join('')}
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
    },

    renderFoldersView() {
        return `
      <div class="folders-view">
        <div class="folders-grid">
          ${this.folders.map(folder => this.renderFolderCard(folder)).join('')}
          <div class="folder-card add-folder" onclick="StoreApp.showNewFolderModal()">
            <div class="folder-icon">
              <i class="fas fa-plus"></i>
            </div>
            <div class="folder-name">Nova Pasta</div>
          </div>
        </div>
      </div>
    `;
    },

    renderCompetenciasView() {
        const user = WorkzSDK.getUser();
        return `
      <div class="competencias-view">
        <div class="competencias-card">
          <h3>Competências de ${user.name || 'Usuário'}</h3>
          <p>Evolução das suas habilidades ao longo de sua jornada com Workz! Tarefas.</p>
          
          <div class="chart-container">
            <div class="chart-placeholder">
              <i class="fas fa-chart-bar fa-3x"></i>
              <p>Gráfico de competências será exibido aqui</p>
            </div>
          </div>
          
          <div class="experience-info">
            <strong>Experiência acumulada: 3.054,50</strong>
          </div>
        </div>
      </div>
    `;
    },

    renderTaskCard(task) {
        const folder = this.folders.find(f => f.id === task.folderId);
        const folderColor = folder ? folder.color : '#6c757d';

        return `
      <div class="task-card" onclick="StoreApp.editTask('${task.id}')">
        <div class="task-header">
          <div class="task-priority">
            <span class="priority-badge">${task.priority || '12'}</span>
          </div>
          <div class="task-actions">
            <button class="task-action-btn" onclick="event.stopPropagation(); StoreApp.toggleTaskStatus('${task.id}')">
              <i class="fas fa-check"></i>
            </button>
          </div>
        </div>
        
        <div class="task-content">
          <h5 class="task-title">${task.title}</h5>
          <p class="task-description">${task.description || ''}</p>
        </div>
        
        <div class="task-footer">
          <div class="task-folder" style="background-color: ${folderColor}">
            ${folder ? folder.name : 'Sem pasta'}
          </div>
          <div class="task-date">
            ${task.dueDate ? new Date(task.dueDate).toLocaleDateString('pt-BR') : ''}
          </div>
        </div>
      </div>
    `;
    },

    renderFolderCard(folder) {
        const taskCount = this.tasks.filter(t => t.folderId === folder.id).length;

        return `
      <div class="folder-card" onclick="StoreApp.selectFolder('${folder.id}')" style="background-color: ${folder.color}">
        <div class="folder-icon">
          <i class="fas fa-folder"></i>
        </div>
        <div class="folder-info">
          <div class="folder-name">${folder.name}</div>
          <div class="task-count">${taskCount}</div>
        </div>
        <div class="folder-actions">
          <button class="folder-action-btn" onclick="event.stopPropagation(); StoreApp.editFolder('${folder.id}')">
            <i class="fas fa-edit"></i>
          </button>
        </div>
      </div>
    `;
    },

    renderModals() {
        return `
      <!-- Modal Nova Tarefa -->
      <div class="modal-overlay" id="taskModal" style="display: none;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="taskModalTitle">Nova Tarefa</h5>
              <button type="button" class="btn-close" onclick="StoreApp.hideModal('taskModal')">&times;</button>
            </div>
            <div class="modal-body">
              <form id="taskForm">
                <input type="hidden" id="taskId" value="">
                
                <div class="form-group">
                  <label for="taskTitle">Título</label>
                  <input type="text" id="taskTitle" placeholder="Adicionar o título da Tarefa" required>
                </div>
                
                <div class="form-group">
                  <label for="taskDescription">Observações</label>
                  <textarea id="taskDescription" placeholder="Adicionar notas" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                  <label for="taskFolder">Pasta</label>
                  <select id="taskFolder" required>
                    <option value="">Selecione</option>
                    ${this.folders.map(folder =>
            `<option value="${folder.id}">${folder.name}</option>`
        ).join('')}
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="taskTeam">Equipe</label>
                  <select id="taskTeam">
                    <option value="me">Somente eu</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="taskDifficulty">Dificuldade</label>
                  <select id="taskDifficulty">
                    <option value="trivial">Trivial</option>
                    <option value="easy">Fácil</option>
                    <option value="medium">Médio</option>
                    <option value="hard">Difícil</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="taskFrequency">Frequência</label>
                  <select id="taskFrequency">
                    <option value="once">Única (Não recorrente)</option>
                    <option value="daily">Diária</option>
                    <option value="weekly">Semanal</option>
                    <option value="monthly">Mensal</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label for="taskDueDate">Prazo</label>
                  <input type="date" id="taskDueDate">
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" id="deleteTaskBtn" style="display:none;" onclick="StoreApp.deleteTask()">
                <i class="fas fa-trash"></i> Excluir
              </button>
              <button type="button" class="btn btn-secondary" onclick="StoreApp.hideModal('taskModal')">Cancelar</button>
              <button type="button" class="btn btn-primary" onclick="StoreApp.saveTask()">
                <i class="fas fa-save"></i> Salvar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Nova Pasta -->
      <div class="modal-overlay" id="folderModal" style="display: none;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="folderModalTitle">Nova Pasta</h5>
              <button type="button" class="btn-close" onclick="StoreApp.hideModal('folderModal')">&times;</button>
            </div>
            <div class="modal-body">
              <form id="folderForm">
                <input type="hidden" id="folderId" value="">
                
                <div class="form-group">
                  <label for="folderTitle">Título</label>
                  <input type="text" id="folderTitle" placeholder="Adicionar o título da Pasta" required>
                </div>
                
                <div class="form-group">
                  <label for="folderDescription">Observações</label>
                  <textarea id="folderDescription" placeholder="Adicionar notas" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                  <label for="folderColor">Cor da Pasta</label>
                  <input type="color" id="folderColor" value="#3b82f6">
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" id="deleteFolderBtn" style="display:none;" onclick="StoreApp.deleteFolder()">
                <i class="fas fa-trash"></i> Excluir
              </button>
              <button type="button" class="btn btn-secondary" onclick="StoreApp.hideModal('folderModal')">Cancelar</button>
              <button type="button" class="btn btn-primary" onclick="StoreApp.saveFolder()">
                <i class="fas fa-save"></i> Salvar
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    },

    renderStyles() {
        return `
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }

        .tarefas-app {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          height: 100vh;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          flex-direction: column;
          overflow: hidden;
        }

        .app-header {
          background: rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(10px);
          padding: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-content {
          display: flex;
          justify-content: space-between;
          align-items: center;
          max-width: 1200px;
          margin: 0 auto;
        }

        .app-title {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          color: white;
          font-size: 1.5rem;
          font-weight: bold;
        }

        .user-level {
          display: flex;
          align-items: center;
          gap: 1rem;
          margin-top: 0.25rem;
        }

        .level-badge {
          background: #ffd700;
          color: #333;
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.8rem;
          font-weight: bold;
        }

        .xp-info {
          color: white;
          font-size: 0.9rem;
        }

        .user-avatar img {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .main-content {
          flex: 1;
          overflow-y: auto;
          padding: 1rem;
          padding-bottom: 80px;
        }

        .filter-header {
          background: rgba(255, 255, 255, 0.95);
          border-radius: 12px;
          padding: 1rem;
          margin-bottom: 1rem;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .filter-dropdown select {
          width: 100%;
          padding: 0.75rem;
          border: 1px solid #ddd;
          border-radius: 8px;
          font-size: 1rem;
          background: white;
        }

        .folder-info {
          display: flex;
          align-items: center;
          gap: 1rem;
          margin-top: 1rem;
          padding-top: 1rem;
          border-top: 1px solid #eee;
        }

        .folder-icon {
          width: 50px;
          height: 50px;
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-size: 1.5rem;
        }

        .folder-details h3 {
          margin: 0;
          color: #333;
          font-size: 1.2rem;
        }

        .folder-details p {
          margin: 0.25rem 0 0 0;
          color: #666;
          font-size: 0.9rem;
        }

        .status-section {
          margin-bottom: 2rem;
        }

        .status-title {
          color: white;
          margin-bottom: 1rem;
          font-size: 1.1rem;
          font-weight: 600;
        }

        .tasks-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 1rem;
        }

        .task-card {
          background: rgba(255, 255, 255, 0.95);
          border-radius: 12px;
          padding: 1rem;
          cursor: pointer;
          transition: all 0.3s ease;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .task-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .task-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 0.75rem;
        }

        .priority-badge {
          background: #ffd700;
          color: #333;
          padding: 0.25rem 0.5rem;
          border-radius: 8px;
          font-weight: bold;
          font-size: 0.8rem;
        }

        .task-action-btn {
          background: #28a745;
          color: white;
          border: none;
          width: 30px;
          height: 30px;
          border-radius: 50%;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.2s ease;
        }

        .task-action-btn:hover {
          background: #218838;
          transform: scale(1.1);
        }

        .task-title {
          font-size: 1rem;
          font-weight: 600;
          color: #333;
          margin-bottom: 0.5rem;
          line-height: 1.3;
        }

        .task-description {
          color: #666;
          font-size: 0.9rem;
          line-height: 1.4;
          margin-bottom: 0.75rem;
        }

        .task-footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          font-size: 0.8rem;
        }

        .task-folder {
          color: white;
          padding: 0.25rem 0.5rem;
          border-radius: 6px;
          font-weight: 500;
        }

        .task-date {
          color: #666;
        }

        .folders-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 1rem;
        }

        .folder-card {
          background: rgba(255, 255, 255, 0.95);
          border-radius: 16px;
          padding: 1.5rem;
          cursor: pointer;
          transition: all 0.3s ease;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
          position: relative;
          overflow: hidden;
        }

        .folder-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .folder-card.add-folder {
          background: rgba(255, 255, 255, 0.2);
          border: 2px dashed rgba(255, 255, 255, 0.5);
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          min-height: 150px;
        }

        .folder-card.add-folder .folder-icon {
          background: rgba(255, 255, 255, 0.3);
          color: white;
          margin-bottom: 0.5rem;
        }

        .folder-card.add-folder .folder-name {
          color: white;
          font-weight: 500;
        }

        .folder-card .folder-icon {
          width: 60px;
          height: 60px;
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-size: 2rem;
          margin-bottom: 1rem;
          background: rgba(0, 0, 0, 0.2);
        }

        .folder-name {
          font-size: 1.1rem;
          font-weight: 600;
          color: white;
          margin-bottom: 0.5rem;
        }

        .task-count {
          font-size: 2rem;
          font-weight: bold;
          color: white;
          text-align: center;
        }

        .folder-actions {
          position: absolute;
          top: 1rem;
          right: 1rem;
        }

        .folder-action-btn {
          background: rgba(255, 255, 255, 0.2);
          color: white;
          border: none;
          width: 32px;
          height: 32px;
          border-radius: 50%;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.2s ease;
        }

        .folder-action-btn:hover {
          background: rgba(255, 255, 255, 0.3);
        }

        .competencias-view {
          max-width: 800px;
          margin: 0 auto;
        }

        .competencias-card {
          background: rgba(255, 255, 255, 0.95);
          border-radius: 16px;
          padding: 2rem;
          box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .competencias-card h3 {
          color: #333;
          margin-bottom: 0.5rem;
          font-size: 1.5rem;
        }

        .competencias-card p {
          color: #666;
          margin-bottom: 2rem;
          line-height: 1.5;
        }

        .chart-container {
          background: #f8f9fa;
          border-radius: 12px;
          padding: 3rem;
          margin-bottom: 2rem;
          text-align: center;
        }

        .chart-placeholder {
          color: #6c757d;
        }

        .chart-placeholder i {
          margin-bottom: 1rem;
        }

        .experience-info {
          text-align: center;
          color: #333;
          font-size: 1.1rem;
        }

        .bottom-nav {
          background: rgba(255, 255, 255, 0.95);
          backdrop-filter: blur(10px);
          padding: 0.75rem;
          display: flex;
          justify-content: space-around;
          border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .nav-item {
          background: none;
          border: none;
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 0.25rem;
          padding: 0.5rem;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.2s ease;
          color: #666;
          font-size: 0.8rem;
        }

        .nav-item.active {
          color: #667eea;
          background: rgba(102, 126, 234, 0.1);
        }

        .nav-item i {
          font-size: 1.2rem;
        }

        .fab-container {
          position: fixed;
          bottom: 100px;
          right: 20px;
          z-index: 1000;
        }

        .fab {
          width: 56px;
          height: 56px;
          border-radius: 50%;
          background: #667eea;
          color: white;
          border: none;
          font-size: 1.5rem;
          cursor: pointer;
          box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
          transition: all 0.3s ease;
        }

        .fab:hover {
          transform: scale(1.1);
          box-shadow: 0 6px 30px rgba(102, 126, 234, 0.6);
        }

        .modal-overlay {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          z-index: 2000;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 1rem;
        }

        .modal-dialog {
          background: white;
          border-radius: 16px;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
          max-width: 500px;
          width: 100%;
          max-height: 90vh;
          overflow-y: auto;
        }

        .modal-header {
          padding: 1.5rem;
          border-bottom: 1px solid #eee;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .modal-title {
          margin: 0;
          font-size: 1.25rem;
          font-weight: 600;
          color: #333;
        }

        .btn-close {
          background: none;
          border: none;
          font-size: 1.5rem;
          cursor: pointer;
          color: #666;
          width: 30px;
          height: 30px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 50%;
          transition: all 0.2s ease;
        }

        .btn-close:hover {
          background: #f8f9fa;
        }

        .modal-body {
          padding: 1.5rem;
        }

        .form-group {
          margin-bottom: 1.5rem;
        }

        .form-group label {
          display: block;
          margin-bottom: 0.5rem;
          font-weight: 500;
          color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
          width: 100%;
          padding: 0.75rem;
          border: 1px solid #ddd;
          border-radius: 8px;
          font-size: 1rem;
          transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
          outline: none;
          border-color: #667eea;
          box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
          padding: 1.5rem;
          border-top: 1px solid #eee;
          display: flex;
          justify-content: flex-end;
          gap: 0.75rem;
        }

        .btn {
          padding: 0.75rem 1.5rem;
          border: none;
          border-radius: 8px;
          font-size: 0.9rem;
          font-weight: 500;
          cursor: pointer;
          transition: all 0.2s ease;
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
        }

        .btn-primary {
          background: #667eea;
          color: white;
        }

        .btn-primary:hover {
          background: #5a6fd8;
        }

        .btn-secondary {
          background: #6c757d;
          color: white;
        }

        .btn-secondary:hover {
          background: #5a6268;
        }

        .btn-danger {
          background: #dc3545;
          color: white;
        }

        .btn-danger:hover {
          background: #c82333;
        }

        @media (max-width: 768px) {
          .tasks-grid {
            grid-template-columns: 1fr;
          }
          
          .folders-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
          }
          
          .modal-dialog {
            margin: 0.5rem;
          }
          
          .header-content {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
          }
        }
      </style>
    `;
    },

    // Utility Methods
    getFilteredTasks() {
        if (this.currentFilter === 'todas') {
            return this.tasks;
        }
        return this.tasks.filter(task => task.folderId === this.currentFilter);
    },

    groupTasksByStatus(tasks) {
        const grouped = {};
        this.customStatuses.forEach(status => {
            grouped[status] = tasks.filter(task => task.status === status);
        });
        return grouped;
    },

    // Event Handlers
    switchView(view) {
        this.currentView = view;
        this.renderApp();
    },

    changeFilter(filterId) {
        this.currentFilter = filterId;
        this.selectedFolder = filterId !== 'todas' ? this.folders.find(f => f.id === filterId) : null;
        this.renderApp();
    },

    selectFolder(folderId) {
        this.currentFilter = folderId;
        this.selectedFolder = this.folders.find(f => f.id === folderId);
        this.currentView = 'tarefas';
        this.renderApp();
    },

    // Task Management
    showNewTaskModal() {
        document.getElementById('taskModalTitle').textContent = 'Nova Tarefa';
        document.getElementById('taskForm').reset();
        document.getElementById('taskId').value = '';
        document.getElementById('deleteTaskBtn').style.display = 'none';

        // Set default folder if one is selected
        if (this.selectedFolder) {
            document.getElementById('taskFolder').value = this.selectedFolder.id;
        }

        this.showModal('taskModal');
    },

    editTask(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;

        document.getElementById('taskModalTitle').textContent = 'Editar Tarefa';
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.title;
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskFolder').value = task.folderId || '';
        document.getElementById('taskTeam').value = task.team || 'me';
        document.getElementById('taskDifficulty').value = task.difficulty || 'trivial';
        document.getElementById('taskFrequency').value = task.frequency || 'once';
        document.getElementById('taskDueDate').value = task.dueDate || '';
        document.getElementById('deleteTaskBtn').style.display = 'block';

        this.showModal('taskModal');
    },

    async saveTask() {
        const form = document.getElementById('taskForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const taskId = document.getElementById('taskId').value;
        const isEdit = !!taskId;

        const taskData = {
            title: document.getElementById('taskTitle').value,
            description: document.getElementById('taskDescription').value,
            folderId: document.getElementById('taskFolder').value,
            status: isEdit ? (this.tasks.find(t => t.id === taskId)?.status || 'Pendentes') : 'Pendentes',
            priority: Math.floor(Math.random() * 100) + 1,
            createdAt: isEdit ? undefined : new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };

        try {
            const id = taskId || 'task_' + Date.now();

            // Tentar salvar no storage primeiro, se falhar usar localStorage
            try {
                console.log('Tentando salvar tarefa no storage:', { id, taskData });
                await WorkzSDK.storage.docs.save(id, taskData);
                console.log('Tarefa salva com sucesso no storage');
            } catch (storageError) {
                console.error('Storage indisponível, salvando localmente:', storageError);
                this.saveToLocalStorage(id, taskData, 'task');
            }

            // Atualizar dados localmente
            if (isEdit) {
                const taskIndex = this.tasks.findIndex(t => t.id === taskId);
                if (taskIndex !== -1) {
                    this.tasks[taskIndex] = { id: taskId, ...taskData };
                }
            } else {
                this.tasks.push({ id, ...taskData });
            }

            this.renderApp();
            this.hideModal('taskModal');
            this.showSuccess(isEdit ? 'Tarefa atualizada!' : 'Tarefa criada!');
        } catch (error) {
            console.error('Erro ao salvar tarefa:', error);
            this.showError('Erro ao salvar tarefa');
        }
    },

    async deleteTask() {
        const taskId = document.getElementById('taskId').value;
        if (!taskId) return;

        if (!confirm('Tem certeza que deseja excluir esta tarefa?')) return;

        try {
            // Tentar deletar do storage primeiro, se falhar usar localStorage
            try {
                await WorkzSDK.storage.docs.delete(taskId);
            } catch (storageError) {
                console.log('Storage indisponível, deletando localmente:', storageError);
                this.deleteFromLocalStorage(taskId, 'task');
            }

            // Remover dos dados locais
            this.tasks = this.tasks.filter(t => t.id !== taskId);

            this.renderApp();
            this.hideModal('taskModal');
            this.showSuccess('Tarefa excluída!');
        } catch (error) {
            console.error('Erro ao excluir tarefa:', error);
            this.showError('Erro ao excluir tarefa');
        }
    },

    async toggleTaskStatus(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;

        const statusOrder = ['Pendentes', 'Iniciadas', 'Em execução'];
        const currentIndex = statusOrder.indexOf(task.status);
        const nextIndex = (currentIndex + 1) % statusOrder.length;

        task.status = statusOrder[nextIndex];
        task.updatedAt = new Date().toISOString();

        try {
            // Tentar salvar no storage primeiro, se falhar usar localStorage
            try {
                await WorkzSDK.storage.docs.save(taskId, task);
            } catch (storageError) {
                console.log('Storage indisponível, salvando localmente:', storageError);
                this.saveToLocalStorage(taskId, task, 'task');
            }

            this.renderApp();
            this.showSuccess(`Status alterado para: ${task.status}`);
        } catch (error) {
            console.error('Erro ao alterar status:', error);
            this.showError('Erro ao alterar status');
        }
    },

    // Folder Management
    showNewFolderModal() {
        document.getElementById('folderModalTitle').textContent = 'Nova Pasta';
        document.getElementById('folderForm').reset();
        document.getElementById('folderId').value = '';
        document.getElementById('deleteFolderBtn').style.display = 'none';
        document.getElementById('folderColor').value = '#' + Math.floor(Math.random() * 16777215).toString(16);

        this.showModal('folderModal');
    },

    editFolder(folderId) {
        const folder = this.folders.find(f => f.id === folderId);
        if (!folder) return;

        document.getElementById('folderModalTitle').textContent = 'Editar Pasta';
        document.getElementById('folderId').value = folder.id;
        document.getElementById('folderTitle').value = folder.name;
        document.getElementById('folderDescription').value = folder.description || '';
        document.getElementById('folderColor').value = folder.color || '#3b82f6';
        document.getElementById('deleteFolderBtn').style.display = 'block';

        this.showModal('folderModal');
    },

    async saveFolder() {
        const form = document.getElementById('folderForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const folderId = document.getElementById('folderId').value;
        const isEdit = !!folderId;

        const folderData = {
            name: document.getElementById('folderTitle').value,
            description: document.getElementById('folderDescription').value,
            color: document.getElementById('folderColor').value,
            createdAt: isEdit ? undefined : new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };

        try {
            const id = folderId || 'folder_' + Date.now();

            // Tentar salvar no storage primeiro, se falhar usar localStorage
            try {
                console.log('Tentando salvar pasta no storage:', { id, folderData });
                await WorkzSDK.storage.docs.save(id, folderData);
                console.log('Pasta salva com sucesso no storage');
            } catch (storageError) {
                console.error('Storage indisponível, salvando localmente:', storageError);
                this.saveToLocalStorage(id, folderData, 'folder');
            }

            // Atualizar dados localmente
            if (isEdit) {
                const folderIndex = this.folders.findIndex(f => f.id === folderId);
                if (folderIndex !== -1) {
                    this.folders[folderIndex] = { id: folderId, ...folderData };
                }
            } else {
                this.folders.push({ id, ...folderData });
            }

            this.renderApp();
            this.hideModal('folderModal');
            this.showSuccess(isEdit ? 'Pasta atualizada!' : 'Pasta criada!');
        } catch (error) {
            console.error('Erro ao salvar pasta:', error);
            this.showError('Erro ao salvar pasta');
        }
    },

    async deleteFolder() {
        const folderId = document.getElementById('folderId').value;
        if (!folderId) return;

        const tasksInFolder = this.tasks.filter(t => t.folderId === folderId);
        if (tasksInFolder.length > 0) {
            this.showError('Não é possível excluir uma pasta que contém tarefas');
            return;
        }

        if (!confirm('Tem certeza que deseja excluir esta pasta?')) return;

        try {
            // Tentar deletar do storage primeiro, se falhar usar localStorage
            try {
                await WorkzSDK.storage.docs.delete(folderId);
            } catch (storageError) {
                console.log('Storage indisponível, deletando localmente:', storageError);
                this.deleteFromLocalStorage(folderId, 'folder');
            }

            // Remover dos dados locais
            this.folders = this.folders.filter(f => f.id !== folderId);

            this.renderApp();
            this.hideModal('folderModal');
            this.showSuccess('Pasta excluída!');
        } catch (error) {
            console.error('Erro ao excluir pasta:', error);
            this.showError('Erro ao excluir pasta');
        }
    },

    // UI Helpers
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    },

    showSuccess(message) {
        this.showNotification(message, 'success');
    },

    showError(message) {
        this.showNotification(message, 'error');
    },

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
      transform: translateX(100%);
      transition: transform 0.3s ease;
      ${type === 'success' ? 'background: #28a745;' : ''}
      ${type === 'error' ? 'background: #dc3545;' : ''}
      ${type === 'info' ? 'background: #17a2b8;' : ''}
    `;

        notification.innerHTML = `
      ${message}
      <button onclick="this.parentNode.remove()" style="
        background: none;
        border: none;
        color: white;
        float: right;
        margin-left: 10px;
        cursor: pointer;
        font-size: 16px;
      ">&times;</button>
    `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    },

    bindEvents() {
        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.hideModal(e.target.id);
            }
        });

        // Close modals with ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal-overlay[style*="flex"]');
                modals.forEach(modal => this.hideModal(modal.id));
            }
        });
    }
};

// Initialize the app when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => StoreApp.bootstrap());
} else {
    StoreApp.bootstrap();
}
    
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