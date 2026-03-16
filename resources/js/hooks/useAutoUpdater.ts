import { check, download, install } from '@/actions/App/Http/Controllers/UpdateController';
import { jsonFetchHeaders } from '@/lib/utils';
import { useSyncExternalStore } from 'react';

type UpdateStatus = 'idle' | 'checking' | 'available' | 'downloading' | 'ready' | 'error';

export interface UpdateState {
    status: UpdateStatus;
    version: string | null;
    progress: number;
    error: string | null;
    releaseNotes: string | null;
}

const EVENTS = {
    CHECKING: 'Native\\Desktop\\Events\\AutoUpdater\\CheckingForUpdate',
    AVAILABLE: 'Native\\Desktop\\Events\\AutoUpdater\\UpdateAvailable',
    NOT_AVAILABLE: 'Native\\Desktop\\Events\\AutoUpdater\\UpdateNotAvailable',
    PROGRESS: 'Native\\Desktop\\Events\\AutoUpdater\\DownloadProgress',
    DOWNLOADED: 'Native\\Desktop\\Events\\AutoUpdater\\UpdateDownloaded',
    ERROR: 'Native\\Desktop\\Events\\AutoUpdater\\Error',
} as const;

function normalizeReleaseNotes(notes: string | string[] | null | undefined, fallback: string | null = null): string | null {
    return Array.isArray(notes) ? notes.join('\n') : (notes ?? fallback);
}

// ---------------------------------------------------------------------------
// Module-level singleton store — listeners register once, state is shared
// ---------------------------------------------------------------------------
let state: UpdateState = {
    status: 'idle',
    version: null,
    progress: 0,
    error: null,
    releaseNotes: null,
};

const listeners = new Set<() => void>();

function emit() {
    listeners.forEach((cb) => cb());
}

function setState(updater: (prev: UpdateState) => UpdateState) {
    const next = updater(state);
    if (next === state) return; // no-op guard
    state = next;
    emit();
}

function subscribe(cb: () => void) {
    listeners.add(cb);
    return () => listeners.delete(cb);
}

function getSnapshot() {
    return state;
}

// Register NativePHP event listeners exactly once
let nativeListenersRegistered = false;

function ensureNativeListeners() {
    if (nativeListenersRegistered || typeof window === 'undefined' || !window.Native?.on) {
        return;
    }
    nativeListenersRegistered = true;

    window.Native.on(EVENTS.CHECKING, () => {
        setState((prev) =>
            prev.status === 'checking' && prev.error === null ? prev : { ...prev, status: 'checking', error: null },
        );
    });

    window.Native.on(EVENTS.AVAILABLE, (payload: { version?: string; releaseNotes?: string | string[] | null }) => {
        setState((prev) => ({
            ...prev,
            status: 'available',
            version: payload?.version ?? null,
            releaseNotes: normalizeReleaseNotes(payload?.releaseNotes),
        }));
    });

    window.Native.on(EVENTS.NOT_AVAILABLE, () => {
        setState((prev) =>
            prev.status === 'idle' && prev.error === null ? prev : { ...prev, status: 'idle', error: null },
        );
    });

    window.Native.on(EVENTS.PROGRESS, (payload: { percent?: number }) => {
        const progress = Math.round(payload?.percent ?? 0);
        setState((prev) =>
            prev.status === 'downloading' && prev.progress === progress ? prev : { ...prev, status: 'downloading', progress },
        );
    });

    window.Native.on(EVENTS.DOWNLOADED, (payload: { version?: string; releaseNotes?: string | string[] | null }) => {
        setState((prev) => ({
            ...prev,
            status: 'ready',
            version: payload?.version ?? prev.version,
            progress: 100,
            releaseNotes: normalizeReleaseNotes(payload?.releaseNotes, prev.releaseNotes),
        }));
    });

    window.Native.on(EVENTS.ERROR, (payload: { message?: string }) => {
        setState((prev) => ({
            ...prev,
            status: 'error',
            error: payload?.message ?? 'Unknown error',
        }));
    });
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
function postAction(url: string, errorMsg: string) {
    fetch(url, { method: 'POST', headers: jsonFetchHeaders() }).catch(() => {
        setState((prev) => ({ ...prev, status: 'error', error: errorMsg }));
    });
}

function checkForUpdates() {
    setState((prev) =>
        prev.status === 'checking' ? prev : { ...prev, status: 'checking', error: null },
    );
    postAction(check.url(), 'Failed to check for updates');
}

function downloadUpdate() {
    setState((prev) =>
        prev.status === 'downloading' ? prev : { ...prev, status: 'downloading', progress: 0, error: null },
    );
    postAction(download.url(), 'Failed to download update');
}

function installUpdate() {
    postAction(install.url(), 'Failed to install update');
}

const IDLE_STATE: UpdateState = { status: 'idle', version: null, progress: 0, error: null, releaseNotes: null };

function dismissUpdate() {
    setState(() => IDLE_STATE);
}

// ---------------------------------------------------------------------------
// Hook — thin wrapper around the singleton store
// ---------------------------------------------------------------------------
export function useAutoUpdater() {
    ensureNativeListeners();
    const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
    return { state: snapshot, checkForUpdates, downloadUpdate, installUpdate, dismissUpdate };
}
