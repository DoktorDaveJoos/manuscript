import { store } from '@/actions/App/Http/Controllers/ChapterController';
import type { Storyline } from '@/types/models';
import { router } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { getXsrfToken } from './csrf';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCompactCount(count: number): string {
    if (count >= 1000) {
        return `${(count / 1000).toFixed(1).replace(/\.0$/, '')}k`;
    }
    return count.toString();
}

export function createChapter(bookId: number, storylineId: number, storylines: Storyline[]): void {
    const totalChapters = storylines.reduce((sum, s) => sum + (s.chapters?.length ?? 0), 0);
    router.post(store.url({ book: bookId }), {
        title: `Chapter ${totalChapters + 1}`,
        storyline_id: storylineId,
    });
}

export function jsonFetchHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': getXsrfToken(),
    };
}
