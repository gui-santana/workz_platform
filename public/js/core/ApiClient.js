// public/js/core/ApiClient.js

export class ApiClient {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
    }

    // Método para requisições GET (novo)
    async get(endpoint) {
        try {
            const token = localStorage.getItem('jwt_token');
            if (!token) {
                // Se não houver token, podemos redirecionar para o login ou mostrar um erro.
                // Por enquanto, apenas retornamos um erro.
                return { error: 'Usuário não autenticado.' };
            }

            const response = await fetch(this.baseUrl + endpoint, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}` // Adiciona o token aqui!
                },
            });
            return response.json();
        } catch (error) {
            console.error('Erro na requisição GET:', error);
            return { error: 'Falha na comunicação com o servidor.' };
        }
    }

    async put(endpoint, data) {
        try {
            const token = localStorage.getItem('jwt_token');
            const headers = {
                'Content-Type': 'application/json',
            };

            // Se o token existir, adiciona-o aos cabeçalhos.
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }

            const response = await fetch(this.baseUrl + endpoint, {
                method: 'PUT',
                headers: headers, // Usa os cabeçalhos atualizados
                body: JSON.stringify(data),
            });
            return response.json();
        } catch (error) {
            console.error('Erro na requisição PUT:', error);
            return { error: 'Falha na comunicação com o servidor.' };
        }
    }

    // Método para requisições POST (CORRIGIDO)
    async post(endpoint, data) {
        try {
            const token = localStorage.getItem('jwt_token');
            const headers = { 'Content-Type': 'application/json' };
            if (token) headers['Authorization'] = `Bearer ${token}`;

            let urlPost;
            if (typeof endpoint === 'string') {
                if (endpoint.startsWith('/app/')) {
                    urlPost = endpoint;
                } else if (endpoint.startsWith('/api/')) {
                    urlPost = endpoint;
                } else if (endpoint.startsWith('/')) {
                    urlPost = this.baseUrl + endpoint;
                } else {
                    urlPost = this.baseUrl + '/' + endpoint;
                }
            } else {
                urlPost = this.baseUrl + '/';
            }
            const response = await ApiClient._schedule(() => ApiClient._fetchWithRetry(urlPost, {
                method: 'POST',
                headers,
                body: JSON.stringify(data),
            }));
            const tryJson = async () => { try { return await response.json(); } catch(_) { return {}; } };
            if (!response.ok) {
                const body = await tryJson();
                return { error: true, status: response.status, ...body };
            }
            return await tryJson();
        } catch (error) {
            // Evita poluir o console em erros tratados pelo chamador
            return { error: true, message: 'Falha na comunicação com o servidor.' };
        }
    }

    // Upload de arquivos (multipart/form-data)
    async upload(endpoint, formData) {
        try {
            const token = localStorage.getItem('jwt_token');
            const headers = {};
            if (token) headers['Authorization'] = `Bearer ${token}`;

            const response = await fetch(this.baseUrl + endpoint, {
                method: 'POST',
                headers,
                body: formData
            });
            const tryJson = async () => { try { return await response.json(); } catch (_) { return {}; } };
            if (!response.ok) {
                const body = await tryJson();
                return { error: true, status: response.status, ...body };
            }
            return await tryJson();
        } catch (error) {
            return { error: true, message: 'Falha na comunicação com o servidor.' };
        }
    }
    // NOVO: Método para requisições DELETE
    async delete(endpoint) {
        try {
            const token = localStorage.getItem('jwt_token');
            const headers = { 'Content-Type': 'application/json' };
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }

            const response = await fetch(this.baseUrl + endpoint, {
                method: 'DELETE',
                headers: headers,
            });
            return response.json();
        } catch (error) {
            console.error('Erro na requisição DELETE:', error);
            return { error: 'Falha na comunicação com o servidor.' };
        }
    }

    // Futuramente, teremos outros métodos aqui (get, put, delete)
}

// Controle simples de concorrência + retry com backoff e timeout
ApiClient.concurrency = 2;
ApiClient._active = 0;
ApiClient._queue = [];
ApiClient._delay = (ms) => new Promise(r => setTimeout(r, ms));

ApiClient._runNext = function(){
  if (ApiClient._active >= ApiClient.concurrency) return;
  const item = ApiClient._queue.shift();
  if (!item) return;
  ApiClient._active++;
  const { fn, resolve, reject } = item;
  fn().then(resolve).catch(reject).finally(() => {
    ApiClient._active--;
    setTimeout(ApiClient._runNext, 40);
  });
};

ApiClient._schedule = function(fn){
  return new Promise((resolve, reject) => {
    ApiClient._queue.push({ fn, resolve, reject });
    ApiClient._runNext();
  });
};

ApiClient._fetchWithRetry = async function(url, options = {}, attempt = 0){
  const MAX_RETRY = 3;
  const timeoutMs = 30000;
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const resp = await fetch(url, { ...options, signal: ctrl.signal });
    if ((resp.status === 429 || resp.status === 503) && attempt < MAX_RETRY) {
      const backoff = Math.min(2000, 300 * Math.pow(2, attempt)) + Math.floor(Math.random()*150);
      await ApiClient._delay(backoff);
      return ApiClient._fetchWithRetry(url, options, attempt+1);
    }
    return resp;
  } catch (err) {
    if (attempt < MAX_RETRY) {
      const backoff = Math.min(2000, 300 * Math.pow(2, attempt)) + Math.floor(Math.random()*150);
      await ApiClient._delay(backoff);
      return ApiClient._fetchWithRetry(url, options, attempt+1);
    }
    throw err;
  } finally {
    clearTimeout(timer);
  }
};










