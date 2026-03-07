<?php require_once __DIR__ . '/includes/cache_bust.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Truck - Get Water Delivered</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0891b2;
            --primary-dark: #0e7490;
            --secondary: #06b6d4;
            --accent: #22d3ee;
            --dark: #164e63;
            --light: #ecfeff;
            --gradient: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        body {
            background: linear-gradient(180deg, #ecfeff 0%, #cffafe 50%, #a5f3fc 100%);
            min-height: 100vh;
        }
        
        .hero-section {
            padding: 1rem 0 0.5rem;
            text-align: center;
        }
        
        .water-icon {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .hero-title {
            color: var(--dark);
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0.25rem 0;
        }
        
        .hero-subtitle {
            color: var(--primary-dark);
            font-size: 0.9rem;
            max-width: 400px;
            margin: 0 auto 0.75rem;
        }
        
        .location-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(8, 145, 178, 0.15);
            padding: 2rem;
            max-width: 500px;
            margin: 0 auto;
        }

        .role-chooser-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(8, 145, 178, 0.15);
            padding: 1.5rem;
            max-width: 500px;
            margin: 0 auto 1rem;
        }

        .role-chooser-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
            text-align: center;
        }

        .role-chooser-subtitle {
            color: var(--primary-dark);
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .role-chooser-note {
            margin-top: 0.75rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--primary-dark);
            background: #ecfeff;
            border: 1px solid #a5f3fc;
            border-radius: 10px;
            padding: 0.6rem 0.75rem;
        }

        .btn-role-primary {
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 700;
            padding: 1rem 1.25rem;
        }

        .btn-role-secondary {
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 0.9rem 1rem;
            border: 2px solid #bae6fd;
            background: #f0f9ff;
            color: var(--primary-dark);
        }

        .btn-role-secondary:hover,
        .btn-role-secondary:focus {
            border-color: var(--primary);
            background: #e0f2fe;
            color: var(--dark);
        }
        
        .form-control {
            border: 2px solid #e0f2fe;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(8, 145, 178, 0.1);
        }
        
        .btn-primary {
            background: var(--gradient);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(8, 145, 178, 0.3);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }
        
        .location-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .location-input-wrapper {
            position: relative;
        }
        
        .location-input-wrapper .form-control {
            padding-left: 3rem;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-outline-primary:disabled {
            opacity: 0.6;
        }
        
        .footer-links {
            margin-top: 3rem;
            text-align: center;
        }
        
        .footer-links a {
            color: var(--primary-dark);
            text-decoration: none;
            margin: 0 1rem;
            font-size: 0.9rem;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .input-group-text {
            background: var(--light);
            border: 2px solid #e0f2fe;
            border-right: none;
            border-radius: 12px 0 0 12px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
            border-left: none;
        }
        
        .input-group .form-control:focus {
            border-color: #e0f2fe;
        }
        
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: var(--primary);
        }
        
        .recent-locations {
            margin-top: 0.5rem;
        }
        
        .recent-location {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--light);
            border: none;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--dark);
            margin: 0.25rem 0.25rem 0.25rem 0;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .recent-location:hover {
            background: var(--primary);
            color: white;
        }
        
        @media (max-width: 576px) {
            .hero-title {
                font-size: 1.25rem;
            }
            
            .location-card {
                margin: 0 1rem;
                padding: 1.5rem;
            }

            .role-chooser-card {
                margin: 0 1rem 1rem;
                padding: 1.25rem;
            }

            .btn-role-primary,
            .btn-role-secondary {
                min-height: 52px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <section class="hero-section">
            <div id="app-logo"><i class="bi bi-droplet-fill water-icon"></i></div>
            <h1 class="hero-title">Get Water Delivered</h1>
            <p class="hero-subtitle">Find available water trucks near you. See prices, capacity, and estimated delivery time upfront.</p>
        </section>
        
        <div class="role-chooser-card" id="role-chooser" style="display:none;">
            <h2 class="role-chooser-title">How would you like to continue?</h2>
            <p class="role-chooser-subtitle">Choose your role to get started quickly.</p>
            <button type="button" id="choose-customer" class="btn btn-primary w-100 btn-role-primary mb-2">
                <i class="bi bi-droplet-fill me-2"></i>I am a customer looking for water
            </button>
            <button type="button" id="choose-truck-owner" class="btn w-100 btn-role-secondary mb-2">
                <i class="bi bi-truck me-2"></i>I am a Truck Owner
            </button>
            <button type="button" id="choose-operator" class="btn w-100 btn-role-secondary">
                <i class="bi bi-building me-2"></i>I am an Operator
            </button>
            <div class="role-chooser-note">
                <i class="bi bi-info-circle me-1"></i>
                Truck Owners and Operators: this platform is free to use and provided as a public service.
            </div>
        </div>

        <div class="location-card" id="customer-card" style="display:none;">
            <form id="location-form">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Your delivery location *</label>
                    <button type="button" class="btn btn-outline-primary w-100 mb-2" id="use-gps">
                        <i class="bi bi-crosshairs me-2"></i>Use My Current Location
                    </button>
                    <div class="text-center text-muted small mb-2">— or enter manually —</div>
                    <div class="location-input-wrapper">
                        <i class="bi bi-geo-alt-fill location-icon"></i>
                        <input type="text" class="form-control" id="delivery-location" 
                               placeholder="Enter your address or area">
                    </div>
                    <div class="recent-locations" id="recent-locations"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Your name *</label>
                    <input type="text" class="form-control" id="customer-name" 
                           placeholder="How should the driver address you?" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone number</label>
                    <div class="input-group">
                        <span class="input-group-text" id="country-code">+1</span>
                        <input type="tel" class="form-control" id="customer-phone" 
                               placeholder="Enter your phone number">
                    </div>
                </div>
                
                <input type="hidden" id="lat" value="">
                <input type="hidden" id="lng" value="">
                
                <button type="submit" class="btn btn-primary w-100 mt-3">
                    <i class="bi bi-search me-2"></i>Find Water Trucks
                </button>
            </form>
        </div>
        
        <div class="footer-links" id="footer-links" style="display:none;">
            <a href="/truck">I'm a Truck Owner</a>
            <a href="/operator">I'm an Operator</a>
        </div>
    </div>
    
    <script src="/js/identity.js<?= $cb ?>"></script>
    <script src="/js/api.js<?= $cb ?>"></script>
    <script src="/js/view-eligibility.js<?= $cb ?>"></script>
    <script src="/js/customer.js<?= $cb ?>"></script>
    <script src="/js/seo.js<?= $cb ?>"></script>
    <script>
        // Inject SEO meta tags
        injectSEO('customer');
        
        let config = {
            country_code: '+1-868',
            phone_digits: 7
        };
        const STARTUP_UI_TIMEOUT_MS = 1800;
        const ROLE_CHOICE_KEY = 'home_role_choice_made';
        const ROUTE_GUARD_SOFT_TIMEOUT_MS = 1500;
        const ROUTE_GUARD_HARD_TIMEOUT_MS = 2000;
        let meResponsePromise = null;
        let customerHomeHydrated = false;

        function safeGetLocalStorage(key) {
            try {
                return window.localStorage.getItem(key);
            } catch (error) {
                console.warn('localStorage get failed', key, error);
                return null;
            }
        }

        function safeSetLocalStorage(key, value) {
            try {
                window.localStorage.setItem(key, value);
                return true;
            } catch (error) {
                console.warn('localStorage set failed', key, error);
                return false;
            }
        }

        function safeRemoveLocalStorage(key) {
            try {
                window.localStorage.removeItem(key);
                return true;
            } catch (error) {
                console.warn('localStorage remove failed', key, error);
                return false;
            }
        }

        function safeSetSessionStorage(key, value) {
            try {
                window.sessionStorage.setItem(key, value);
                return true;
            } catch (error) {
                console.warn('sessionStorage set failed', key, error);
                return false;
            }
        }

        function isRoleChooserVisible() {
            const chooser = document.getElementById('role-chooser');
            return chooser.style.display !== 'none';
        }

        function setCustomerFormVisible(isVisible) {
            document.getElementById('customer-card').style.display = isVisible ? 'block' : 'none';
        }

        function setRoleChooserVisible(isVisible) {
            document.getElementById('role-chooser').style.display = isVisible ? 'block' : 'none';
        }

        function setFooterVisible(isVisible) {
            document.getElementById('footer-links').style.display = isVisible ? 'block' : 'none';
        }

        function showCustomerView() {
            setRoleChooserVisible(false);
            setCustomerFormVisible(true);
            setFooterVisible(true);
        }

        function showRoleChooserView() {
            setRoleChooserVisible(true);
            setCustomerFormVisible(false);
            setFooterVisible(false);
        }

        function hydrateCustomerHome() {
            if (customerHomeHydrated) return;
            loadConfig();
            loadSavedData();
            customerHomeHydrated = true;
        }
        
        // Load config from API
        async function loadConfig() {
            try {
                const response = await fetch('/api/config');
                const data = await response.json();
                if (data.success) {
                    config = data.data;
                    document.getElementById('country-code').textContent = config.country_code;
                    
                    // Update logo if configured
                    if (config.logo) {
                        document.getElementById('app-logo').innerHTML = 
                            `<img src="${config.logo}" alt="${config.app_name || 'Logo'}" style="max-height: 80px; max-width: 200px;">`;
                    }
                }
            } catch (e) {
                console.log('Using default config');
            }
        }
        
        // Load saved customer data
        function loadSavedData() {
            const savedName = safeGetLocalStorage('customer_name');
            const savedPhone = safeGetLocalStorage('customer_phone');
            const savedLocation = safeGetLocalStorage('last_location');
            
            if (savedName) {
                document.getElementById('customer-name').value = savedName;
            }
            if (savedPhone) {
                document.getElementById('customer-phone').value = savedPhone;
            }
            if (savedLocation) {
                document.getElementById('delivery-location').value = savedLocation;
            }
            
            // Show recent locations
            renderRecentLocations();
        }
        
        // Get recent locations from localStorage
        function getRecentLocations() {
            try {
                return JSON.parse(safeGetLocalStorage('recent_locations') || '[]');
            } catch {
                return [];
            }
        }
        
        // Add location to recent list
        function addRecentLocation(location) {
            if (!location || location === 'Current Location (GPS)') return;
            
            let recent = getRecentLocations();
            // Remove if exists, add to front
            recent = recent.filter(l => l.toLowerCase() !== location.toLowerCase());
            recent.unshift(location);
            // Keep only last 5
            recent = recent.slice(0, 5);
            safeSetLocalStorage('recent_locations', JSON.stringify(recent));
        }
        
        // Render recent location buttons
        function renderRecentLocations() {
            const container = document.getElementById('recent-locations');
            const recent = getRecentLocations();
            
            if (recent.length === 0) {
                container.innerHTML = '';
                return;
            }
            
            container.innerHTML = recent.map(loc => `
                <button type="button" class="recent-location" onclick="useRecentLocation('${escapeHtml(loc)}')">
                    <i class="bi bi-clock-history"></i>
                    ${escapeHtml(loc.length > 25 ? loc.substring(0, 25) + '...' : loc)}
                </button>
            `).join('');
        }
        
        // Use a recent location
        function useRecentLocation(location) {
            document.getElementById('delivery-location').value = location;
            // Clear GPS coords when using saved location
            document.getElementById('lat').value = '';
            document.getElementById('lng').value = '';
        }
        
        // Format phone number as user types
        function formatPhoneInput(input) {
            let value = input.value.replace(/\D/g, '');
            
            // Limit to expected digits
            value = value.substring(0, config.phone_digits || 10);
            
            // Format based on length (for 7 digits: XXX-XXXX, for 10: XXX-XXX-XXXX)
            if (config.phone_digits === 7) {
                if (value.length > 3) {
                    value = value.substring(0, 3) + '-' + value.substring(3);
                }
            } else {
                if (value.length > 6) {
                    value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6);
                } else if (value.length > 3) {
                    value = value.substring(0, 3) + '-' + value.substring(3);
                }
            }
            
            input.value = value;
        }
        
        // Get full phone number with country code
        function getFullPhoneNumber() {
            const phone = document.getElementById('customer-phone').value.replace(/\D/g, '');
            if (!phone) return '';
            return config.country_code.replace('-', '') + phone;
        }
        
        // Notify nearby offline trucks that a customer is looking for water
        async function notifyNearbyTrucks(lat, lng) {
            try {
                await api.post('/notify-trucks', { lat, lng });
                console.log('Notified nearby trucks');
            } catch (error) {
                // Silent failure - don't interrupt customer experience
                console.log('Could not notify trucks:', error);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function withTimeout(promise, ms) {
            return Promise.race([
                promise,
                new Promise((_, reject) => {
                    setTimeout(() => reject(new Error(`Timed out after ${ms}ms`)), ms);
                })
            ]);
        }

        function getMeResponse() {
            if (!meResponsePromise) {
                meResponsePromise = api.get('/me').catch((error) => {
                    meResponsePromise = null;
                    throw error;
                });
            }

            return meResponsePromise;
        }

        function setRoutingGuard(isActive) {
            const submitButton = document.querySelector('#location-form button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = isActive;
            }
        }

        function hasEligibilityHelpers() {
            return !!(
                window.ViewEligibility &&
                typeof window.ViewEligibility.isTruckDashboardEligible === 'function' &&
                typeof window.ViewEligibility.isOperatorDashboardEligible === 'function'
            );
        }

        function clearRoleLastViewIfMatches(roleValue) {
            if (safeGetLocalStorage('last_view') === roleValue) {
                safeRemoveLocalStorage('last_view');
            }
        }

        function hasSavedCustomerSignal(value) {
            if (!value) return false;
            if (typeof value === 'string') return value.trim().length > 0;
            return true;
        }

        function countCustomerSignals() {
            let count = 0;
            if (hasSavedCustomerSignal(safeGetLocalStorage('customer_name'))) count += 1;
            if (hasSavedCustomerSignal(safeGetLocalStorage('customer_phone'))) count += 1;
            if (hasSavedCustomerSignal(safeGetLocalStorage('last_location'))) count += 1;
            if (getRecentLocations().length > 0) count += 1;
            return count;
        }

        function shouldShowFirstVisitRoleChooser(meData) {
            const roleChoice = safeGetLocalStorage(ROLE_CHOICE_KEY);
            if (roleChoice) return false;

            const lastView = safeGetLocalStorage('last_view');
            if (lastView) return false;

            if (countCustomerSignals() >= 2) return false;

            if (!meData) return true;

            const hasHelper = hasEligibilityHelpers();
            const canViewTruck = hasHelper
                ? window.ViewEligibility.isTruckDashboardEligible(meData.truck)
                : !!meData.truck;
            const canViewOperator = hasHelper
                ? window.ViewEligibility.isOperatorDashboardEligible(meData.operator)
                : !!meData.operator;

            return !canViewTruck && !canViewOperator;
        }

        async function getMeResponseForRouting() {
            const startedAt = Date.now();
            const response = await withTimeout(getMeResponse(), ROUTE_GUARD_HARD_TIMEOUT_MS);
            const elapsedMs = Date.now() - startedAt;

            if (elapsedMs > ROUTE_GUARD_SOFT_TIMEOUT_MS) {
                console.warn('Role validation exceeded soft timeout budget', elapsedMs);
            }

            return response;
        }

        async function checkUserRole() {
            const lastView = safeGetLocalStorage('last_view');

            if (lastView === 'customer') {
                return false;
            }

            if (lastView !== 'truck' && lastView !== 'operator') {
                return false;
            }

            if (!hasEligibilityHelpers()) {
                console.warn('Eligibility helper unavailable; skipping role redirects for this load');
                return false;
            }

            setRoutingGuard(true);

            try {
                const meResponse = await getMeResponseForRouting();
                if (!meResponse.success || !meResponse.data) {
                    clearRoleLastViewIfMatches(lastView);
                    return false;
                }

                const meData = meResponse.data;
                const canViewTruck = window.ViewEligibility.isTruckDashboardEligible(meData.truck);
                const canViewOperator = window.ViewEligibility.isOperatorDashboardEligible(meData.operator);

                if (lastView === 'truck') {
                    if (canViewTruck) {
                        window.location.href = '/truck';
                        return true;
                    }
                    clearRoleLastViewIfMatches('truck');
                    return false;
                }

                if (lastView === 'operator') {
                    if (canViewOperator) {
                        window.location.href = '/operator';
                        return true;
                    }
                    clearRoleLastViewIfMatches('operator');
                }
            } catch (error) {
                console.warn('Role validation failed, staying on customer home', error);
                clearRoleLastViewIfMatches(lastView);
            } finally {
                setRoutingGuard(false);
            }

            return false;
        }

        async function checkUserRoleFallback() {
            try {
                const response = await getMeResponse();
                if (response.success && response.data) {
                    updateFooterLinks(response.data);
                    return response.data;
                }
            } catch (e) {
                // Continue as customer view
                console.warn('Could not load user context for footer links', e);
            }

            return null;
        }

        function updateFooterLinks(user) {
            const footer = document.getElementById('footer-links');
            let truckLink = '<a href="/truck">I\'m a Truck Owner</a>';
            let operatorLink = '<a href="/operator">I\'m an Operator</a>';

            const hasHelper = hasEligibilityHelpers();
            const canViewTruck = hasHelper
                ? window.ViewEligibility.isTruckDashboardEligible(user.truck)
                : !!user.truck;
            const canViewOperator = hasHelper
                ? window.ViewEligibility.isOperatorDashboardEligible(user.operator)
                : !!user.operator;

            if (canViewTruck) {
                truckLink = '<a href="/truck">Go to Truck Dashboard</a>';
            }

            if (canViewOperator) {
                operatorLink = '<a href="/operator">Operator Dashboard</a>';
            }
            
            footer.innerHTML = truckLink + operatorLink;
        }
        
        async function checkActiveJob() {
            const activeJob = await Customer.getActiveJob();
            if (!activeJob) return false;
            if (window.location.pathname !== '/') return false; // already navigating away
            window.location.href = `/job/${activeJob.id}`;
            return true;
        }

        function handleChooseCustomer() {
            safeSetLocalStorage(ROLE_CHOICE_KEY, 'customer');
            safeSetLocalStorage('last_view', 'customer');
            showCustomerView();
            const locationInput = document.getElementById('delivery-location');
            if (locationInput) {
                locationInput.focus();
            }
        }

        function handleChooseTruckOwner() {
            safeSetLocalStorage(ROLE_CHOICE_KEY, 'truck');
            window.location.href = '/truck';
        }

        function handleChooseOperator() {
            safeSetLocalStorage(ROLE_CHOICE_KEY, 'operator');
            window.location.href = '/operator';
        }

        // Initialize
        (async function() {
            let uiResolved = false;
            const uiTimeoutId = setTimeout(() => {
                if (uiResolved) return;
                hydrateCustomerHome();
                showCustomerView();
                uiResolved = true;
            }, STARTUP_UI_TIMEOUT_MS);

            // Active customer job has highest routing priority.
            const jobRedirected = await checkActiveJob();
            if (jobRedirected) {
                clearTimeout(uiTimeoutId);
                return;
            }

            // Next, honor explicit view intent with guarded role validation.
            const roleRedirected = await checkUserRole();
            if (roleRedirected) {
                clearTimeout(uiTimeoutId);
                return;
            }

            // Finally, update passive UI context only (no redirects).
            const meData = await checkUserRoleFallback();
            hydrateCustomerHome();

            if (!uiResolved) {
                if (shouldShowFirstVisitRoleChooser(meData)) {
                    showRoleChooserView();
                } else {
                    showCustomerView();
                }
                uiResolved = true;
                clearTimeout(uiTimeoutId);
            }
        })();

        document.getElementById('choose-customer').addEventListener('click', handleChooseCustomer);
        document.getElementById('choose-truck-owner').addEventListener('click', handleChooseTruckOwner);
        document.getElementById('choose-operator').addEventListener('click', handleChooseOperator);
        
        // Phone formatting
        document.getElementById('customer-phone').addEventListener('input', function() {
            formatPhoneInput(this);
        });
        
        // Form submission
        document.getElementById('location-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const deliveryLocation = document.getElementById('delivery-location').value.trim();
            const customerName = document.getElementById('customer-name').value.trim();
            const customerPhone = document.getElementById('customer-phone').value.trim();
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;
            
            if (!deliveryLocation) {
                alert('Please enter your location');
                return;
            }
            
            if (!customerName) {
                alert('Please enter your name');
                document.getElementById('customer-name').focus();
                return;
            }
            
            // Save to localStorage for persistence
            if (customerName) safeSetLocalStorage('customer_name', customerName);
            if (customerPhone) safeSetLocalStorage('customer_phone', customerPhone);
            safeSetLocalStorage('last_location', deliveryLocation);
            addRecentLocation(deliveryLocation);
            
            // Store in session for this request
            safeSetSessionStorage('delivery_location', deliveryLocation);
            safeSetSessionStorage('customer_name', customerName);
            safeSetSessionStorage('customer_phone', getFullPhoneNumber());
            safeSetSessionStorage('lat', lat);
            safeSetSessionStorage('lng', lng);
            
            // Navigate to results
            window.location.href = '/results';
        });
        
        // GPS button
        document.getElementById('use-gps').addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }
            
            const btn = this;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Getting location...';
            btn.disabled = true;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    document.getElementById('lat').value = lat;
                    document.getElementById('lng').value = lng;
                    document.getElementById('delivery-location').value = 'Current Location (GPS)';
                    btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Location Set';
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-success');
                    btn.disabled = false;
                    
                    // Notify offline trucks in the area
                    notifyNearbyTrucks(lat, lng);
                },
                function(error) {
                    alert('Unable to get your location. Please enter it manually.');
                    btn.innerHTML = '<i class="bi bi-crosshairs me-2"></i>Use My Current Location';
                    btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    </script>
</body>
</html>
