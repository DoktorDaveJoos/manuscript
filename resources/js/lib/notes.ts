export type NoteBlockNavigation = 'previous' | 'next' | null;

export function shouldShowNotesEscapeHint(chapterId?: number): boolean {
    return chapterId !== undefined;
}

export function getNoteBlockNavigation({
    key,
    activeIndex,
    blockCount,
    selectionStart,
    selectionEnd,
    valueLength,
    hasModifier,
}: {
    key: string;
    activeIndex: number;
    blockCount: number;
    selectionStart: number;
    selectionEnd: number;
    valueLength: number;
    hasModifier: boolean;
}): NoteBlockNavigation {
    if (hasModifier || selectionStart !== selectionEnd) return null;

    if (key === 'ArrowUp' && activeIndex > 0 && selectionStart === 0) {
        return 'previous';
    }

    if (
        key === 'ArrowDown' &&
        activeIndex < blockCount - 1 &&
        selectionEnd === valueLength
    ) {
        return 'next';
    }

    return null;
}

export function matchesNoteSlashSearch(
    query: string,
    values: string[],
): boolean {
    const normalizedQuery = query.trim().toLocaleLowerCase();
    if (!normalizedQuery) return true;

    return values.some((value) =>
        value.toLocaleLowerCase().includes(normalizedQuery),
    );
}
