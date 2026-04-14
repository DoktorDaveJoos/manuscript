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
            }
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                background: #fafafa;
            }
            html.dark body { background: #161616; }
        </style>
    </head>
    <body>
        <div>
            @include('partials.loader', ['delay' => 100, 'fontFamily' => 'Georgia, serif'])
        </div>
        <script>
            // Poll the health endpoint until Laravel is ready, then redirect.
            // This replaces a fixed 600ms delay which could miss slow startups
            // (migrations, cache warmup on first launch).
            (function() {
                var elapsed = 0;
                var MAX_WAIT = 20000;
                var INTERVAL = 300;
                var msgEl = null;

                function showManualReload() {
                    if (msgEl) return;
                    msgEl = document.createElement('div');
                    msgEl.style.cssText = 'position:fixed;bottom:32px;left:0;right:0;text-align:center;font-family:system-ui,sans-serif;font-size:13px;color:#888;';
                    msgEl.innerHTML = 'Still loading\u2026 <a href="/" style="color:#aaa;text-decoration:underline;">Reload</a>';
                    document.body.appendChild(msgEl);
                }

                function poll() {
                    fetch('/up', { cache: 'no-store' })
                        .then(function(r) {
                            if (r.ok) { window.location.replace('/'); return; }
                            throw 0;
                        })
                        .catch(function() {
                            elapsed += INTERVAL;
                            if (elapsed >= MAX_WAIT) { showManualReload(); }
                            setTimeout(poll, INTERVAL);
                        });
                }

                // Give the loading animation a moment to start before first poll
                setTimeout(poll, 100);
            })();
        </script>
    </body>
</html>
