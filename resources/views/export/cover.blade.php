@php
    /** @var \App\Services\Export\CoverOptions $options */
    /** @var string $face */
    $face = $face ?? \App\Services\Export\CoverService::FACE_FRONT;

    $trim = $options->trim()->dimensions();
    $safety = $options->safety;
    // Height of the safe text area inside a single panel (trim minus the safety inset
    // on the top and bottom). Used to place the title block and the blurb consistently
    // whether a panel is rendered on its own or inside the wraparound jacket.
    $panelContentHeight = $trim['height'] - 2 * $safety;
    // Drop the title block into the upper-middle of the safe area; the blurb sits higher.
    $titleTop = round($panelContentHeight * 0.34, 1);
    // Inset the blurb down from the top of the safe area so it sits clear of the trim edge.
    $blurbTop = round($panelContentHeight * 0.11, 1);

    $titleFont = \App\Enums\FontPairing::ElegantSerif->headingFontKey(); // Cormorant Garamond
    $sansFont = \App\Enums\FontPairing::ModernMixed->headingFontKey(); // Source Sans 3
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            color: #000000;
            background-color: #ffffff;
        }
        .cover-author {
            font-family: {{ $sansFont }}, sans-serif;
            text-align: center;
            font-size: 12pt;
            font-weight: normal;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: #1a1a1a;
        }
        .cover-title-block {
            text-align: center;
        }
        .cover-title {
            font-family: {{ $titleFont }}, Georgia, serif;
            font-size: 36pt;
            font-weight: bold;
            line-height: 1.12;
            margin: 0;
            color: #000000;
        }
        .cover-subtitle {
            font-family: {{ $sansFont }}, sans-serif;
            font-size: 11pt;
            font-weight: normal;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            margin-top: 7mm;
            color: #6b6b6b;
        }
        .cover-blurb {
            font-family: {{ $sansFont }}, sans-serif;
            font-size: 9.5pt;
            font-weight: normal;
            line-height: 1.5;
            text-align: justify;
            color: #1a1a1a;
        }
        /* mPDF discards margins on block elements nested inside <td>, which collapsed the
           title and blurb to the top of each panel and made the printed jacket diverge from
           the single-face preview. The wraparound therefore lays its panels out with
           page-level absolutely-positioned blocks: each panel is its own block-flow context,
           so the partials' margins are honoured exactly as in the single-face renders. */
        .cover-panel-abs {
            position: absolute;
        }
    </style>
</head>
<body>
@if ($face === \App\Services\Export\CoverService::FACE_WRAPAROUND)
    @php
        // Distance from the page edge to the safe area (bleed off the cut line + safety off
        // the trim edge); matches CoverOptions::contentMargin() used for the single faces.
        $inset = $options->bleed + $safety;
        $panelWidth = $trim['width'] - 2 * $safety;
        $panelHeight = $trim['height'] - 2 * $safety;
        // The front panel sits after the back panel and the spine; its safe area starts a
        // further $safety in from the front panel's left edge.
        $frontLeft = $options->bleed + $trim['width'] + $options->spineWidth + $safety;
    @endphp
    <div class="cover-panel-abs" style="top: {{ $inset }}mm; left: {{ $inset }}mm; width: {{ $panelWidth }}mm; height: {{ $panelHeight }}mm;">
        @include('export.partials.cover-back', ['options' => $options, 'blurbTop' => $blurbTop])
    </div>
    <div class="cover-panel-abs" style="top: {{ $inset }}mm; left: {{ $frontLeft }}mm; width: {{ $panelWidth }}mm; height: {{ $panelHeight }}mm;">
        @include('export.partials.cover-front', ['options' => $options, 'titleTop' => $titleTop])
    </div>
@elseif ($face === \App\Services\Export\CoverService::FACE_BACK)
    @include('export.partials.cover-back', ['options' => $options, 'blurbTop' => $blurbTop])
@else
    @include('export.partials.cover-front', ['options' => $options, 'titleTop' => $titleTop])
@endif
</body>
</html>
