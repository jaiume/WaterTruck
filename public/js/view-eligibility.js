(function() {
    function isNonEmptyString(value) {
        return typeof value === 'string' && value.trim().length > 0;
    }

    function toPositiveNumber(value) {
        const numberValue = Number(value);
        return Number.isFinite(numberValue) && numberValue > 0 ? numberValue : 0;
    }

    function isTruckDashboardEligible(truck) {
        if (!truck) return false;

        return (
            isNonEmptyString(truck.name) &&
            isNonEmptyString(truck.phone) &&
            toPositiveNumber(truck.capacity_gallons) > 0
        );
    }

    function isOperatorDashboardEligible(operator) {
        if (!operator) return false;
        return toPositiveNumber(operator.id) > 0;
    }

    function deriveRoleRoutingState(meData) {
        return {
            canViewTruckDashboard: isTruckDashboardEligible(meData && meData.truck),
            canViewOperatorDashboard: isOperatorDashboardEligible(meData && meData.operator)
        };
    }

    window.ViewEligibility = {
        isTruckDashboardEligible,
        isOperatorDashboardEligible,
        deriveRoleRoutingState
    };
})();
