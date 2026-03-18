/**
 * stats-ua-tracker.js
 *
 * Loaded on every public page by Stats plugin (hookPublicHead).
 *
 * Flow:
 *  1. Load ua-parser-js (from CDN configured in plugin settings, or from
 *     the bundled copy under libraries/ua-parser/).
 *  2. Parse navigator.userAgent.
 *  3. POST the result to /stats/summary/parse so the server can cache it.
 *
 * The script is intentionally lightweight and deferred: it never blocks page
 * rendering and silently swallows any error.
 *
 * Global config object injected by PHP (see StatsPlugin::hookPublicHead):
 *
 *   window.StatsUAConfig = {
 *       parseUrl  : "/stats/ua-parser/parse",    // server endpoint
 *       scriptUrl : "https://..."               // ua-parser-js URL
 *   };
 */
(function () {
    'use strict';

    var config = window.StatsUAConfig || {};
    if (!config.parseUrl || !config.scriptUrl) {
        return; // plugin misconfigured, bail out silently
    }

    /**
     * Parse the UA with the loaded UAParser library and POST to the server.
     */
    function parseAndSend() {
        try {
            var parser  = new UAParser();
            var result  = parser.getResult();

            var browser = result.browser || {};
            var engine  = result.engine  || {};
            var os      = result.os      || {};
            var device  = result.device  || {};

            // Normalise device type: UAParser uses "mobile" | "tablet" |
            // "console" | "smarttv" | "wearable" | "embedded" | undefined.
            var deviceType = device.type || 'desktop';

            // Simple bot detection heuristic (ua-parser-js does not flag bots).
            var botPattern = /bot|crawler|spider|slurp|check_http|curl|wget|python|java\/|libwww/i;
            var isBot      = botPattern.test(navigator.userAgent) ? 1 : 0;

            var payload = {
                user_agent : navigator.userAgent,
                parsed     : {
                    browser         : browser.name    || '',
                    browser_version : browser.version || '',
                    engine          : engine.name     || '',
                    engine_version  : engine.version  || '',
                    os              : os.name         || '',
                    os_version      : os.version      || '',
                    device_type     : deviceType,
                    device_vendor   : device.vendor   || '',
                    device_model    : device.model    || '',
                    is_bot          : isBot
                }
            };

            // Use fetch if available, fall back to XHR.
            if (typeof fetch === 'function') {
                fetch(config.parseUrl, {
                    method      : 'POST',
                    headers     : { 'Content-Type': 'application/json' },
                    body        : JSON.stringify(payload),
                    credentials : 'same-origin'
                }).catch(function () { /* silent */ });
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', config.parseUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify(payload));
            }
        } catch (e) {
            // Never crash the page.
        }
    }

    /**
     * Dynamically load ua-parser-js then call the callback.
     *
     * @param {string}   src
     * @param {Function} callback
     */
    function loadScript(src, callback) {
        var s    = document.createElement('script');
        s.src    = src;
        s.async  = true;
        s.defer  = true;
        s.onload = callback;
        s.onerror = function () {
            // CDN failed — try the bundled fallback if we haven't already.
            if (src !== config.bundleUrl && config.bundleUrl) {
                loadScript(config.bundleUrl, callback);
            }
        };
        document.head.appendChild(s);
    }

    // Kick off after the page has loaded to avoid any impact on rendering.
    if (document.readyState === 'complete') {
        loadScript(config.scriptUrl, parseAndSend);
    } else {
        window.addEventListener('load', function () {
            loadScript(config.scriptUrl, parseAndSend);
        });
    }
}());
