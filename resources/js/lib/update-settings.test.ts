import { describe, expect, it } from 'vitest';
import englishSettings from '@/i18n/en/settings.json';
import {
    AUTOMATIC_UPDATE_CHECK_INTERVAL_MS,
    automaticUpdateCheckInterval,
    settledUpdateStatus,
} from '@/lib/update-settings';

describe('automatic update preferences', () => {
    it('only schedules polling when automatic updates are enabled', () => {
        expect(automaticUpdateCheckInterval(true)).toBe(
            AUTOMATIC_UPDATE_CHECK_INTERVAL_MS,
        );
        expect(automaticUpdateCheckInterval(false)).toBeNull();
    });

    it('shows available updates separately from the up-to-date state', () => {
        expect(settledUpdateStatus('available')).toBe('available');
        expect(settledUpdateStatus('idle')).toBe('up-to-date');
        expect(settledUpdateStatus('checking')).toBeNull();
        expect(englishSettings['updates.available']).toContain(
            'Version {{version}}',
        );
        expect(englishSettings['updates.available']).not.toBe(
            englishSettings['updates.upToDate'],
        );
    });
});
