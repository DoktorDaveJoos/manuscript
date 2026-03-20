<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @php
            $isEbook = $isEbookPreview ?? false;
            $trimSize = $options->trimSize ?? \App\Enums\TrimSize::UsTrade;
            $fontSize = $options->fontSize;
            $dimensions = $trimSize->dimensions();
            $margins = $trimSize->margins();
        @endphp

        @unless ($isEbook)
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
                color: #999999;
                font-style: italic;
            }
        }
        @@page chapter-{{ $index }} :right {
            @@top-right {
                content: "{{ cssEscape($chapter->title) }}";
                font-size: 8pt;
                color: #999999;
                font-style: italic;
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
        @endunless

        {{-- Template CSS (typography, colors, spacing) --}}
        {!! $css !!}

        @unless ($isEbook)
        .act-break {
            page: matter;
            break-before: page;
        }
        @endunless
    </style>
</head>
<body>
    @if (!($isEbook) && $options->showPageNumbers)
    <htmlpagefooter name="footerL" style="display:none">
        <div style="font-size: 8pt; color: #999999;">{PAGENO}</div>
    </htmlpagefooter>
    <htmlpagefooter name="footerR" style="display:none">
        <div style="font-size: 8pt; color: #999999; text-align: right;">{PAGENO}</div>
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

        <section class="chapter-section"@unless ($isEbook) style="page: chapter-{{ $index }};"@endunless>
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
            'acknowledgments' => 'Acknowledgments',
            'about-author' => 'About the Author',
        ];
        $backMatterTexts = [
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
