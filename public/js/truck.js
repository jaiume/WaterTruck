/**
 * Truck Module
 * Handles truck-specific functionality
 */

const Truck = (function() {
    
    /**
     * Get current user's truck
     */
    async function getMyTruck() {
        const me = await api.get('/me');
        if (me.success && me.data.truck) {
            return me.data.truck;
        }
        return null;
    }
    
    /**
     * Create a new truck for current user
     */
    async function createTruck(data = {}) {
        return await api.post('/trucks', data);
    }
    
    /**
     * Update truck details
     */
    async function updateTruck(truckId, data) {
        return await api.put(`/trucks/${truckId}`, data);
    }
    
    /**
     * Get truck's jobs
     */
    async function getJobs(truckId) {
        return await api.get(`/trucks/${truckId}/jobs`);
    }
    
    /**
     * Accept a job
     */
    async function acceptJob(jobId) {
        return await api.post(`/jobs/${jobId}/accept`);
    }
    
    /**
     * Reject a job
     */
    async function rejectJob(jobId) {
        return await api.post(`/jobs/${jobId}/reject`);
    }
    
    /**
     * Update job status
     */
    async function updateJobStatus(jobId, status) {
        return await api.post(`/jobs/${jobId}/status`, { status });
    }
    
    /**
     * Check if truck meets minimum requirements
     */
    function meetsRequirements(truck) {
        return truck && truck.name && truck.phone && truck.capacity_gallons;
    }
    
    /**
     * Get truck status text
     */
    function getStatusText(truck) {
        if (!truck) return 'Not Set Up';
        if (!meetsRequirements(truck)) return 'Setup Incomplete';
        if (truck.is_active) return 'Active';
        return 'Inactive';
    }
    
    return {
        getMyTruck,
        createTruck,
        updateTruck,
        getJobs,
        acceptJob,
        rejectJob,
        updateJobStatus,
        meetsRequirements,
        getStatusText
    };
})();

// Make globally available
window.Truck = Truck;
