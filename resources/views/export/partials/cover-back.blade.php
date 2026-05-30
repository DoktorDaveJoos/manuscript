@php
    /** @var \App\Services\Export\CoverOptions $options */
    /** @var float $blurbTop */
    /** @var float $blurbSide */
    $blurbSide = $blurbSide ?? 0;
@endphp
@if (trim($options->blurb) !== '')
    <div class="cover-blurb" style="margin: {{ $blurbTop }}mm {{ $blurbSide }}mm 0 {{ $blurbSide }}mm;">{!! nl2br(e($options->blurb)) !!}</div>
@endif
