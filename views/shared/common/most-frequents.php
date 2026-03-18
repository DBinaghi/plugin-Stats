<?php if (empty($result)): ?>
<p><?php echo __('None'); ?></p>
<?php else: ?>
<ol>
<?php foreach ($result as $position => $stat): ?>
    <li><?php
        if ($field === 'user_agent') {
            $raw   = htmlspecialchars($stat['user_agent_raw'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($stat['user_agent'],     ENT_QUOTES, 'UTF-8');
            $attrs = $stat['parsed']
                ? 'title="' . $raw . '"'
                : 'class="ua-unparsed" title="' . $raw . '" data-ua="' . $raw . '"';
            echo '<span ' . $attrs . '>' . $label . '</span> (' . __('%d views', (int)$stat['hits']) . ')';
        } else {
            echo __('%s (%d views)', $stat[$field], $stat['hits']);
        }
    ?></li>
<?php endforeach; ?>
</ol>

<?php if ($field === 'user_agent'): ?>
<script>
(function () {
    var spans = document.querySelectorAll('.ua-unparsed');
    if (!spans.length) return;

    var parseUrl = '<?php echo url('stats/ua-parser/parse'); ?>';
    var libUrl   = (window.StatsUAConfig && window.StatsUAConfig.scriptUrl)
                   ? window.StatsUAConfig.scriptUrl
                   : '<?php echo WEB_ROOT . "/plugins/Stats/libraries/ua-parser/ua-parser.min.js"; ?>';

    function formatUa(result, ua) {
        var browser = (result.browser.name    || '') + (result.browser.version ? ' ' + result.browser.version : '');
        var os      = (result.os.name         || '') + (result.os.version      ? ' ' + result.os.version      : '');
        var isBot   = /bot|crawl|spider|slurp|mediapartners/i.test(ua);
        var label   = browser || '<?php echo __('Unknown'); ?>';
        if (os)    label += ' [' + os + ']';
        if (isBot) label += ' (bot)';
        return { label: label, isBot: isBot };
    }

    function parseAndStore(span) {
        var ua     = span.getAttribute('data-ua');
        var parser = new UAParser(ua);
        var result = parser.getResult();
        var fmt    = formatUa(result, ua);

        span.textContent = fmt.label;
        span.title       = ua;
        span.classList.remove('ua-unparsed');

        // Memorizza in cache (best-effort)
        if (typeof fetch !== 'undefined') {
            fetch(parseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    user_agent: ua,
                    parsed: {
                        browser:         result.browser.name    || '',
                        browser_version: result.browser.version || '',
                        engine:          result.engine.name     || '',
                        engine_version:  result.engine.version  || '',
                        os:              result.os.name         || '',
                        os_version:      result.os.version      || '',
                        device_type:     result.device.type     || '',
                        device_vendor:   result.device.vendor   || '',
                        device_model:    result.device.model    || '',
                        is_bot:          fmt.isBot ? 1 : 0,
                    }
                })
            }).catch(function () {});
        }
    }

    function runParsing() {
        spans.forEach(function (span) { parseAndStore(span); });
    }

    if (typeof UAParser === 'function') {
        runParsing();
    } else {
        var s    = document.createElement('script');
        s.src    = libUrl;
        s.onload = runParsing;
        document.head.appendChild(s);
    }
}());
</script>
<?php endif; ?>

<?php endif; ?>
