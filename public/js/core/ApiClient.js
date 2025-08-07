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
            // CORREÇÃO: Pega o token do localStorage.
            const token = localStorage.getItem('jwt_token');
            const headers = {
                'Content-Type': 'application/json',
            };

            // CORREÇÃO: Se o token existir, adiciona-o aos cabeçalhos.
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }

            const response = await fetch(this.baseUrl + endpoint, {
                method: 'POST',
                headers: headers, // Usa os cabeçalhos atualizados
                body: JSON.stringify(data),
            });
            return response.json();
        } catch (error) {
            console.error('Erro na requisição POST:', error);
            return { error: 'Falha na comunicação com o servidor.' };
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
