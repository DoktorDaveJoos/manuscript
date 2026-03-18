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

export type TrimSizeOption = { value: string; label: string };

export type MatterItem = {
    id: string;
    label: string;
    checked: boolean;
    settingsSection?: string;
};
