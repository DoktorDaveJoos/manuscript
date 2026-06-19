{{--
    Static table of contents. Rendered like any other front-matter page (its own
    unnumbered `matter` page, no recto-forcing), so it never introduces blank
    versos. Folios come from the probe render (see PdfExporter::resolveTableOfContentsFolios);
    a <dottab> draws the dotted leader and right-aligns the page number. Reuses
    the templates' .toc-title and .mpdf_toc_* styling.
--}}
@php $tocFolios = $tocFolios ?? []; @endphp
<section class="matter-section">
    <p class="toc-title">{{ __('Table of Contents') }}</p>
    <div class="mpdf_toc">
        @foreach ($chapters as $tocIndex => $tocChapter)
            <div class="mpdf_toc_level_0"><span class="mpdf_toc_t_level_0">{{ $tocChapter->title }}</span>@if (($tocFolios[$tocIndex] ?? '') !== '')<dottab outdent="0" /><span class="mpdf_toc_p_level_0">{{ $tocFolios[$tocIndex] }}</span>@endif</div>
        @endforeach
    </div>
</section>
