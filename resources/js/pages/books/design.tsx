import { Head } from '@inertiajs/react';
import { Trash2, WandSparkles } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    apply as applyTemplate,
    destroy as destroyTemplate,
    store as storeTemplate,
    update as updateTemplate,
} from '@/actions/App/Http/Controllers/BookDesignController';
import SpreadPreview from '@/components/design/SpreadPreview';
import type {
    BuiltInTemplateDef,
    CustomTemplateDef,
    DesignSettings,
    DesignTrimSizeOption,
    FontPairingDef,
    SceneBreakStyleDef,
} from '@/components/design/types';
import Sidebar from '@/components/editor/Sidebar';
import Button from '@/components/ui/Button';
import NumberInput from '@/components/ui/NumberInput';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import ToggleRow from '@/components/ui/ToggleRow';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Book } from '@/types/models';

interface Props {
    book: Book;
    builtInTemplates: BuiltInTemplateDef[];
    customTemplates: CustomTemplateDef[];
    currentTemplate: string;
    trimSizes: DesignTrimSizeOption[];
    fontPairings: FontPairingDef[];
    sceneBreakStyles: SceneBreakStyleDef[];
}

const LINE_HEIGHTS = [1.2, 1.3, 1.35, 1.4, 1.5, 1.6, 1.8];
const FONT_SIZES = [9, 10, 11, 12, 13, 14];
const HEADING_SCALES = [1.4, 1.6, 1.8, 2.0, 2.2, 2.6];
const HEADING_TOP_SPACES = [2, 3, 6, 9, 12];
const PARAGRAPH_SPACINGS = [0, 0.35, 0.5, 0.75, 1];

/**
 * Template settings aren't limited to the preset lists (e.g. Modern's real
 * heading scale is 1.2em) — a select must always show its actual value.
 */
function withCurrent(options: number[], current: number): number[] {
    return options.includes(current)
        ? options
        : [...options, current].sort((a, b) => a - b);
}

function FieldRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3 py-2">
            <span className="text-[13px] text-ink-soft">{label}</span>
            <div className="w-36 shrink-0">{children}</div>
        </div>
    );
}

export default function Design({
    book,
    builtInTemplates,
    customTemplates: initialCustomTemplates,
    currentTemplate,
    trimSizes,
    fontPairings,
    sceneBreakStyles,
}: Props) {
    const { t } = useTranslation('design');
    const sidebarStorylines = useSidebarStorylines();

    const [customTemplates, setCustomTemplates] = useState(
        initialCustomTemplates,
    );
    const [selectedSlug, setSelectedSlug] = useState(() => {
        const known = [
            ...builtInTemplates.map((b) => b.slug),
            ...initialCustomTemplates.map((c) => c.slug),
        ];
        return known.includes(currentTemplate) ? currentTemplate : 'classic';
    });
    const [previewVersion, setPreviewVersion] = useState(0);
    const [appliedSlug, setAppliedSlug] = useState(currentTemplate);
    const [pageTab, setPageTab] = useState<'paper' | 'content'>('paper');
    const [notice, setNotice] = useState<string | null>(null);
    const saveChain = useRef<Promise<unknown>>(Promise.resolve());

    const selectedBuiltIn = builtInTemplates.find(
        (b) => b.slug === selectedSlug,
    );
    const selectedCustom = customTemplates.find((c) => c.slug === selectedSlug);
    const settings: DesignSettings | null =
        selectedCustom?.settings ?? selectedBuiltIn?.settings ?? null;

    const trimSpec = useMemo(() => {
        if (!settings) return { width: 127, height: 203.2 };
        if (settings.page.trim_size === 'custom') {
            return {
                width: settings.page.custom_width ?? 127,
                height: settings.page.custom_height ?? 203.2,
            };
        }
        const found = trimSizes.find(
            (ts) => ts.value === settings.page.trim_size,
        );
        return found ?? { width: 127, height: 203.2 };
    }, [settings, trimSizes]);

    /**
     * The physical sheet the PDF renders: the trim grown by bleed, mirroring
     * PdfExporter::resolveGeometry (outer-only bleed spares the binding edge).
     */
    const sheetSpec = useMemo(() => {
        const bleed = Math.max(0, settings?.page.bleed ?? 0);
        return {
            width:
                trimSpec.width +
                (settings?.page.bleed_mode === 'outer' ? bleed : 2 * bleed),
            height: trimSpec.height + 2 * bleed,
        };
    }, [trimSpec, settings]);

    /**
     * Every edit persists immediately. Editing a built-in silently duplicates
     * it into an editable custom template first (built-ins stay read-only).
     */
    const updateSettings = useCallback(
        (mutate: (draft: DesignSettings) => void) => {
            if (!settings) return;

            const next: DesignSettings = structuredClone(settings);
            mutate(next);

            if (selectedCustom) {
                const updated = { ...selectedCustom, settings: next };
                setCustomTemplates((list) =>
                    list.map((c) => (c.id === updated.id ? updated : c)),
                );
                saveChain.current = saveChain.current.then(() =>
                    fetch(
                        updateTemplate.url([book, { id: selectedCustom.id }]),
                        {
                            method: 'PUT',
                            headers: jsonFetchHeaders(),
                            body: JSON.stringify({
                                name: selectedCustom.name,
                                settings: next,
                            }),
                        },
                    ).then(() => setPreviewVersion((v) => v + 1)),
                );
            } else if (selectedBuiltIn) {
                const name = `${selectedBuiltIn.name} (${t('template.copySuffix')})`;
                saveChain.current = saveChain.current.then(() =>
                    fetch(storeTemplate.url(book), {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({
                            name,
                            based_on: selectedBuiltIn.slug,
                            settings: next,
                        }),
                    })
                        .then((res) => res.json() as Promise<CustomTemplateDef>)
                        .then((created) => {
                            setCustomTemplates((list) => [...list, created]);
                            setSelectedSlug(created.slug);
                            setNotice(
                                t('template.duplicated', {
                                    name: created.name,
                                }),
                            );
                            setPreviewVersion((v) => v + 1);
                        }),
                );
            }
        },
        [settings, selectedCustom, selectedBuiltIn, book, t],
    );

    const handleApply = useCallback(() => {
        fetch(applyTemplate.url(book), {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ template: selectedSlug }),
        }).then(() => {
            setAppliedSlug(selectedSlug);
            setNotice(t('actions.applied'));
        });
    }, [book, selectedSlug, t]);

    const handleDelete = useCallback(() => {
        if (!selectedCustom) return;
        fetch(destroyTemplate.url([book, { id: selectedCustom.id }]), {
            method: 'DELETE',
            headers: jsonFetchHeaders(),
        }).then(() => {
            setCustomTemplates((list) =>
                list.filter((c) => c.id !== selectedCustom.id),
            );
            setSelectedSlug(selectedCustom.basedOn);
            setPreviewVersion((v) => v + 1);
        });
    }, [book, selectedCustom]);

    if (!settings) return null;

    return (
        <div className="flex h-screen bg-surface">
            <Head title={`${t('title')} — ${book.title}`} />
            <Sidebar
                book={book}
                storylines={sidebarStorylines}
                scenesVisible={false}
                onScenesVisibleChange={() => {}}
            />

            <main className="flex min-w-0 flex-1 flex-col">
                {/* Top bar: template picker + apply */}
                <div className="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border-subtle px-6">
                    <div className="flex items-center gap-3">
                        <div className="w-64">
                            <Select
                                variant="compact"
                                value={selectedSlug}
                                aria-label={t('template')}
                                data-testid="design-template-select"
                                onChange={(e) => {
                                    setSelectedSlug(e.target.value);
                                    setPreviewVersion((v) => v + 1);
                                }}
                            >
                                <optgroup label={t('template.builtIn')}>
                                    {builtInTemplates.map((b) => (
                                        <option key={b.slug} value={b.slug}>
                                            {b.name}
                                        </option>
                                    ))}
                                </optgroup>
                                {customTemplates.length > 0 && (
                                    <optgroup label={t('template.custom')}>
                                        {customTemplates.map((c) => (
                                            <option key={c.slug} value={c.slug}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                )}
                            </Select>
                        </div>
                        {selectedCustom ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('template.delete')}
                                onClick={handleDelete}
                            >
                                <Trash2 className="size-3.5 text-delete" />
                            </Button>
                        ) : (
                            <span className="text-[11px] text-ink-faint">
                                {t('template.readOnlyHint')}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {notice && (
                            <span className="text-[11px] text-ink-muted">
                                {notice}
                            </span>
                        )}
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={handleApply}
                            disabled={appliedSlug === selectedSlug}
                            data-testid="design-apply"
                        >
                            <WandSparkles className="size-3.5" />
                            {t('actions.apply')}
                        </Button>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1">
                    {/* Left panel: page setup */}
                    <aside className="w-[300px] shrink-0 overflow-y-auto border-r border-border-subtle px-5 py-5">
                        <div className="mb-4 flex items-center justify-between">
                            <SectionLabel variant="section">
                                {t('page.title')}
                            </SectionLabel>
                            <ToggleGroup
                                type="single"
                                value={pageTab}
                                onValueChange={(v) =>
                                    v && setPageTab(v as 'paper' | 'content')
                                }
                            >
                                <ToggleGroupItem value="paper">
                                    {t('page.tab.paper')}
                                </ToggleGroupItem>
                                <ToggleGroupItem value="content">
                                    {t('page.tab.content')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        {pageTab === 'paper' ? (
                            <div className="flex flex-col gap-5">
                                <div>
                                    <p className="mb-1 text-sm font-medium text-ink">
                                        {t('page.trimSize')}
                                    </p>
                                    <p className="mb-2 text-[12px] text-ink-muted">
                                        {t('page.trimSize.help')}
                                    </p>
                                    <Select
                                        variant="compact"
                                        value={settings.page.trim_size}
                                        data-testid="design-trim-size"
                                        onChange={(e) =>
                                            updateSettings((d) => {
                                                d.page.trim_size =
                                                    e.target.value;
                                                const preset = trimSizes.find(
                                                    (ts) =>
                                                        ts.value ===
                                                        e.target.value,
                                                );
                                                if (preset) {
                                                    d.page.margin_top =
                                                        preset.margins.top;
                                                    d.page.margin_bottom =
                                                        preset.margins.bottom;
                                                    d.page.margin_inner =
                                                        preset.margins.gutter;
                                                    d.page.margin_outer =
                                                        preset.margins.outer;
                                                }
                                            })
                                        }
                                    >
                                        {trimSizes.map((ts) => (
                                            <option
                                                key={ts.value}
                                                value={ts.value}
                                            >
                                                {ts.label} · {ts.labelMetric}
                                            </option>
                                        ))}
                                        <option value="custom">Custom</option>
                                    </Select>
                                    {settings.page.trim_size === 'custom' && (
                                        <div className="mt-2 flex gap-2">
                                            <FieldRow
                                                label={t('page.customWidth')}
                                            >
                                                <NumberInput
                                                    value={
                                                        settings.page
                                                            .custom_width ?? 127
                                                    }
                                                    min={50}
                                                    max={500}
                                                    unit="mm"
                                                    onChange={(v) =>
                                                        updateSettings((d) => {
                                                            d.page.custom_width =
                                                                v;
                                                        })
                                                    }
                                                />
                                            </FieldRow>
                                            <FieldRow
                                                label={t('page.customHeight')}
                                            >
                                                <NumberInput
                                                    value={
                                                        settings.page
                                                            .custom_height ??
                                                        203
                                                    }
                                                    min={50}
                                                    max={500}
                                                    unit="mm"
                                                    onChange={(v) =>
                                                        updateSettings((d) => {
                                                            d.page.custom_height =
                                                                v;
                                                        })
                                                    }
                                                />
                                            </FieldRow>
                                        </div>
                                    )}
                                </div>

                                <div className="border-t border-border-subtle pt-4">
                                    <p className="mb-1 text-sm font-medium text-ink">
                                        {t('page.bleed')}
                                    </p>
                                    <p className="mb-2 text-[12px] text-ink-muted">
                                        {t('page.bleed.help')}
                                    </p>
                                    <FieldRow label={t('page.bleed')}>
                                        <NumberInput
                                            value={settings.page.bleed}
                                            min={0}
                                            max={25}
                                            step={0.1}
                                            unit="mm"
                                            onChange={(v) =>
                                                updateSettings((d) => {
                                                    d.page.bleed = v;
                                                })
                                            }
                                        />
                                    </FieldRow>
                                    <ToggleRow
                                        label={t('page.bleed.outerOnly')}
                                        checked={
                                            settings.page.bleed_mode === 'outer'
                                        }
                                        border={false}
                                        onChange={() =>
                                            updateSettings((d) => {
                                                d.page.bleed_mode =
                                                    d.page.bleed_mode === 'all'
                                                        ? 'outer'
                                                        : 'all';
                                            })
                                        }
                                    />
                                </div>

                                <div className="border-t border-border-subtle pt-4">
                                    <p className="mb-1 text-sm font-medium text-ink">
                                        {t('page.margins')}
                                    </p>
                                    <p className="mb-2 text-[12px] text-ink-muted">
                                        {t('page.margins.help')}
                                    </p>
                                    {(
                                        [
                                            ['margin_top', 'page.marginTop'],
                                            [
                                                'margin_bottom',
                                                'page.marginBottom',
                                            ],
                                            [
                                                'margin_inner',
                                                'page.marginInner',
                                            ],
                                            [
                                                'margin_outer',
                                                'page.marginOuter',
                                            ],
                                        ] as const
                                    ).map(([key, labelKey]) => (
                                        <FieldRow key={key} label={t(labelKey)}>
                                            <NumberInput
                                                value={settings.page[key]}
                                                min={5}
                                                max={80}
                                                unit="mm"
                                                aria-label={t(labelKey)}
                                                onChange={(v) =>
                                                    updateSettings((d) => {
                                                        d.page[key] = v;
                                                    })
                                                }
                                            />
                                        </FieldRow>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col">
                                <ToggleRow
                                    label={t('structure.pageNumbers')}
                                    checked={
                                        settings.structure.show_page_numbers
                                    }
                                    onChange={() =>
                                        updateSettings((d) => {
                                            d.structure.show_page_numbers =
                                                !d.structure.show_page_numbers;
                                        })
                                    }
                                />
                                <ToggleRow
                                    label={t('structure.actBreaks')}
                                    checked={
                                        settings.structure.include_act_breaks
                                    }
                                    border={false}
                                    onChange={() =>
                                        updateSettings((d) => {
                                            d.structure.include_act_breaks =
                                                !d.structure.include_act_breaks;
                                        })
                                    }
                                />
                            </div>
                        )}
                    </aside>

                    {/* Center: spread preview */}
                    <div className="min-w-0 flex-1 bg-neutral-bg">
                        <SpreadPreview
                            bookId={book.id}
                            templateSlug={selectedSlug}
                            version={previewVersion}
                            trimWidth={sheetSpec.width}
                            trimHeight={sheetSpec.height}
                        />
                    </div>

                    {/* Right panel: text layout */}
                    <aside className="w-[320px] shrink-0 overflow-y-auto border-l border-border-subtle px-5 py-5">
                        <SectionLabel variant="section" className="mb-4 block">
                            {t('text.title')}
                        </SectionLabel>

                        <div className="mb-1">
                            <p className="text-sm font-medium text-ink">
                                {t('text.headings')}
                            </p>
                            <p className="mb-2 text-[12px] text-ink-muted">
                                {t('text.headings.help')}
                            </p>
                        </div>
                        <FieldRow label={t('text.chapterHeading')}>
                            <Select
                                variant="compact"
                                value={settings.headings.chapter_heading}
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.headings.chapter_heading =
                                            e.target.value;
                                    })
                                }
                            >
                                <option value="none">
                                    {t('text.chapterHeading.none')}
                                </option>
                                <option value="number">
                                    {t('text.chapterHeading.number')}
                                </option>
                                <option value="full">
                                    {t('text.chapterHeading.full')}
                                </option>
                            </Select>
                        </FieldRow>
                        <FieldRow label={t('text.headingScale')}>
                            <Select
                                variant="compact"
                                value={String(
                                    settings.headings.heading_scale_em,
                                )}
                                data-testid="design-heading-scale"
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.headings.heading_scale_em = Number(
                                            e.target.value,
                                        );
                                    })
                                }
                            >
                                {withCurrent(
                                    HEADING_SCALES,
                                    settings.headings.heading_scale_em,
                                ).map((s) => (
                                    <option key={s} value={String(s)}>
                                        {s}×
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <FieldRow label={t('text.headingTopSpace')}>
                            <Select
                                variant="compact"
                                value={String(
                                    settings.headings.heading_top_space_em,
                                )}
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.headings.heading_top_space_em =
                                            Number(e.target.value);
                                    })
                                }
                            >
                                {withCurrent(
                                    HEADING_TOP_SPACES,
                                    settings.headings.heading_top_space_em,
                                ).map((s) => (
                                    <option key={s} value={String(s)}>
                                        {s} em
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <FieldRow label={t('text.sceneBreak')}>
                            <Select
                                variant="compact"
                                value={settings.headings.scene_break_style}
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.headings.scene_break_style =
                                            e.target.value;
                                    })
                                }
                            >
                                {sceneBreakStyles.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <ToggleRow
                            label={t('text.dropCaps')}
                            checked={settings.headings.drop_caps}
                            border={false}
                            onChange={() =>
                                updateSettings((d) => {
                                    d.headings.drop_caps =
                                        !d.headings.drop_caps;
                                })
                            }
                        />

                        <div className="mt-5 border-t border-border-subtle pt-4">
                            <p className="text-sm font-medium text-ink">
                                {t('text.body')}
                            </p>
                            <p className="mb-2 text-[12px] text-ink-muted">
                                {t('text.body.help')}
                            </p>
                        </div>
                        <FieldRow label={t('text.fontPairing')}>
                            <Select
                                variant="compact"
                                value={settings.typography.font_pairing}
                                data-testid="design-font-pairing"
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.typography.font_pairing =
                                            e.target.value;
                                    })
                                }
                            >
                                {fontPairings.map((fp) => (
                                    <option key={fp.value} value={fp.value}>
                                        {fp.label}
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <FieldRow label={t('text.fontSize')}>
                            <Select
                                variant="compact"
                                value={String(settings.typography.font_size)}
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.typography.font_size = Number(
                                            e.target.value,
                                        );
                                    })
                                }
                            >
                                {withCurrent(
                                    FONT_SIZES,
                                    settings.typography.font_size,
                                ).map((s) => (
                                    <option key={s} value={String(s)}>
                                        {s} pt
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <FieldRow label={t('text.lineHeight')}>
                            <Select
                                variant="compact"
                                value={String(settings.typography.line_height)}
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.typography.line_height = Number(
                                            e.target.value,
                                        );
                                    })
                                }
                            >
                                {withCurrent(
                                    LINE_HEIGHTS,
                                    settings.typography.line_height,
                                ).map((lh) => (
                                    <option key={lh} value={String(lh)}>
                                        {lh}
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <FieldRow label={t('text.paragraphSpacing')}>
                            <Select
                                variant="compact"
                                value={String(
                                    settings.typography.paragraph_spacing_em,
                                )}
                                onChange={(e) =>
                                    updateSettings((d) => {
                                        d.typography.paragraph_spacing_em =
                                            Number(e.target.value);
                                    })
                                }
                            >
                                {withCurrent(
                                    PARAGRAPH_SPACINGS,
                                    settings.typography.paragraph_spacing_em,
                                ).map((s) => (
                                    <option key={s} value={String(s)}>
                                        {s} em
                                    </option>
                                ))}
                            </Select>
                        </FieldRow>
                        <div className="py-2">
                            <span className="mb-1.5 block text-[13px] text-ink-soft">
                                {t('text.alignment')}
                            </span>
                            <ToggleGroup
                                type="single"
                                value={settings.typography.alignment}
                                onValueChange={(v) =>
                                    v &&
                                    updateSettings((d) => {
                                        d.typography.alignment = v as
                                            | 'justify'
                                            | 'left';
                                    })
                                }
                            >
                                <ToggleGroupItem value="justify">
                                    {t('text.alignment.justify')}
                                </ToggleGroupItem>
                                <ToggleGroupItem value="left">
                                    {t('text.alignment.left')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>
                        <ToggleRow
                            label={t('text.hyphenation')}
                            checked={settings.typography.hyphenation}
                            onChange={() =>
                                updateSettings((d) => {
                                    d.typography.hyphenation =
                                        !d.typography.hyphenation;
                                })
                            }
                        />
                        <ToggleRow
                            label={t('text.firstLineIndent')}
                            checked={settings.typography.first_line_indent}
                            border={false}
                            onChange={() =>
                                updateSettings((d) => {
                                    d.typography.first_line_indent =
                                        !d.typography.first_line_indent;
                                })
                            }
                        />
                        <p className="text-[11px] text-ink-faint">
                            {t('text.firstLineIndent.help')}
                        </p>
                    </aside>
                </div>
            </main>
        </div>
    );
}
