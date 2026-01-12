/**
 * API Module
 * Wrapper for all API calls with device token authentication
 */

const api = (function() {
    const BASE_URL = '/api';
    
    /**
     * Make an API request
     */
    async function request(method, endpoint, data = null) {
        const url = BASE_URL + endpoint;
        
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Device-Token': Identity.getDeviceToken()
            },
            credentials: 'include' // Include cookies
        };
        
        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const json = await response.json();
            
            // Handle HTTP errors
            if (!response.ok) {
                return {
                    success: false,
                    message: json.message || `HTTP ${response.status}`,
                    status: response.status
                };
            }
            
            return json;
        } catch (error) {
            console.error('API Error:', error);
            return {
                success: false,
                message: error.message || 'Network error',
                status: 0
            };
        }
    }
    
    /**
     * GET request
     */
    function get(endpoint) {
        return request('GET', endpoint);
    }
    
    /**
     * POST request
     */
    function post(endpoint, data = {}) {
        return request('POST', endpoint, data);
    }
    
    /**
     * PUT request
     */
    function put(endpoint, data = {}) {
        return request('PUT', endpoint, data);
    }
    
    /**
     * DELETE request
     */
    function del(endpoint) {
        return request('DELETE', endpoint);
    }
    
    return {
        get,
        post,
        put,
        delete: del,
        request
    };
})();

// Make globally available
window.api = api;
