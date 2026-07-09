// Front/back matter type constants — keep in sync with PHP enums
export const FRONT_MATTER_TYPES = [
    'title-page',
    'copyright',
    'dedication',
    'epigraph',
    'prologue',
    'toc',
] as const;
export const BACK_MATTER_TYPES = [
    'epilogue',
    'acknowledgments',
    'about-author',
    'also-by',
] as const;

export type FrontMatterType = (typeof FRONT_MATTER_TYPES)[number];
export type BackMatterType = (typeof BACK_MATTER_TYPES)[number];

export type ActRef = { id: number; number: number; title: string | null };

export type ChapterRow = {
    id: number;
    storyline_id: number;
    act_id: number | null;
    title: string;
    reader_order: number;
    word_count: number;
    content: string | null;
    is_epilogue?: boolean;
    is_prologue?: boolean;
};

export type StorylineRef = {
    id: number;
    name: string;
    color: string | null;
    type: string;
};

export type TrimSizeOption = {
    value: string;
    label: string;
    labelMetric: string;
    width: number;
    height: number;
};

export type Format = 'epub' | 'pdf' | 'docx' | 'txt' | 'kdp';

// Keep in sync with App\Enums\DocxLayout
export type DocxLayout = 'manuscript' | 'normseite';

export const VISUAL_FORMATS: Set<Format> = new Set(['pdf', 'epub', 'kdp']);

/**
 * Persisted snapshot of the export page's UI selections — stored in
 * books.export_settings, keep keys in sync with
 * BookSettingsController::updateExportSettings. Typesetting lives in the
 * Book Designer template, not here.
 */
export type SavedExportSettings = {
    format?: Format;
    template?: string;
    docx_layout?: DocxLayout;
    cmyk?: boolean;
    include_cover?: boolean;
    front_matter?: string[];
    back_matter?: string[];
    excluded_chapter_ids?: number[];
};

export type MatterItem = {
    id: string;
    label: string;
    checked: boolean;
    settingsSection?: string;
};

export type TemplateDef = {
    slug: string;
    name: string;
    group: 'builtin' | 'custom';
    headingFont: string;
    bodyFont: string;
    /** Trim dimensions in mm — drives the preview panel's page aspect ratio. */
    trimWidth: number;
    trimHeight: number;
};
