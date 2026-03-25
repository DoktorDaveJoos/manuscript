// Front/back matter type constants — keep in sync with PHP enums
export const FRONT_MATTER_TYPES = [
    'title-page',
    'copyright',
    'dedication',
    'epigraph',
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
    width: number;
    height: number;
};

export type Format = 'epub' | 'pdf' | 'docx' | 'txt' | 'kdp';

export const VISUAL_FORMATS: Set<Format> = new Set(['pdf', 'epub', 'kdp']);

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
