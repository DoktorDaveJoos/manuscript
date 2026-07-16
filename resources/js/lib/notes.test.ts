import { describe, expect, it } from 'vitest';
import {
    getNoteBlockNavigation,
    matchesNoteSlashSearch,
    shouldShowNotesEscapeHint,
} from '@/lib/notes';

describe('notes keyboard hints', () => {
    it('shows Escape only for chapter-scoped notes', () => {
        expect(shouldShowNotesEscapeHint(42)).toBe(true);
        expect(shouldShowNotesEscapeHint()).toBe(false);
    });
});

describe('note block navigation', () => {
    it('keeps arrow navigation native until the caret reaches a block boundary', () => {
        const baseSelection = {
            activeIndex: 1,
            blockCount: 3,
            selectionStart: 24,
            selectionEnd: 24,
            valueLength: 100,
            hasModifier: false,
        };

        expect(
            getNoteBlockNavigation({
                ...baseSelection,
                key: 'ArrowUp',
            }),
        ).toBeNull();
        expect(
            getNoteBlockNavigation({
                ...baseSelection,
                key: 'ArrowDown',
            }),
        ).toBeNull();
    });

    it('moves between blocks at the true start and end boundaries', () => {
        expect(
            getNoteBlockNavigation({
                key: 'ArrowUp',
                activeIndex: 1,
                blockCount: 3,
                selectionStart: 0,
                selectionEnd: 0,
                valueLength: 100,
                hasModifier: false,
            }),
        ).toBe('previous');
        expect(
            getNoteBlockNavigation({
                key: 'ArrowDown',
                activeIndex: 1,
                blockCount: 3,
                selectionStart: 100,
                selectionEnd: 100,
                valueLength: 100,
                hasModifier: false,
            }),
        ).toBe('next');
    });
});

describe('note slash search', () => {
    it('matches translated labels and descriptions as well as block ids', () => {
        const values = ['bullet', 'Bullet List', 'Create a simple list'];

        expect(matchesNoteSlashSearch('list', values)).toBe(true);
        expect(matchesNoteSlashSearch('simple', values)).toBe(true);
        expect(matchesNoteSlashSearch('bullet', values)).toBe(true);
        expect(matchesNoteSlashSearch('heading', values)).toBe(false);
    });
});
