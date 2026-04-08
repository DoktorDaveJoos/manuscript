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
            // Hand off to the real entry point after the typing animation has
            // had a moment to start, so users always see *something* even when
            // the '/' route is near-instant.
            setTimeout(function() { window.location.replace('/'); }, 600);
        </script>
    </body>
</html>
