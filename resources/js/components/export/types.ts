// Front/back matter type constants — keep in sync with PHP enums
export const FRONT_MATTER_TYPES = {
    TitlePage: 'title-page',
    Copyright: 'copyright',
    Toc: 'toc',
} as const;

export const BACK_MATTER_TYPES = {
    Acknowledgments: 'acknowledgments',
    AboutAuthor: 'about-author',
} as const;

export type FrontMatterType =
    (typeof FRONT_MATTER_TYPES)[keyof typeof FRONT_MATTER_TYPES];
export type BackMatterType =
    (typeof BACK_MATTER_TYPES)[keyof typeof BACK_MATTER_TYPES];

export type ActRef = { id: number; number: number; title: string | null };

export type ChapterRow = {
    id: number;
    storyline_id: number;
    act_id: number | null;
    title: string;
    reader_order: number;
    word_count: number;
    content: string | null;
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
