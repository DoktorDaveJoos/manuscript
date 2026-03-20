<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @php
            $trimSize = $options->trimSize ?? \App\Enums\TrimSize::UsTrade;
            $fontSize = $options->fontSize ?? 11;
            $dimensions = $trimSize->dimensions();
            $margins = $trimSize->margins();
            $dropCapSize = round($fontSize * 3.2);
            $contentPreparer = new \App\Services\Export\ContentPreparer();
        @endphp

        @if ($fontData ?? null)
        @font-face {
            font-family: "Spectral";
            src: url("data:font/ttf;base64,{{ $fontData['regular'] }}") format("truetype");
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Spectral";
            src: url("data:font/ttf;base64,{{ $fontData['italic'] }}") format("truetype");
            font-weight: normal;
            font-style: italic;
            font-display: swap;
        }
        @endif

        @@page {
            size: {{ $dimensions['width'] }}mm {{ $dimensions['height'] }}mm;
        }

        @@page :left {
            margin: {{ $margins['top'] }}mm {{ $margins['outer'] }}mm {{ $margins['bottom'] }}mm {{ $margins['gutter'] }}mm;
        }

        @@page :right {
            margin: {{ $margins['top'] }}mm {{ $margins['gutter'] }}mm {{ $margins['bottom'] }}mm {{ $margins['outer'] }}mm;
        }

        @if ($options->showPageNumbers)
        @@page {
            odd-footer-name: footerR;
            even-footer-name: footerL;
        }
        @endif

        {{-- Per-chapter named pages with running headers --}}
        @foreach ($chapters as $index => $chapter)
        @@page chapter-{{ $index }} :left {
            @@top-left {
                content: "{{ cssEscape($book->title) }}";
                font-size: 8pt;
                color: #B5B5B5;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }
        }
        @@page chapter-{{ $index }} :right {
            @@top-right {
                content: "{{ cssEscape($chapter->title) }}";
                font-size: 8pt;
                color: #B5B5B5;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }
        }
        @@page chapter-{{ $index }}:first {
            @@top-left { content: none; }
            @@top-right { content: none; }
        }
        @if ($options->showPageNumbers)
        @@page chapter-{{ $index }} {
            odd-footer-name: footerR;
            even-footer-name: footerL;
        }
        @endif
        @endforeach

        {{-- Matter pages suppress all headers and footers --}}
        @@page matter {
            odd-footer-name: _blank;
            even-footer-name: _blank;
            odd-header-name: _blank;
            even-header-name: _blank;
        }

        body {
            font-family: "Spectral", Georgia, serif;
            font-size: {{ $fontSize }}pt;
            line-height: 1.5;
            text-align: justify;
            color: #4A4A4A;
        }

        .chapter-label {
            font-size: 0.65em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #B5B5B5;
            margin: 2em 0 0.25em;
            text-indent: 0;
        }

        h1 {
            font-size: 1.6em;
            font-weight: normal;
            text-align: center;
            margin: 0 0 1.5em;
            color: #1a1a1a;
        }

        .act-break {
            font-size: 1.6em;
            font-weight: bold;
            text-align: center;
            margin: 3em 0 1em;
            color: #1a1a1a;
            page: matter;
            break-before: page;
        }

        p {
            margin: 0;
            text-indent: 1.5em;
            widows: 2;
            orphans: 2;
        }

        p:first-child,
        .scene-break + p,
        h1 + p,
        .act-break + p {
            text-indent: 0;
        }

        .drop-cap {
            float: left;
            font-size: {{ $dropCapSize }}pt;
            line-height: 0.8;
            margin: 2pt 3pt 0 0;
            color: #1a1a1a;
        }

        .scene-break {
            text-align: center;
            letter-spacing: 0.3em;
            color: #B5B5B5;
            margin: 1.5em 0;
            text-indent: 0;
        }

        .toc-title {
            font-size: 1.8em;
            font-weight: bold;
            text-align: center;
            margin: 2em 0 1.5em;
            color: #1a1a1a;
        }

        .toc-entry {
            margin: 0.3em 0;
            text-indent: 0;
        }

        .toc-entry a {
            text-decoration: none;
            color: inherit;
        }

        .matter-title {
            font-size: 0.65em;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #B5B5B5;
            margin: 2em 0 1.5em;
            text-indent: 0;
        }

        .matter-body {
            text-indent: 0;
            margin: 0 0 0.5em;
        }

        .title-page-title {
            font-size: 2em;
            font-weight: normal;
            text-align: center;
            color: #1a1a1a;
            margin: 0;
            text-indent: 0;
        }

        .title-page-author {
            font-size: 0.85em;
            text-align: center;
            color: #B5B5B5;
            margin: 0.5em 0 0;
            text-indent: 0;
        }

        .copyright-text {
            font-size: 0.75em;
            text-align: center;
            color: #999;
            text-indent: 0;
            margin: 0 0 0.3em;
        }

        .dedication-text {
            font-style: italic;
            text-align: center;
            color: #4A4A4A;
            text-indent: 0;
        }

        .matter-section {
            page: matter;
            break-before: page;
        }

        .chapter-section {
            break-before: page;
        }
    </style>
</head>
<body>
    @if ($options->showPageNumbers)
    <htmlpagefooter name="footerL" style="display:none">
        <div style="font-size: 8pt; color: #B5B5B5;">{PAGENO}</div>
    </htmlpagefooter>
    <htmlpagefooter name="footerR" style="display:none">
        <div style="font-size: 8pt; color: #B5B5B5; text-align: right;">{PAGENO}</div>
    </htmlpagefooter>
    @endif

    {{-- Front Matter --}}
    @foreach ($options->frontMatter as $item)
        @if ($item === 'title-page')
            <section class="matter-section" style="text-align: center; padding-top: 35%;">
                <p class="title-page-title">{{ $book->title }}</p>
                @if ($book->author)
                    <p class="title-page-author">{{ $book->author }}</p>
                @endif
            </section>
        @endif

        @if ($item === 'copyright')
            <section class="matter-section" style="padding-top: 60%;">
                <p class="copyright-text">Copyright &copy; {{ date('Y') }}</p>
                <p class="copyright-text">{{ $book->title }}</p>
                <p class="copyright-text">All rights reserved.</p>
            </section>
        @endif

        @if ($item === 'dedication')
            <section class="matter-section" style="text-align: center; padding-top: 30%;">
                @php
                    $dedicationContent = $contentPreparer->toMatterHtml($options->dedicationText);
                    if ($dedicationContent === '') {
                        $dedicationContent = '<p class="dedication-text"></p>';
                    } else {
                        $dedicationContent = str_replace('class="matter-body"', 'class="dedication-text"', $dedicationContent);
                    }
                @endphp
                {!! $dedicationContent !!}
            </section>
        @endif

        @if ($item === 'toc')
            <section class="matter-section">
                <p class="toc-title">Table of Contents</p>
                @foreach ($chapters as $tocIndex => $tocChapter)
                    <p class="toc-entry"><a href="#chapter-{{ $tocIndex }}">{{ $tocChapter->title }}</a></p>
                @endforeach
            </section>
        @endif
    @endforeach

    {{-- Table of Contents (standalone, not in front matter) --}}
    @if ($options->includeTableOfContents && $chapters->isNotEmpty() && ! in_array('toc', $options->frontMatter))
        <section class="matter-section">
            <p class="toc-title">Table of Contents</p>
            @foreach ($chapters as $tocIndex => $tocChapter)
                <p class="toc-entry"><a href="#chapter-{{ $tocIndex }}">{{ $tocChapter->title }}</a></p>
            @endforeach
        </section>
    @endif

    {{-- Chapters --}}
    @php $currentActId = null; @endphp
    @foreach ($chapters as $index => $chapter)
        @if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId)
            @php $currentActId = $chapter->act_id; @endphp
            <div class="act-break">{{ $chapter->act?->title ?? "Act {$chapter->act?->number}" }}</div>
        @endif

        <section class="chapter-section" style="page: chapter-{{ $index }};">
            @if ($options->includeChapterTitles)
                <p class="chapter-label" id="chapter-{{ $index }}">Chapter {{ $index + 1 }}</p>
                <h1>{{ $chapter->title }}</h1>
            @endif

            {!! $chapter->prepared_content !!}
        </section>
    @endforeach

    {{-- Back Matter --}}
    @php
        $backMatterHeadings = [
            'also-by' => 'Also By',
            'acknowledgments' => 'Acknowledgments',
            'about-author' => 'About the Author',
        ];
        $backMatterTexts = [
            'also-by' => $options->alsoByText,
            'acknowledgments' => $options->acknowledgmentText,
            'about-author' => $options->aboutAuthorText,
        ];
    @endphp
    @foreach ($options->backMatter as $item)
        @if (isset($backMatterHeadings[$item]))
            <section class="matter-section">
                <p class="matter-title">{{ $backMatterHeadings[$item] }}</p>
                {!! $contentPreparer->toMatterHtml($backMatterTexts[$item]) !!}
            </section>
        @endif
    @endforeach
</body>
</html>
