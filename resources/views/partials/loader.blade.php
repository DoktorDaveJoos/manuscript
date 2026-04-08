<style>
    #loader-text {
        font-family: {{ $fontFamily ?? "'Playfair Display', Georgia, serif" }};
        font-size: 24px;
        font-weight: 500;
        color: #141414;
        letter-spacing: 0.02em;
    }
    html.dark #loader-text { color: #e8e5df; }
    #loader-caret {
        display: inline-block;
        width: 2px;
        height: 1.1em;
        background: #141414;
        margin-left: 2px;
        vertical-align: text-bottom;
        animation: caret-blink 0.8s step-end infinite;
    }
    html.dark #loader-caret { background: #e8e5df; }
    @keyframes caret-blink { 50% { opacity: 0; } }
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
        setTimeout(type, {{ $delay ?? 200 }});
    })();
</script>
