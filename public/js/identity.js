/**
 * Identity Module
 * Handles device token generation and storage for lightweight authentication
 */

const Identity = (function() {
    const STORAGE_KEY = 'water_truck_device_token';
    
    /**
     * Generate a UUID v4
     */
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    /**
     * Get the device token, creating one if it doesn't exist
     */
    function getDeviceToken() {
        let token = localStorage.getItem(STORAGE_KEY);
        
        if (!token) {
            token = generateUUID();
            localStorage.setItem(STORAGE_KEY, token);
        }
        
        return token;
    }
    
    /**
     * Clear the device token (for testing/debugging)
     */
    function clearDeviceToken() {
        localStorage.removeItem(STORAGE_KEY);
    }
    
    /**
     * Check if device token exists
     */
    function hasDeviceToken() {
        return localStorage.getItem(STORAGE_KEY) !== null;
    }
    
    // Auto-initialize on load
    getDeviceToken();
    
    return {
        getDeviceToken,
        clearDeviceToken,
        hasDeviceToken,
        generateUUID
    };
})();

// Make globally available
window.Identity = Identity;
