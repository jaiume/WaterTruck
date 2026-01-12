/**
 * Operator Module
 * Handles operator-specific functionality
 */

const Operator = (function() {
    
    /**
     * Get current user's operator profile
     */
    async function getMyOperator() {
        const response = await api.get('/operator');
        return response.success ? response.data : null;
    }
    
    /**
     * Create operator profile
     */
    async function createOperator(serviceArea = null) {
        return await api.post('/operator', { service_area: serviceArea });
    }
    
    /**
     * Set operator mode (delegated/dispatcher)
     */
    async function setMode(mode) {
        return await api.post('/operator/mode', { mode });
    }
    
    /**
     * Get operator's trucks
     */
    async function getTrucks() {
        const response = await api.get('/operator/trucks');
        return response.success ? response.data : [];
    }
    
    /**
     * Get operator's jobs
     */
    async function getJobs() {
        const response = await api.get('/operator/jobs');
        return response.success ? response.data : { pending: [], active: [] };
    }
    
    /**
     * Assign job to truck (dispatcher mode)
     */
    async function assignJob(jobId, truckId) {
        return await api.post(`/operator/jobs/${jobId}/assign`, { truck_id: truckId });
    }
    
    /**
     * Generate invite link
     */
    async function generateInvite() {
        return await api.post('/invites');
    }
    
    /**
     * Get invite list
     */
    async function getInvites() {
        // Would need endpoint - not implemented
        return [];
    }
    
    return {
        getMyOperator,
        createOperator,
        setMode,
        getTrucks,
        getJobs,
        assignJob,
        generateInvite,
        getInvites
    };
})();

// Make globally available
window.Operator = Operator;
