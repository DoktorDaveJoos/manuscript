import { router } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import { store } from '@/actions/App/Http/Controllers/ChapterController';
import type { Storyline } from '@/types/models';
import { getXsrfToken } from './csrf';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCompactCount(count: number): string {
    if (count >= 1_000_000) {
        return `${(count / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`;
    }
    if (count >= 1_000) {
        return `${(count / 1_000).toFixed(1).replace(/\.0$/, '')}k`;
    }
    return count.toLocaleString('en-US');
}

export function createChapter(
    bookId: number,
    storylineId: number,
    storylines: Storyline[],
): void {
    const totalChapters = storylines.reduce(
        (sum, s) => sum + (s.chapters?.length ?? 0),
        0,
    );
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

export function saveAppSetting(
    key: string,
    value: string | boolean | number,
): Promise<Response> {
    return fetch(update.url(), {
        method: 'PUT',
        headers: jsonFetchHeaders(),
        body: JSON.stringify({ key, value }),
    });
}

export function extractErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof Error && 'response' in error) {
        const resp = (error as Record<string, unknown>).response;
        if (resp && typeof resp === 'object' && 'data' in resp) {
            const data = (resp as Record<string, unknown>).data;
            if (data && typeof data === 'object') {
                const d = data as Record<string, unknown>;
                if (typeof d.message === 'string' && d.message)
                    return d.message;
                if (d.errors && typeof d.errors === 'object') {
                    const first = Object.values(d.errors).flat()[0];
                    if (typeof first === 'string') return first;
                }
            }
        }
    }
    if (error instanceof Error) return error.message;
    return fallback;
}

export function formatTimeAgo(
    dateString: string,
    t: (key: string, options?: Record<string, unknown>) => string,
    prefix: string,
): string {
    const now = new Date();
    const date = new Date(dateString);
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return t(`${prefix}.justNow`);
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return t(`${prefix}.minutes`, { count: minutes });
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return t(`${prefix}.hours`, { count: hours });
    const days = Math.floor(hours / 24);
    return t(`${prefix}.days`, { count: days });
}
