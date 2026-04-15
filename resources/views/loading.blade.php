<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Manuscript</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <script>
            (function() {
                var t = 'system';
                try { t = localStorage.getItem('manuscript:theme') || 'system'; } catch(e) {}
                var d = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (d) document.documentElement.classList.add('dark');
            })();
        </script>
        <style>
            html, body {
                margin: 0;
                padding: 0;
                height: 100%;
                overflow: hidden;
                user-select: none;
                -webkit-user-select: none;
                font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            }
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                background: #fafafa;
            }
            html.dark body { background: #161616; }

            /* Design tokens: ink/ink-muted/ink-faint + accent (see docs/design-system.md) */
            #repair-panel {
                position: fixed;
                bottom: 48px;
                left: 50%;
                transform: translateX(-50%);
                display: none;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                max-width: 360px;
                padding: 16px 24px;
                text-align: center;
            }
            #repair-panel.visible { display: flex; }
            #repair-label {
                font-size: 11px;
                font-weight: 500;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #B87333;
            }
            html.dark #repair-label { color: #D4956A; }
            #repair-title {
                font-size: 13px;
                font-weight: 500;
                color: #141414;
            }
            html.dark #repair-title { color: #E8E5DF; }
            #repair-hint {
                font-size: 13px;
                color: #737373;
                line-height: 1.5;
            }
            html.dark #repair-hint { color: #9A958E; }

            #fallback-reload {
                position: fixed;
                bottom: 32px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 13px;
                color: #A3A3A3;
                display: none;
            }
            html.dark #fallback-reload { color: #6B665E; }
            #fallback-reload.visible { display: block; }
            #fallback-reload a {
                color: #737373;
                text-decoration: underline;
            }
            html.dark #fallback-reload a { color: #9A958E; }
        </style>
    </head>
    <body>
        <div>
            @include('partials.loader', ['delay' => 100, 'fontFamily' => 'Georgia, serif'])
        </div>

        <div id="repair-panel" role="status" aria-live="polite">
            <span id="repair-label">Restoring your data</span>
            <span id="repair-title">Please don't quit the app.</span>
            <span id="repair-hint">We detected a problem with your database and are recovering your work from a backup. This can take up to a minute.</span>
        </div>

        <div id="fallback-reload">
            Still loading&hellip; <a href="/">Reload</a>
        </div>

        <script>
            (function() {
                var INTERVAL = 300;
                var MAX_WAIT = 20000;
                // PHP's built-in server is single-threaded, so a /ready call
                // that's still pending after this threshold is almost always
                // blocked on auto-repair — show the repair UI as a fallback.
                var REPAIR_SUSPECTED_AFTER = 2500;
                var repairMode = false;

                function showRepairPanel() {
                    if (repairMode) return;
                    repairMode = true;
                    document.getElementById('repair-panel').classList.add('visible');
                    document.getElementById('fallback-reload').classList.remove('visible');
                }

                function showManualReload() {
                    if (repairMode) return;
                    document.getElementById('fallback-reload').classList.add('visible');
                }

                // One-shot pre-check: catches the case where a previous repair
                // was interrupted and the marker is still on disk.
                fetch('/repair-status', { cache: 'no-store' })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .then(function(data) {
                        if (data && data.state === 'repairing') { showRepairPanel(); }
                    })
                    .catch(function() { /* ignore */ });

                // Independent wall-clock timers — the /ready fetch may stay
                // pending (never resolving .then or .catch) while PHP is
                // blocked on repair, so we can't derive "still loading" from
                // the fetch state itself.
                setTimeout(showRepairPanel, REPAIR_SUSPECTED_AFTER);
                setTimeout(showManualReload, MAX_WAIT);

                function poll() {
                    fetch('/ready', { cache: 'no-store' })
                        .then(function(r) {
                            if (r.ok) { window.location.replace('/'); return; }
                            throw 0;
                        })
                        .catch(function() {
                            setTimeout(poll, INTERVAL);
                        });
                }

                setTimeout(poll, 100);
            })();
        </script>
    </body>
</html>
