/**
 * Customer Module
 * Handles customer-specific functionality
 */

const Customer = (function() {
    
    /**
     * Get available trucks
     */
    async function getAvailableTrucks() {
        const response = await api.get('/trucks/available');
        return response.success ? response.data : [];
    }
    
    /**
     * Create a new job
     */
    async function createJob(location, truckIds, customerName = null, lat = null, lng = null) {
        return await api.post('/jobs', {
            location,
            truck_ids: truckIds,
            customer_name: customerName,
            lat,
            lng
        });
    }
    
    /**
     * Get job details
     */
    async function getJob(jobId) {
        return await api.get(`/jobs/${jobId}`);
    }
    
    /**
     * Get current user's jobs
     */
    async function getMyJobs() {
        const me = await api.get('/me');
        if (!me.success) return [];
        // Jobs would need a separate endpoint - for now return empty
        return [];
    }
    
    /**
     * Format price for display
     */
    function formatPrice(price) {
        return '$' + parseFloat(price || 0).toFixed(0);
    }
    
    /**
     * Format capacity for display
     */
    function formatCapacity(gallons) {
        return (gallons || 0) + ' gal';
    }
    
    /**
     * Format ETA for display
     */
    function formatETA(minutes) {
        if (minutes < 60) {
            return `${minutes} min`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMins = minutes % 60;
        if (remainingMins === 0) {
            return `${hours} hr`;
        }
        return `${hours} hr ${remainingMins} min`;
    }
    
    return {
        getAvailableTrucks,
        createJob,
        getJob,
        getMyJobs,
        formatPrice,
        formatCapacity,
        formatETA
    };
})();

// Make globally available
window.Customer = Customer;
