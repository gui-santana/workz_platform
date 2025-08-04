// public/js/main.js

import { ApiClient } from "./core/ApiClient.js";

document.addEventListener('DOMContentLoaded', () => {

    const apiClient = new ApiClient();

    let currentUserData = null;

    async function initializeCurrentUserData() {
        try {
            const userData = await apiClient.get('/me');
            if (userData.error) {
                console.error("Error fetching current user data:", userData.error);                
            }
            currentUserData = userData;
            console.log(currentUserData);
        } catch (error) {
            console.error("Failed to fetch current user data:", error);
            return;
        }
        searchUserById(currentUserData.id);
        return true;
    }    

    async function searchUserById(userId) {
        try {
            const userData = await apiClient.post('/search', { 
                db: 'workz_data',
                table: 'hus',
                columns: ['id', 'tt', 'ml'],
                conditions: {
                    id: userId
                },
                fetchAll: false
            });
            console.log(userData);
            return userData;
        } catch (error) {
            console.error('Failed to fetch user data by id:', error);
            return null;
        }
    }

    initializeCurrentUserData();
});