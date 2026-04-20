/**
 * ROS Configuration Runtime Discovery
 * 
 * Allows dashboard widgets to discover ROSbridge connection details at runtime
 * instead of relying on PHP echo'd hostname values. This makes the dashboard
 * proxy-agnostic and works correctly when the dashboard runs behind a bridge.
 * 
 * Usage:
 *   ROSConfig.init().then(cfg => {
 *       console.log('Robot:', cfg.vehicle_name);
 *       console.log('ROSbridge URL:', cfg.rosbridge_url);
 *       
 *       // Create ROSLIB connections using the discovered URL
 *       let ros = new ROSLIB.Ros({ url: cfg.rosbridge_url });
 *   });
 * 
 * Or with async/await:
 *   let cfg = await ROSConfig.init();
 *   let ros = new ROSLIB.Ros({ url: cfg.rosbridge_url });
 */

window.ROSConfig = (function() {
    let cached_config = null;
    let config_loading = null;
    
    return {
        /**
         * Initialize and fetch ROS configuration from server
         * Caches result for subsequent calls
         */
        init: async function() {
            // Return cached config if already loaded
            if (cached_config) {
                return cached_config;
            }
            
            // Return existing promise if already loading
            if (config_loading) {
                return config_loading;
            }
            
            // Fetch configuration from server
            config_loading = fetch('/api/ros-config')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`ROS config endpoint failed: ${response.status}`);
                    }
                    return response.json();
                })
                .then(cfg => {
                    // Cache and return
                    cached_config = cfg;
                    console.log('[ROSConfig] Discovered configuration:', cfg);
                    return cfg;
                })
                .catch(err => {
                    console.error('[ROSConfig] Failed to fetch configuration:', err);
                    // Return fallback config based on browser location
                    return this._getFallbackConfig();
                });
            
            return config_loading;
        },
        
        /**
         * Get cached configuration without fetching
         * Returns null if not yet cached
         */
        get: function() {
            return cached_config;
        },
        
        /**
         * Fallback configuration when server endpoint is unavailable
         * Derives robot name from browser URL (less reliable but works as backup)
         */
        _getFallbackConfig: function() {
            // Try to extract hostname from browser location
            let hostname = window.location.hostname;
            
            // Common fallback: if accessed via localhost/127.0.0.1, assume testdrone
            if (hostname === 'localhost' || hostname === '127.0.0.1') {
                hostname = 'testdrone.local';
            }
            
            // If no domain, append .local
            if (!hostname.includes('.')) {
                hostname = hostname + '.local';
            }
            
            let is_https = window.location.protocol === 'https:';
            let scheme = is_https ? 'wss' : 'ws';
            
            return {
                vehicle_name: hostname.split('.')[0],  // Extract before .local
                robot_hostname: hostname,
                rosbridge_url: `${scheme}://${hostname}:9090/rosbridge_websocket`,
                rosbridge_host: hostname,
                rosbridge_port: 9090,
                rosbridge_scheme: scheme,
                timestamp: Math.floor(Date.now() / 1000),
                _is_fallback: true
            };
        },
        
        /**
         * Create a ROSLIB.Ros connection using discovered configuration
         */
        createROS: async function() {
            let cfg = await this.init();
            return new ROSLIB.Ros({ url: cfg.rosbridge_url });
        }
    };
})();

// Auto-initialize configuration discovery when page loads
document.addEventListener('DOMContentLoaded', function() {
    ROSConfig.init().catch(err => {
        console.warn('[ROSConfig] Initialization warning:', err);
    });
});
