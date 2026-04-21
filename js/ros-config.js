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
                .catch(async err => {
                    console.error('[ROSConfig] Failed to fetch configuration:', err);
                    // Return fallback config based on browser location and runtime WS probing.
                    const cfg = await this._discoverWorkingFallbackConfig();
                    cached_config = cfg;
                    console.log('[ROSConfig] Using fallback configuration:', cfg);
                    return cfg;
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
            // Use whatever hostname the browser used to reach the dashboard.
            // This is the most reliable signal: if the browser could load the
            // page from this host, rosbridge on the same host is the best guess.
            let hostname = window.location.hostname;

            let is_https = window.location.protocol === 'https:';
            let scheme = is_https ? 'wss' : 'ws';

            return {
                vehicle_name: hostname.split('.')[0],
                robot_hostname: hostname,
                rosbridge_url: `${scheme}://${hostname}:9001/rosbridge_websocket`,
                rosbridge_host: hostname,
                rosbridge_port: 9001,
                rosbridge_scheme: scheme,
                timestamp: Math.floor(Date.now() / 1000),
                _is_fallback: true
            };
        },

        _getFallbackCandidates: function(baseCfg) {
            const scheme = baseCfg.rosbridge_scheme;
            const host = baseCfg.rosbridge_host;
            return [
                `${scheme}://${host}:9001`,
                `${scheme}://${host}:9001/rosbridge_websocket`,
                `${scheme}://${host}:9090`,
                `${scheme}://${host}:9090/rosbridge_websocket`
            ];
        },

        _probeWebSocketUrl: function(url, timeoutMs = 1500) {
            return new Promise((resolve) => {
                let done = false;
                let ws = null;
                const finish = (ok) => {
                    if (done) {
                        return;
                    }
                    done = true;
                    clearTimeout(timer);
                    try {
                        if (ws) {
                            ws.close();
                        }
                    } catch (e) {
                        // ignore close errors
                    }
                    resolve(ok);
                };

                const timer = setTimeout(() => finish(false), timeoutMs);

                try {
                    ws = new WebSocket(url);
                    ws.onopen = () => finish(true);
                    ws.onerror = () => finish(false);
                    ws.onclose = () => finish(false);
                } catch (e) {
                    finish(false);
                }
            });
        },

        _discoverWorkingFallbackConfig: async function() {
            const cfg = this._getFallbackConfig();
            const candidates = this._getFallbackCandidates(cfg);

            for (const url of candidates) {
                const ok = await this._probeWebSocketUrl(url);
                if (!ok) {
                    continue;
                }
                const parsed = new URL(url);
                cfg.rosbridge_url = url;
                cfg.rosbridge_host = parsed.hostname;
                cfg.rosbridge_port = Number(parsed.port || (parsed.protocol === 'wss:' ? 443 : 80));
                cfg._is_fallback = true;
                cfg._autodetected_ws = true;
                return cfg;
            }

            // No candidate proved reachable, keep existing fallback values.
            return cfg;
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
