<?php

use App\Models\DesignTemplate;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;

it('persists a design template with settings json', function () {
    $template = DesignTemplate::factory()->create([
        'name' => 'Classic (Custom)',
        'based_on' => 'classic',
    ]);

    expect($template->fresh()->settings)->toBeArray()
        ->and($template->fresh()->settings['page']['trim_size'])->not->toBeNull();
});

it('exposes design settings on every built-in template', function (object $template) {
    $settings = $template->designSettings();

    expect($settings)->toHaveKeys(['page', 'typography', 'headings', 'structure'])
        ->and($settings['page'])->toHaveKeys(['trim_size', 'bleed', 'bleed_mode', 'margin_top', 'margin_bottom', 'margin_inner', 'margin_outer'])
        ->and($settings['typography'])->toHaveKeys(['font_pairing', 'font_size', 'line_height', 'alignment', 'hyphenation', 'first_line_indent'])
        ->and($settings['headings'])->toHaveKeys(['chapter_heading', 'drop_caps', 'scene_break_style', 'heading_top_space_em'])
        ->and($settings['structure'])->toHaveKeys(['show_page_numbers', 'include_act_breaks']);
})->with([
    'classic' => [new ClassicTemplate],
    'modern' => [new ModernTemplate],
    'elegant' => [new ElegantTemplate],
]);
