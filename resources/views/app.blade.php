<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <script>
            (function() {
                var t = 'system';
                try { t = localStorage.getItem('manuscript:theme') || 'system'; } catch(e) {}
                var d = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (d) document.documentElement.classList.add('dark');
            })();
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=eb-garamond:400,500,600&playfair-display:400,500,600&geist:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <div id="app" data-page="{{ json_encode($page) }}">
            <div id="app-loader">
                <style>
                    #app-loader {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        background: #fafafa;
                        user-select: none;
                        -webkit-user-select: none;
                    }
                    html.dark #app-loader {
                        background: #161616;
                    }
                    #loader-text {
                        font-family: 'Playfair Display', Georgia, serif;
                        font-size: 24px;
                        font-weight: 500;
                        color: #141414;
                        letter-spacing: 0.02em;
                    }
                    html.dark #loader-text {
                        color: #e8e5df;
                    }
                    #loader-caret {
                        display: inline-block;
                        width: 2px;
                        height: 1.1em;
                        background: #141414;
                        margin-left: 2px;
                        vertical-align: text-bottom;
                        animation: caret-blink 0.8s step-end infinite;
                    }
                    html.dark #loader-caret {
                        background: #e8e5df;
                    }
                    @keyframes caret-blink {
                        50% { opacity: 0; }
                    }
                </style>
                <span id="loader-text"></span><span id="loader-caret"></span>
                <script>
                    (function() {
                        var text = 'Loading Manuscript';
                        var el = document.getElementById('loader-text');
                        var i = 0;
                        function type() {
                            if (i < text.length && el.isConnected) {
                                el.textContent += text[i];
                                i++;
                                setTimeout(type, 60 + Math.random() * 40);
                            }
                        }
                        setTimeout(type, 200);
                    })();
                </script>
            </div>
        </div>
    </body>
</html>
