export type StorylineType = 'main' | 'backstory' | 'parallel';
export type ChapterStatus = 'draft' | 'revised' | 'final';
export type VersionSource = 'original' | 'ai_revision' | 'manual_edit';
export type PlotPointType = 'setup' | 'conflict' | 'turning_point' | 'resolution' | 'worldbuilding';
export type PlotPointStatus = 'planned' | 'fulfilled' | 'abandoned';
export type CharacterRole = 'protagonist' | 'supporting' | 'mentioned';
export type AnalysisType = 'pacing' | 'plothole' | 'character_consistency' | 'density' | 'plot_deviation' | 'next_chapter_suggestion';
export type AiProvider = 'anthropic' | 'openai';

export type Book = {
    id: number;
    title: string;
    author: string;
    language: string;
    ai_provider: AiProvider;
    ai_enabled: boolean;
    created_at: string;
    updated_at: string;
    storylines?: Storyline[];
    acts?: Act[];
    characters?: Character[];
    chapters?: Chapter[];
    plot_points?: PlotPoint[];
    analyses?: Analysis[];
};

export type Storyline = {
    id: number;
    book_id: number;
    name: string;
    type: StorylineType;
    timeline_label: string | null;
    color: string | null;
    sort_order: number;
    created_at: string;
    updated_at: string;
    book?: Book;
    chapters?: Chapter[];
    plot_points?: PlotPoint[];
};

export type Act = {
    id: number;
    book_id: number;
    number: number;
    title: string;
    description: string | null;
    color: string | null;
    sort_order: number;
    created_at: string;
    updated_at: string;
    book?: Book;
    chapters?: Chapter[];
    plot_points?: PlotPoint[];
};

export type Character = {
    id: number;
    book_id: number;
    name: string;
    aliases: string[] | null;
    description: string | null;
    first_appearance: number | null;
    storylines: number[] | null;
    is_ai_extracted: boolean;
    created_at: string;
    updated_at: string;
    book?: Book;
    first_appearance_chapter?: Chapter;
    chapters?: (Chapter & { pivot: CharacterChapterPivot })[];
};

export type Chapter = {
    id: number;
    book_id: number;
    storyline_id: number;
    act_id: number | null;
    title: string;
    pov_character_id: number | null;
    timeline_position: string | null;
    reader_order: number;
    status: ChapterStatus;
    word_count: number;
    created_at: string;
    updated_at: string;
    book?: Book;
    storyline?: Storyline;
    act?: Act;
    pov_character?: Character;
    versions?: ChapterVersion[];
    current_version?: ChapterVersion;
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

export type ChapterVersion = {
    id: number;
    chapter_id: number;
    version_number: number;
    content: string | null;
    source: VersionSource;
    change_summary: string | null;
    is_current: boolean;
    created_at: string;
    updated_at: string;
    chapter?: Chapter;
};

export type PlotPoint = {
    id: number;
    book_id: number;
    storyline_id: number | null;
    act_id: number | null;
    title: string;
    description: string | null;
    type: PlotPointType;
    intended_chapter_id: number | null;
    actual_chapter_id: number | null;
    status: PlotPointStatus;
    sort_order: number;
    is_ai_derived: boolean;
    created_at: string;
    updated_at: string;
    book?: Book;
    storyline?: Storyline;
    act?: Act;
    intended_chapter?: Chapter;
    actual_chapter?: Chapter;
};

export type Analysis = {
    id: number;
    book_id: number;
    chapter_id: number | null;
    type: AnalysisType;
    result: Record<string, unknown> | null;
    ai_generated: boolean;
    created_at: string;
    updated_at: string;
    book?: Book;
    chapter?: Chapter;
};

export type CharacterChapterPivot = {
    character_id: number;
    chapter_id: number;
    role: CharacterRole;
    notes: string | null;
};
