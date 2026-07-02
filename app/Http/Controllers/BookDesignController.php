<?php

namespace App\Http\Controllers;

use App\Enums\ChapterHeading;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;
use App\Enums\TrimSize;
use App\Models\Book;
use App\Models\DesignTemplate;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookDesignController extends Controller
{
    /**
     * The Book Designer: typesetting templates with a live two-page preview.
     */
    public function show(Book $book): Response
    {
        $builtIns = collect([new ClassicTemplate, new ModernTemplate, new ElegantTemplate]);

        return Inertia::render('books/design', [
            'book' => $book->only('id', 'title', 'author'),
            'builtInTemplates' => $builtIns->map(fn ($t) => [
                'slug' => $t->slug(),
                'name' => $t->name(),
                'settings' => $t->designSettings(),
            ]),
            'customTemplates' => DesignTemplate::query()
                ->orderBy('name')
                ->get()
                ->map(fn (DesignTemplate $t) => [
                    'id' => $t->id,
                    'slug' => $t->slug(),
                    'name' => $t->name,
                    'basedOn' => $t->based_on,
                    'settings' => $t->settings,
                ]),
            'currentTemplate' => (string) ($book->export_settings['template'] ?? 'classic'),
            'trimSizes' => collect(TrimSize::cases())->map(function (TrimSize $t) {
                $dims = $t->dimensions();
                $margins = $t->margins();

                return [
                    'value' => $t->value,
                    'label' => $t->label(),
                    'labelMetric' => $t->metricLabel(),
                    'width' => $dims['width'],
                    'height' => $dims['height'],
                    'margins' => $margins,
                ];
            }),
            'fontPairings' => collect(FontPairing::cases())->map(fn (FontPairing $fp) => [
                'value' => $fp->value,
                'label' => $fp->label(),
                'headingFont' => $fp->headingFont(),
                'bodyFont' => $fp->bodyFont(),
            ]),
            'sceneBreakStyles' => collect(SceneBreakStyle::cases())->map(fn (SceneBreakStyle $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(Request $request, Book $book): JsonResponse
    {
        $validated = $this->validateTemplate($request);

        $template = DesignTemplate::create($validated);

        return response()->json([
            'id' => $template->id,
            'slug' => $template->slug(),
            'name' => $template->name,
            'basedOn' => $template->based_on,
            'settings' => $template->settings,
        ], 201);
    }

    public function update(Request $request, Book $book, DesignTemplate $template): JsonResponse
    {
        $validated = $this->validateTemplate($request, requireBasedOn: false);

        $template->update($validated);

        return response()->json(['message' => __('Template saved.')]);
    }

    /**
     * Set the book's export template, preserving the rest of the saved
     * export settings snapshot.
     */
    public function apply(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'template' => ['required', 'string', 'regex:/^(classic|modern|elegant|romance|custom:\d+)$/'],
        ]);

        $settings = $book->export_settings ?? [];
        $settings['template'] = $validated['template'];
        $book->update(['export_settings' => $settings]);

        return response()->json(['message' => __('Template applied.')]);
    }

    public function destroy(Book $book, DesignTemplate $template): JsonResponse
    {
        // Books pointing at the deleted template fall back to its base template.
        Book::query()
            ->whereNotNull('export_settings')
            ->get()
            ->filter(fn (Book $b) => ($b->export_settings['template'] ?? null) === $template->slug())
            ->each(function (Book $b) use ($template) {
                $settings = $b->export_settings;
                $settings['template'] = $template->based_on;
                $b->update(['export_settings' => $settings]);
            });

        $template->delete();

        return response()->json(['message' => __('Template deleted.')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request, bool $requireBasedOn = true): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'based_on' => [$requireBasedOn ? 'required' : 'sometimes', 'string', 'in:classic,modern,elegant'],
            'settings' => ['required', 'array:page,typography,headings,structure'],
            'settings.page' => ['required', 'array'],
            'settings.page.trim_size' => ['required', 'string', Rule::in([...array_map(fn (TrimSize $t) => $t->value, TrimSize::cases()), 'custom'])],
            'settings.page.custom_width' => ['nullable', 'numeric', 'min:50', 'max:500'],
            'settings.page.custom_height' => ['nullable', 'numeric', 'min:50', 'max:500'],
            'settings.page.bleed' => ['required', 'numeric', 'min:0', 'max:25'],
            'settings.page.bleed_mode' => ['required', 'string', 'in:all,outer'],
            'settings.page.margin_top' => ['required', 'numeric', 'min:5', 'max:80'],
            'settings.page.margin_bottom' => ['required', 'numeric', 'min:5', 'max:80'],
            'settings.page.margin_inner' => ['required', 'numeric', 'min:5', 'max:80'],
            'settings.page.margin_outer' => ['required', 'numeric', 'min:5', 'max:80'],
            'settings.typography' => ['required', 'array'],
            'settings.typography.font_pairing' => ['required', Rule::enum(FontPairing::class)],
            'settings.typography.font_size' => ['required', 'integer', 'in:9,10,11,12,13,14'],
            'settings.typography.line_height' => ['required', 'numeric', 'min:1', 'max:2.5'],
            'settings.typography.alignment' => ['required', 'string', 'in:justify,left'],
            'settings.typography.hyphenation' => ['required', 'boolean'],
            'settings.typography.first_line_indent' => ['required', 'boolean'],
            'settings.typography.paragraph_spacing_em' => ['required', 'numeric', 'min:0', 'max:3'],
            'settings.headings' => ['required', 'array'],
            'settings.headings.chapter_heading' => ['required', Rule::enum(ChapterHeading::class)],
            'settings.headings.heading_scale_em' => ['required', 'numeric', 'min:1', 'max:4'],
            'settings.headings.heading_top_space_em' => ['required', 'numeric', 'min:0', 'max:20'],
            'settings.headings.drop_caps' => ['required', 'boolean'],
            'settings.headings.scene_break_style' => ['required', Rule::enum(SceneBreakStyle::class)],
            'settings.structure' => ['required', 'array'],
            'settings.structure.show_page_numbers' => ['required', 'boolean'],
            'settings.structure.include_act_breaks' => ['required', 'boolean'],
        ]);
    }
}
