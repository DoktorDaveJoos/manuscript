@php
    /** @var \App\Services\Export\CoverOptions $options */
    /** @var float $titleTop */
@endphp
@if ($options->author !== '')
    <div class="cover-author">{{ $options->author }}</div>
@endif
<div class="cover-title-block" style="margin-top: {{ $titleTop }}mm;">
    <div class="cover-title">{{ $options->title }}</div>
    @if ($options->subtitle !== '')
        <div class="cover-subtitle">{{ $options->subtitle }}</div>
    @endif
</div>
