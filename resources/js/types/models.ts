export type StorylineType = 'main' | 'backstory' | 'parallel';
export type ChapterStatus = 'draft' | 'revised' | 'final';
export type VersionSource = 'original' | 'ai_revision' | 'manual_edit' | 'normalization' | 'beautify' | 'snapshot';
export type VersionStatus = 'accepted' | 'pending';
export type PlotPointType = 'setup' | 'conflict' | 'turning_point' | 'resolution' | 'worldbuilding';
export type PlotPointStatus = 'planned' | 'fulfilled' | 'abandoned';
export type ConnectionType = 'causes' | 'sets_up' | 'resolves' | 'contradicts';
export type CharacterRole = 'protagonist' | 'supporting' | 'mentioned';
export type AnalysisType = 'pacing' | 'plothole' | 'character_consistency' | 'density' | 'plot_deviation' | 'next_chapter_suggestion' | 'chapter_hook' | 'scene_audit' | 'thriller_health';
export type HookType = 'cliffhanger' | 'soft_hook' | 'closed' | 'dead_end';
export type AiProvider = 'anthropic' | 'openai' | 'gemini' | 'groq' | 'xai' | 'deepseek' | 'mistral' | 'ollama' | 'azure' | 'openrouter';

export type StoryBible = {
    characters?: Record<string, unknown>[];
    setting?: Record<string, unknown>[];
    plot_outline?: Record<string, unknown>[];
    themes?: (string | Record<string, unknown>)[];
    style_rules?: (string | Record<string, unknown>)[];
    genre_rules?: (string | Record<string, unknown>)[];
    timeline?: Record<string, unknown>[];
};

export type ProsePassRule = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
};

export type License = {
    active: boolean;
    masked_key: string | null;
};

export type AppSettings = {
    show_ai_features: boolean;
    hide_formatting_toolbar: boolean;
    typewriter_mode: boolean;
    show_scenes: boolean;
};

export type Book = {
    id: number;
    title: string;
    author: string;
    language: string;
    prose_pass_rules: ProsePassRule[] | null;
    writing_style_text: string | null;
    story_bible?: StoryBible | null;
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
    summary: string | null;
    notes: string | null;
    tension_score: number | null;
    hook_score: number | null;
    hook_type: HookType | null;
    analysis_status: 'pending' | 'running' | 'completed' | 'failed' | null;
    analysis_error: string | null;
    analyzed_at: string | null;
    created_at: string;
    updated_at: string;
    book?: Book;
    storyline?: Storyline;
    act?: Act;
    pov_character?: Character;
    scenes?: Scene[];
    versions?: ChapterVersion[];
    current_version?: ChapterVersion;
    pending_version?: ChapterVersion;
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

export type Scene = {
    id: number;
    chapter_id: number;
    title: string;
    content: string | null;
    sort_order: number;
    word_count: number;
};

export type ChapterVersion = {
    id: number;
    chapter_id: number;
    version_number: number;
    content: string | null;
    source: VersionSource;
    change_summary: string | null;
    is_current: boolean;
    status: VersionStatus;
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
    tension_score: number | null;
    created_at: string;
    updated_at: string;
    book?: Book;
    storyline?: Storyline;
    act?: Act;
    intended_chapter?: Chapter;
    actual_chapter?: Chapter;
    outgoing_connections?: PlotPointConnection[];
    incoming_connections?: PlotPointConnection[];
};

export type PlotPointConnection = {
    id: number;
    book_id: number;
    source_plot_point_id: number;
    target_plot_point_id: number;
    type: ConnectionType;
    description: string | null;
    created_at: string;
    updated_at: string;
    source?: PlotPoint;
    target?: PlotPoint;
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

export type DashboardStats = {
    total_words: number;
    chapter_count: number;
    estimated_pages: number;
    reading_time_minutes: number;
};

export type StatusCounts = {
    draft: number;
    revised: number;
    final: number;
};

export type HealthMetric = { label: string; score: number };
export type AttentionItem = { type: string; title: string; description: string; severity: 'low' | 'medium' | 'high' };
export type HealthMetrics = {
    composite_score: number;
    metrics: HealthMetric[];
    last_analyzed_at: string;
    attention_items: AttentionItem[];
};
export type SuggestedNext = { title: string; description: string; chapter_id?: number };

export type WritingGoalData = {
    daily_word_count_goal: number | null;
    today_words: number;
    streak: number;
    goal_met_today: boolean;
};

export type HeatmapDay = {
    date: string;
    words: number;
    goal_met: boolean;
};

export type HealthSnapshot = {
    date: string;
    composite: number;
    hooks: number;
    pacing: number;
    tension: number;
    weave: number;
};

export type AiUsage = {
    input_tokens: number;
    output_tokens: number;
    cost_display: string;
    reset_at: string | null;
};

export type ManuscriptTarget = {
    target_word_count: number | null;
    total_words: number;
    progress_percent: number;
    milestone_reached: boolean;
    milestone_reached_at: string | null;
    milestone_dismissed: boolean;
    days_writing: number;
};

export type TrashItem = {
    id: number;
    type: 'storyline' | 'chapter' | 'scene';
    name: string;
    color?: string | null;
    deleted_at: string;
};

export type NormalizePreviewResult = {
    chapters: {
        id: number;
        title: string;
        changes: { rule: string; count: number }[];
        total_changes: number;
    }[];
    total_changes: number;
};

export type PreparationPhase = 'chunking' | 'embedding' | 'writing_style' | 'chapter_analysis' | 'character_extraction' | 'story_bible' | 'health_analysis';

export type PhaseError = {
    phase: string;
    chapter: string | null;
    error: string;
};

export type AiPreparationStatus = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    current_phase: PreparationPhase | null;
    current_phase_total: number;
    current_phase_progress: number;
    total_chapters: number;
    processed_chapters: number;
    embedded_chunks: number;
    completed_phases: PreparationPhase[] | null;
    phase_errors: PhaseError[] | null;
    error_message: string | null;
    created_at: string;
    updated_at: string;
};

export type AiSetting = {
    id: number;
    provider: AiProvider;
    has_api_key: boolean;
    base_url: string | null;
    api_version: string | null;
    text_model: string | null;
    embedding_model: string | null;
    embedding_dimensions: number | null;
    enabled: boolean;
    requires_api_key: boolean;
    requires_base_url: boolean;
    created_at: string;
    updated_at: string;
};

export type CharacterChapterPivot = {
    character_id: number;
    chapter_id: number;
    role: CharacterRole;
    notes: string | null;
};

export type WikiEntryKind = 'location' | 'organization' | 'item' | 'lore';

export type WikiEntry = {
    id: number;
    book_id: number;
    kind: WikiEntryKind;
    name: string;
    type: string | null;
    description: string | null;
    first_appearance: number | null;
    metadata: Record<string, unknown> | null;
    is_ai_extracted: boolean;
    created_at: string;
    updated_at: string;
    first_appearance_chapter?: Chapter;
    chapters?: Chapter[];
};

export type WikiEntryChapterPivot = {
    wiki_entry_id: number;
    chapter_id: number;
    notes: string | null;
};
