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
                </style>
                @include('partials.loader', ['delay' => 200])
            </div>
        </div>
    </body>
</html>
