// public/js/core/ApiClient.js

export class ApiClient {

    constructor(baseUrl = '/api'){
        this.baseUrl = baseUrl;
    }

    //Método para requisições GET
    async get(endpoint) {
        try {
            const response = await fetch(this.baseUrl + endpoint, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'                    
                }
            });
            return response.json();
        } catch (error) {
            console.error('Erro na requisição GET:', error);
            return { error: 'Falha na comunicação com o servidor.' };
        }
    }

    async put(endpoint, data){
        try{
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
        } catch (error) {
            console.error('Erro na requisição PUT:', error);
        }
    }

    async post(endpoint, data) {
        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Erro na requisição POST:', error);
            return { error: 'Falha na comunicação com o servidor.' };
        }
    }

    async delete(endpoint){
        try{
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                method: 'DELETE'
            });
        } catch (error) {
            console.error('Erro na requisição DELETE:', error);
        }        
    }

}