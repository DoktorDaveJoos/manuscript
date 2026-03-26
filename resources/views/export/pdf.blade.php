<!DOCTYPE html>
<html lang="{{ $book->language ?? config('app.fallback_locale', 'en') }}">
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
        @php $tokens = $template->designTokens(); @endphp
        @foreach ($chapters as $index => $chapter)
        @@page chapter-{{ $index }} :left {
            @@top-left {
                content: "{{ cssEscape($book->title) }}";
                font-size: {{ $tokens['runningHeaderSizePt'] }}pt;
                color: {{ $tokens['runningHeaderColor'] }};
                font-style: {{ $tokens['runningHeaderStyle'] }};
            }
        }
        @@page chapter-{{ $index }} :right {
            @@top-right {
                content: "{{ cssEscape($chapter->title) }}";
                font-size: {{ $tokens['runningHeaderSizePt'] }}pt;
                color: {{ $tokens['runningHeaderColor'] }};
                font-style: {{ $tokens['runningHeaderStyle'] }};
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
    @php $tokens = $tokens ?? $template->designTokens(); @endphp
    <htmlpagefooter name="footerL" style="display:none">
        <div style="font-size: {{ $tokens['pageNumberSizePt'] }}pt; color: {{ $tokens['pageNumberColor'] }};">{PAGENO}</div>
    </htmlpagefooter>
    <htmlpagefooter name="footerR" style="display:none">
        <div style="font-size: {{ $tokens['pageNumberSizePt'] }}pt; color: {{ $tokens['pageNumberColor'] }}; text-align: right;">{PAGENO}</div>
    </htmlpagefooter>
    @endif

    {{-- Cover Image --}}
    @if ($options->includeCover && $options->coverImagePath)
        @php
            $coverAbsPath = \Illuminate\Support\Facades\Storage::disk('local')->path($options->coverImagePath);
        @endphp
        @if (file_exists($coverAbsPath))
            <section class="matter-section" style="text-align: center; padding: 0; margin: 0;">
                <img src="{{ $coverAbsPath }}" style="max-width: 100%; max-height: 100%;" />
            </section>
        @endif
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
                @if ($options->copyrightText !== '')
                    {!! $contentPreparer->toMatterHtml($options->copyrightText, 'copyright-text') !!}
                @else
                    <p class="copyright-text">Copyright &copy; {{ date('Y') }}</p>
                    <p class="copyright-text">{{ $book->title }}</p>
                    <p class="copyright-text">All rights reserved.</p>
                @endif
            </section>
        @endif

        @if ($item === 'dedication' && $options->dedicationText !== '')
            <section class="matter-section" style="padding-top: 30%; text-align: center;">
                <p class="dedication-text">{{ $options->dedicationText }}</p>
            </section>
        @endif

        @if ($item === 'epigraph' && $options->epigraphText !== '')
            <section class="matter-section" style="padding-top: 30%; text-align: center;">
                <p style="font-style: italic;">{{ $options->epigraphText }}</p>
                @if ($options->epigraphAttribution !== '')
                    <p style="margin-top: 0.5em; font-size: 0.9em;">{{ $options->epigraphAttribution }}</p>
                @endif
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
                {!! $template->chapterHeaderHtml($index, $chapter->title, $book->language ?? config('app.fallback_locale', 'en')) !!}
            @endif

            {!! $chapter->prepared_content !!}
        </section>
    @endforeach

    {{-- Back Matter --}}
    @php
        $backMatterHeadings = [
            'acknowledgments' => 'Acknowledgments',
            'about-author' => 'About the Author',
            'also-by' => 'Also By ' . $book->author,
        ];
        $backMatterTexts = [
            'acknowledgments' => $options->acknowledgmentText,
            'about-author' => $options->aboutAuthorText,
            'also-by' => $options->alsoByText,
        ];
    @endphp
    @foreach ($options->backMatter as $item)
        @if ($item === 'epilogue')
            @php
                $epilogueChapter = \App\Services\Export\ExportService::resolveEpilogueChapter($book);
            @endphp
            @if ($epilogueChapter)
                <section class="matter-section">
                    <p class="chapter-label">Epilogue</p>
                    <h1>{{ $epilogueChapter->title }}</h1>
                    @php
                        $sceneBreak = $options->sceneBreakStyle ?? $template->defaultSceneBreakStyle();
                        $epilogueContent = '';
                        foreach ($epilogueChapter->scenes as $si => $scene) {
                            if ($si > 0) {
                                $epilogueContent .= $sceneBreak->html();
                            }
                            $epilogueContent .= $contentPreparer->toChapterHtml($scene->content ?? '', $sceneBreak);
                        }
                        if ($options->dropCaps) {
                            $epilogueContent = $contentPreparer->addDropCap($epilogueContent);
                        }
                    @endphp
                    {!! $epilogueContent !!}
                </section>
            @endif
        @elseif (isset($backMatterHeadings[$item]))
            <section class="matter-section">
                <p class="matter-title">{{ $backMatterHeadings[$item] }}</p>
                {!! $contentPreparer->toMatterHtml($backMatterTexts[$item] ?? '') !!}
            </section>
        @endif
    @endforeach
</body>
</html>
