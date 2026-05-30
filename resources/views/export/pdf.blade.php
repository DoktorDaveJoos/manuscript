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

        {{-- Per-chapter named pages carry the body folio. Footer names must live on
             the base @@page rule — mPDF silently drops the footer when :left / :right
             pseudo-pages are used. Front and back matter use the `matter` page (below)
             and therefore stay unnumbered, the way a printed book is set. --}}
        @foreach ($chapters as $index => $chapter)
        @@page chapter-{{ $index }} {
            @if ($options->showPageNumbers)
            odd-footer-name: pgnum;
            even-footer-name: pgnum;
            @endif
        }
        @endforeach

        {{-- Numbered matter (prologue / epilogue): carries the body folio so the
             page count opens on the prologue and runs on through the epilogue. --}}
        @@page numbered {
            @if ($options->showPageNumbers)
            odd-footer-name: pgnum;
            even-footer-name: pgnum;
            @endif
        }

        {{-- Plain matter pages (title, copyright, …) suppress folios --}}
        @@page matter {
            odd-footer-name: _blank;
            even-footer-name: _blank;
        }
        @endunless

        {{-- Template CSS (typography, colors, spacing) --}}
        {!! $css !!}

        @unless ($isEbook)
        .act-break {
            page: matter;
            page-break-before: always;
            break-before: page;
        }
        {{-- The first numbered page (prologue or first body chapter) starts via an
             explicit <pagebreak> that also resets the page number to 1, so the
             section itself must not break again. --}}
        .chapter-section--continue,
        .matter-section--continue {
            page-break-before: avoid;
            break-before: avoid;
        }
        @endunless
    </style>
</head>
<body>
    @unless ($isEbook)
    @php $tokens = $tokens ?? $template->designTokens(); @endphp
    @if ($options->showPageNumbers)
    {{-- Centered folio. Only body (chapter) pages reference it; matter pages don't. --}}
    <htmlpagefooter name="pgnum" style="display:none">
        <div style="font-size: {{ $tokens['pageNumberSizePt'] }}pt; color: {{ $tokens['pageNumberColor'] }}; text-align: center;">{PAGENO}</div>
    </htmlpagefooter>
    @endif
    @endunless

    @php
        $renderedFrontContent = false;
        // True once the prologue has opened the numbered region, so the first body
        // chapter continues the count instead of resetting it back to 1.
        $numberingStartedAtPrologue = false;
    @endphp

    {{-- Cover Image --}}
    @if ($options->includeCover && $options->coverImagePath)
        @php
            $coverAbsPath = \Illuminate\Support\Facades\Storage::disk('local')->path($options->coverImagePath);
        @endphp
        @if (file_exists($coverAbsPath))
            @php $renderedFrontContent = true; @endphp
            <section class="matter-section" style="text-align: center; padding: 0; margin: 0;">
                <img src="{{ $coverAbsPath }}" style="max-width: 100%; max-height: 100%;" />
            </section>
        @endif
    @endif

    {{-- Front Matter --}}
    @foreach ($options->frontMatter as $item)
        @if ($item === 'title-page')
            @php $renderedFrontContent = true; @endphp
            <section class="matter-section" style="text-align: center; padding-top: 35%;">
                <p class="title-page-title">{{ $book->title }}</p>
                @if ($book->author)
                    <p class="title-page-author">{{ $book->author }}</p>
                @endif
            </section>
        @endif

        @if ($item === 'copyright')
            @php $renderedFrontContent = true; @endphp
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
            @php $renderedFrontContent = true; @endphp
            <section class="matter-section" style="padding-top: 30%; text-align: center;">
                <p class="dedication-text">{{ $options->dedicationText }}</p>
            </section>
        @endif

        @if ($item === 'epigraph' && $options->epigraphText !== '')
            @php $renderedFrontContent = true; @endphp
            <section class="matter-section" style="padding-top: 30%; text-align: center;">
                <p style="font-style: italic;">{{ $options->epigraphText }}</p>
                @if ($options->epigraphAttribution !== '')
                    <p style="margin-top: 0.5em; font-size: 0.9em;">{{ $options->epigraphAttribution }}</p>
                @endif
            </section>
        @endif

        @if ($item === 'prologue')
            @php
                $prologueChapter = \App\Services\Export\ExportService::resolvePrologueChapter($book);
            @endphp
            @if ($prologueChapter)
                @php
                    // The prologue opens the numbered region. Reset the folio to 1 and
                    // land directly on the numbered page when matter precedes it, so we
                    // neither inherit the front-matter page count nor eject a blank page.
                    $prologueNumbered = ! $isEbook && $options->showPageNumbers;
                    $resetAtPrologue = $prologueNumbered && $renderedFrontContent;
                    $numberingStartedAtPrologue = $prologueNumbered;
                @endphp
                @if ($resetAtPrologue)
                    <pagebreak page-selector="numbered" resetpagenum="1" />
                @endif
                @php $renderedFrontContent = true; @endphp
                <section class="matter-section{{ $resetAtPrologue ? ' matter-section--continue' : '' }}"@if ($prologueNumbered) style="page: numbered;"@endif>
                    <p class="chapter-label">{{ __('Prologue') }}</p>
                    @php
                        $sceneBreak = $options->sceneBreakStyle ?? $template->defaultSceneBreakStyle();
                        $prologueContent = '';
                        foreach ($prologueChapter->scenes as $si => $scene) {
                            if ($si > 0) {
                                $prologueContent .= $sceneBreak->html();
                            }
                            $prologueContent .= $contentPreparer->toChapterHtml($scene->content ?? '', $sceneBreak);
                        }
                        if ($options->dropCaps) {
                            $prologueContent = $contentPreparer->addDropCap($prologueContent);
                        }
                    @endphp
                    {!! $prologueContent !!}
                </section>
            @endif
        @endif

        @if ($item === 'toc')
            @php $renderedFrontContent = true; @endphp
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
        @php $renderedFrontContent = true; @endphp
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
        @php $emittedActBreak = false; @endphp
        @if ($options->includeActBreaks && $chapter->act_id && $chapter->act_id !== $currentActId)
            @php $currentActId = $chapter->act_id; @endphp
            <div class="act-break">{{ $chapter->act?->title ?? "Act {$chapter->act?->number}" }}</div>
            @php $emittedActBreak = true; @endphp
        @endif

        @php
            // Restart arabic numbering at 1 on the first body page whenever anything
            // (front matter or an act break) precedes it — unless the prologue already
            // opened the numbered region, in which case the body continues the count.
            // The page-selector lands us directly on the chapter page so the section
            // does not force a second (blank) page — hence the --continue modifier.
            $resetHere = $index === 0 && ! $isEbook && ! $numberingStartedAtPrologue && ($renderedFrontContent || $emittedActBreak);
        @endphp
        @if ($resetHere)
            <pagebreak page-selector="chapter-{{ $index }}" resetpagenum="1" />
        @endif

        <section class="chapter-section{{ $resetHere ? ' chapter-section--continue' : '' }}"@unless ($isEbook) style="page: chapter-{{ $index }};"@endunless>
            @if ($options->chapterHeading->showsNumber())
                {!! $template->chapterHeaderHtml($index, $chapter->title, $book->language ?? config('app.fallback_locale', 'en'), $options->chapterHeading->showsTitle()) !!}
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
                @php $epilogueNumbered = ! $isEbook && $options->showPageNumbers; @endphp
                <section class="matter-section"@if ($epilogueNumbered) style="page: numbered;"@endif>
                    <p class="chapter-label">{{ __('Epilogue') }}</p>
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
