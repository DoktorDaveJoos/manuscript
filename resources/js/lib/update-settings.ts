export const AUTOMATIC_UPDATE_CHECK_INTERVAL_MS = 4 * 60 * 60 * 1000;

export type SettledUpdateStatus = 'available' | 'up-to-date' | null;

export function automaticUpdateCheckInterval(
    automaticUpdatesEnabled: boolean,
): number | null {
    return automaticUpdatesEnabled ? AUTOMATIC_UPDATE_CHECK_INTERVAL_MS : null;
}

export function settledUpdateStatus(status: string): SettledUpdateStatus {
    if (status === 'available') return 'available';
    if (status === 'idle') return 'up-to-date';

    return null;
}
