/**
 * SEO Injection Script
 * Dynamically injects meta tags, Open Graph, Twitter Cards, and Schema.org data
 * using configuration from /api/config
 */

(function() {
    'use strict';

    /**
     * Inject SEO meta tags based on page type
     * @param {string} pageType - 'customer' or 'truck'
     */
    async function injectSEO(pageType = 'customer') {
        try {
            const response = await fetch('/api/config');
            const result = await response.json();
            
            if (!result.success || !result.data) {
                console.log('SEO: Could not load config');
                return;
            }
            
            const config = result.data;
            const head = document.head;
            
            // Select description and keywords based on page type
            const description = pageType === 'truck' 
                ? (config.seo_truck_description || config.seo_description)
                : config.seo_description;
            
            const keywords = pageType === 'truck'
                ? (config.seo_truck_keywords || config.seo_keywords)
                : config.seo_keywords;
            
            const pageTitle = document.title || config.app_name;
            const siteUrl = config.url || window.location.origin;
            const logoUrl = config.logo ? (siteUrl + config.logo) : '';
            const currentUrl = siteUrl + window.location.pathname;
            
            // Helper to create and append meta tags
            function addMeta(name, content, isProperty = false) {
                if (!content) return;
                const meta = document.createElement('meta');
                meta.setAttribute(isProperty ? 'property' : 'name', name);
                meta.setAttribute('content', content);
                head.appendChild(meta);
            }
            
            // Helper to create link tags
            function addLink(rel, href) {
                if (!href) return;
                const link = document.createElement('link');
                link.setAttribute('rel', rel);
                link.setAttribute('href', href);
                head.appendChild(link);
            }
            
            // Robots directive - block indexing if not discoverable
            if (!config.seo_discoverable) {
                addMeta('robots', 'noindex, nofollow');
            }
            
            // Basic SEO meta tags
            addMeta('description', description);
            addMeta('keywords', keywords);
            addMeta('author', config.app_name);
            
            // Canonical URL
            addLink('canonical', currentUrl);
            
            // Geo meta tags for local SEO
            if (config.country_name) {
                addMeta('geo.region', 'TT'); // Trinidad and Tobago
                addMeta('geo.placename', config.country_name);
            }
            
            // Open Graph tags
            addMeta('og:type', 'website', true);
            addMeta('og:title', pageTitle, true);
            addMeta('og:description', description, true);
            addMeta('og:url', currentUrl, true);
            addMeta('og:site_name', config.app_name, true);
            addMeta('og:locale', 'en_TT', true);
            if (logoUrl) {
                addMeta('og:image', logoUrl, true);
                addMeta('og:image:alt', config.app_name + ' logo', true);
            }
            
            // Twitter Card tags
            addMeta('twitter:card', 'summary_large_image');
            addMeta('twitter:title', pageTitle);
            addMeta('twitter:description', description);
            if (logoUrl) {
                addMeta('twitter:image', logoUrl);
                addMeta('twitter:image:alt', config.app_name + ' logo');
            }
            
            // Schema.org JSON-LD structured data
            const schemaData = {
                '@context': 'https://schema.org',
                '@type': 'LocalBusiness',
                'name': config.app_name,
                'description': config.seo_description,
                'url': siteUrl,
                'areaServed': {
                    '@type': 'Country',
                    'name': config.country_name || 'Trinidad and Tobago'
                },
                'serviceType': 'Water Delivery'
            };
            
            if (logoUrl) {
                schemaData.logo = logoUrl;
                schemaData.image = logoUrl;
            }
            
            // Add Service schema for truck drivers page
            if (pageType === 'truck') {
                schemaData['@type'] = 'Organization';
                schemaData.description = config.seo_truck_description;
                schemaData.makesOffer = {
                    '@type': 'Offer',
                    'itemOffered': {
                        '@type': 'Service',
                        'name': 'Water Truck Driver Partnership',
                        'description': config.seo_truck_description
                    }
                };
            }
            
            const script = document.createElement('script');
            script.type = 'application/ld+json';
            script.textContent = JSON.stringify(schemaData);
            head.appendChild(script);
            
            // Update HTML lang attribute for Trinidad locale
            document.documentElement.setAttribute('lang', 'en-TT');
            
            console.log('SEO: Meta tags injected successfully');
            
        } catch (error) {
            console.log('SEO: Error injecting meta tags', error);
        }
    }
    
    // Expose to global scope
    window.injectSEO = injectSEO;
})();
