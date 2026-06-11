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

export type ChapterHeading = 'none' | 'number' | 'full';

/**
 * Which trim edges the bleed extends past — keep in sync with App\Enums\BleedMode.
 * 'all' = all four edges (Lulu, BoD, epubli, tredition);
 * 'outer' = outside edges only, never the binding edge (KDP, IngramSpark).
 */
export type BleedMode = 'all' | 'outer';

export const VISUAL_FORMATS: Set<Format> = new Set(['pdf', 'epub', 'kdp']);

/**
 * Persisted snapshot of the export page's UI selections — stored in
 * books.export_settings, keep keys in sync with
 * BookSettingsController::updateExportSettings.
 */
export type SavedExportSettings = {
    format?: Format;
    template?: string;
    font_pairing?: string;
    scene_break_style?: string;
    drop_caps?: boolean;
    chapter_heading?: ChapterHeading;
    include_act_breaks?: boolean;
    show_page_numbers?: boolean;
    trim_size?: string;
    font_size?: number;
    cmyk?: boolean;
    bleed?: number;
    bleed_mode?: BleedMode;
    custom_width?: number;
    custom_height?: number;
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
    pack: string;
    defaultFontPairing: string;
    defaultSceneBreakStyle: string;
    defaultDropCaps: boolean;
    headingFont: string;
    bodyFont: string;
};

export type FontPairingDef = {
    value: string;
    label: string;
    headingFont: string;
    bodyFont: string;
};

export type SceneBreakStyleDef = {
    value: string;
    label: string;
};
