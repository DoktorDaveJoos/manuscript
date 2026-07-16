var __awaiter =
    (this && this.__awaiter) ||
    function (thisArg, _arguments, P, generator) {
        function adopt(value) {
            return value instanceof P
                ? value
                : new P(function (resolve) {
                      resolve(value);
                  });
        }
        return new (P || (P = Promise))(function (resolve, reject) {
            function fulfilled(value) {
                try {
                    step(generator.next(value));
                } catch (e) {
                    reject(e);
                }
            }
            function rejected(value) {
                try {
                    step(generator['throw'](value));
                } catch (e) {
                    reject(e);
                }
            }
            function step(result) {
                result.done
                    ? resolve(result.value)
                    : adopt(result.value).then(fulfilled, rejected);
            }
            step(
                (generator = generator.apply(thisArg, _arguments || [])).next(),
            );
        });
    };
import {
    app,
    session,
    powerMonitor,
    dialog,
    autoUpdater as nativeAutoUpdater,
} from 'electron';
import { initialize } from '@electron/remote/main/index.js';
import state from './server/state.js';
import { electronApp, optimizer } from '@electron-toolkit/utils';
import {
    retrieveNativePHPConfig,
    retrievePhpIniSettings,
    runScheduler,
    killScheduler,
    startAPI,
    startPhpApp,
} from './server/index.js';
import { prepareRecoveryLaunch } from './server/php.js';
import { notifyLaravel, notifyLaravelOrThrow } from './server/utils.js';
import { resolve } from 'path';
import { stopAllProcesses } from './server/api/childProcess.js';
import { getWindowLoadPromise } from './server/api/window.js';
import ps from 'ps-node';
import killSync from 'kill-sync';
import electronUpdater from 'electron-updater';
const { autoUpdater } = electronUpdater;
const RECOVERY_ARG = '--nativephp-recovery';
const MAIN_WINDOW_ID = 'main';
const WINDOW_READY_TIMEOUT = 120000;
class NativePHP {
    constructor() {
        this.processes = [];
        this.mainWindow = null;
        this.schedulerInterval = undefined;
        this.bootstrapPromise = null;
        this.reopenPromise = null;
        this.isBootstrapping = true;
        this.isQuitting = false;
        this.isRecovering = false;
        this.recoveryLaunch = process.argv.includes(RECOVERY_ARG);
    }
    bootstrap(app, icon, phpBinary, cert, appPath) {
        // Enforce a single running instance on ALL platforms. Without this, macOS
        // (which had no single-instance lock here, and keeps the app resident after
        // its window closes) lets a second launch start while the first still holds
        // the Electron API port. The second instance then boots its PHP against a
        // port with no healthy API listener, so its window never opens. A second
        // launch should focus the instance that is already running instead.
        if (!app.requestSingleInstanceLock()) {
            app.quit();
            return;
        }
        app.on('second-instance', () => {
            // Resident app with no open window (e.g. closed on macOS) asks
            // Laravel to open one. If initial bootstrap is still running, this
            // waits for it instead of sending a competing /booted request.
            this.reopenLaravelWindow('second-instance');
        });
        initialize();
        state.icon = icon;
        state.php = phpBinary;
        state.caCert = cert;
        state.appPath = appPath;
        this.bootstrapApp(app);
        this.addEventListeners(app);
    }
    addEventListeners(app) {
        app.on('open-url', (event, url) => {
            notifyLaravel('events', {
                event: '\\Native\\Desktop\\Events\\App\\OpenedFromURL',
                payload: [url],
            });
        });
        app.on('open-file', (event, path) => {
            notifyLaravel('events', {
                event: '\\Native\\Desktop\\Events\\App\\OpenFile',
                payload: [path],
            });
        });
        app.on('window-all-closed', () => {
            if (process.platform !== 'darwin') {
                app.quit();
            }
        });
        nativeAutoUpdater.on('before-quit-for-update', () => {
            this.isQuitting = true;
        });
        app.on('before-quit', () => {
            this.isQuitting = true;
            if (this.schedulerInterval) {
                clearInterval(this.schedulerInterval);
            }
            stopAllProcesses();
            this.killChildProcesses();
        });
        app.on('browser-window-created', (_, window) => {
            optimizer.watchWindowShortcuts(window);
        });
        app.on('activate', (event, hasVisibleWindows) => {
            if (!hasVisibleWindows) {
                this.reopenLaravelWindow('activate');
            }
            event.preventDefault();
        });
    }
    bootstrapApp(app) {
        this.bootstrapPromise = this.runBootstrap(app)
            .then(() => true)
            .catch((error) => {
                this.handleStartupFailure(error, 'startup');
                return false;
            })
            .finally(() => {
                this.isBootstrapping = false;
                this.bootstrapPromise = null;
            });
    }
    reopenLaravelWindow(phase) {
        if (
            this.isQuitting ||
            this.reopenPromise ||
            this.isRecovering ||
            (this.isBootstrapping && !this.bootstrapPromise)
        ) {
            return;
        }

        if (this.bootstrapPromise) {
            this.reopenPromise = this.bootstrapPromise
                .then((didBootstrap) => {
                    if (
                        !didBootstrap ||
                        this.isQuitting ||
                        this.focusMainWindow()
                    ) {
                        return;
                    }

                    return this.requestLaravelWindow(phase);
                })
                .finally(() => {
                    this.reopenPromise = null;
                });

            return;
        }

        if (this.focusMainWindow()) {
            return;
        }

        this.reopenPromise = this.requestLaravelWindow(phase).finally(() => {
            this.reopenPromise = null;
        });
    }
    requestLaravelWindow(phase) {
        return notifyLaravelOrThrow('booted')
            .then(() => this.waitForMainWindow())
            .then(() => {
                if (this.isQuitting) {
                    return;
                }

                this.recoveryLaunch = false;
                this.focusMainWindow();
            })
            .catch((error) => {
                this.handleStartupFailure(error, phase);
            });
    }
    focusMainWindow() {
        const window = state.windows[MAIN_WINDOW_ID];
        if (!window || window.isDestroyed()) {
            return false;
        }
        try {
            if (window.isMinimized()) {
                window.restore();
            }
            if (!window.isVisible()) {
                window.show();
            }
            window.focus();
        } catch (error) {
            console.error('Failed to focus the main window:', error);
            return false;
        }
        return true;
    }
    waitForMainWindow(timeout = WINDOW_READY_TIMEOUT) {
        return new Promise((resolve, reject) => {
            const window = state.windows[MAIN_WINDOW_ID];
            if (!window || window.isDestroyed()) {
                reject(
                    new Error(
                        'Laravel boot completed without opening the main window.',
                    ),
                );
                return;
            }

            const loadPromise = getWindowLoadPromise(MAIN_WINDOW_ID);
            if (!loadPromise) {
                reject(
                    new Error(
                        'The main window did not register a page-load operation.',
                    ),
                );
                return;
            }

            let timeoutId;
            let isSettled = false;
            let didLoad = false;
            let didShow = window.isVisible();

            const cleanup = () => {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                window.removeListener('show', onShown);
                window.removeListener('closed', onClosed);
            };
            const fail = (error) => {
                if (isSettled) {
                    return;
                }
                isSettled = true;
                cleanup();
                reject(error);
            };
            const finishIfReady = () => {
                if (isSettled || !didLoad || !didShow) {
                    return;
                }
                if (
                    window.isDestroyed() ||
                    state.windows[MAIN_WINDOW_ID] !== window
                ) {
                    fail(new Error('The main window closed before it loaded.'));
                    return;
                }
                if (!window.isVisible()) {
                    didShow = false;
                    return;
                }
                isSettled = true;
                cleanup();
                resolve(window);
            };
            const onShown = () => {
                didShow = true;
                finishIfReady();
            };
            const onClosed = () => {
                fail(new Error('The main window closed before it loaded.'));
            };

            window.on('show', onShown);
            window.once('closed', onClosed);
            timeoutId = setTimeout(() => {
                fail(
                    new Error(
                        'The main window did not finish loading within 120 seconds.',
                    ),
                );
            }, timeout);

            loadPromise
                .then(() => {
                    didLoad = true;
                    didShow = window.isVisible();
                    finishIfReady();
                })
                .catch((error) => {
                    fail(error);
                });
        });
    }
    handleStartupFailure(error, phase) {
        if (this.isQuitting || this.isRecovering) {
            return;
        }
        console.error('NativePHP failed to start:', {
            phase,
            phpPort: state.phpPort,
            error,
        });
        this.isRecovering = true;
        if (!this.recoveryLaunch) {
            this.recoveryLaunch = true;
            const args = process.argv
                .slice(1)
                .filter((argument) => argument !== RECOVERY_ARG);
            console.warn(
                'NativePHP is relaunching once to recover its background services.',
            );
            this.isQuitting = true;
            app.relaunch({ args: [...args, RECOVERY_ARG] });
            app.quit();
            return;
        }
        try {
            dialog.showErrorBox(
                "Manuscript couldn't start",
                'Manuscript tried to recover automatically, but its background ' +
                    'services still could not start.\n\nPlease restart your computer ' +
                    'and open Manuscript again.',
            );
        } catch (_a) {
            // showErrorBox can itself fail very early in startup; quitting is the priority.
        }
        this.isQuitting = true;
        app.quit();
    }
    runBootstrap(app) {
        return __awaiter(this, void 0, void 0, function* () {
            yield app.whenReady();
            prepareRecoveryLaunch();
            yield this.startElectronApi();
            const config = yield this.loadConfig();
            this.setDockIcon();
            this.setAppUserModelId(config);
            this.setDeepLinkHandler(config);
            this.startAutoUpdater(config);
            state.phpIni = yield this.loadPhpIni();
            yield this.startPhpApp();
            this.startScheduler();
            powerMonitor.on('suspend', () => {
                this.stopScheduler();
            });
            powerMonitor.on('resume', () => {
                this.stopScheduler();
                this.startScheduler();
            });
            const filter = {
                urls: [`http://127.0.0.1:${state.phpPort}/*`],
            };
            session.defaultSession.webRequest.onBeforeSendHeaders(
                filter,
                (details, callback) => {
                    details.requestHeaders['X-NativePHP-Secret'] =
                        state.randomSecret;
                    callback({ requestHeaders: details.requestHeaders });
                },
            );
            if (process.env.NATIVEPHP_NO_FOCUS) {
                state.noFocusOnRestart = true;
            }
            yield notifyLaravelOrThrow('booted');
            yield this.waitForMainWindow();
            this.recoveryLaunch = false;
        });
    }
    loadConfig() {
        return __awaiter(this, void 0, void 0, function* () {
            let config = {};
            try {
                const result = yield retrieveNativePHPConfig();
                config = JSON.parse(result.stdout);
            } catch (error) {
                console.error(error);
            }
            return config;
        });
    }
    setDockIcon() {
        if (
            process.platform === 'darwin' &&
            process.env.NODE_ENV === 'development'
        ) {
            app.dock.setIcon(state.icon);
        }
    }
    setAppUserModelId(config) {
        electronApp.setAppUserModelId(
            config === null || config === void 0 ? void 0 : config.app_id,
        );
    }
    setDeepLinkHandler(config) {
        const deepLinkProtocol =
            config === null || config === void 0
                ? void 0
                : config.deeplink_scheme;
        if (deepLinkProtocol) {
            if (process.defaultApp) {
                if (process.argv.length >= 2) {
                    app.setAsDefaultProtocolClient(
                        deepLinkProtocol,
                        process.execPath,
                        [resolve(process.argv[1])],
                    );
                }
            } else {
                app.setAsDefaultProtocolClient(deepLinkProtocol);
            }
            if (process.platform !== 'darwin') {
                const gotTheLock = app.requestSingleInstanceLock();
                if (!gotTheLock) {
                    app.quit();
                    return;
                } else {
                    app.on(
                        'second-instance',
                        (event, commandLine, workingDirectory) => {
                            if (this.mainWindow) {
                                if (this.mainWindow.isMinimized())
                                    this.mainWindow.restore();
                                this.mainWindow.focus();
                            }
                            notifyLaravel('events', {
                                event: '\\Native\\Desktop\\Events\\App\\OpenedFromURL',
                                payload: {
                                    url: commandLine[commandLine.length - 1],
                                },
                            });
                        },
                    );
                }
            }
        }
    }
    startAutoUpdater(config) {
        var _a, _b, _c, _d, _e;
        if (
            ((_a =
                config === null || config === void 0
                    ? void 0
                    : config.updater) === null || _a === void 0
                ? void 0
                : _a.enabled) === true
        ) {
            const defaultProvider =
                (_b =
                    config === null || config === void 0
                        ? void 0
                        : config.updater) === null || _b === void 0
                    ? void 0
                    : _b.default;
            const publicUrl =
                (_e =
                    (_d =
                        (_c =
                            config === null || config === void 0
                                ? void 0
                                : config.updater) === null || _c === void 0
                            ? void 0
                            : _c.providers) === null || _d === void 0
                        ? void 0
                        : _d[defaultProvider]) === null || _e === void 0
                    ? void 0
                    : _e.public_url;
            if (publicUrl) {
                autoUpdater.setFeedURL({
                    provider: 'generic',
                    url: publicUrl,
                });
            }
            if (config.updater.check_on_startup === true) {
                autoUpdater.checkForUpdatesAndNotify();
            }
        }
    }
    startElectronApi() {
        return __awaiter(this, void 0, void 0, function* () {
            const electronApi = yield startAPI();
            state.electronApiPort = electronApi.port;
            console.log(
                'Electron API server started on port',
                electronApi.port,
            );
        });
    }
    loadPhpIni() {
        return __awaiter(this, void 0, void 0, function* () {
            let config = {};
            try {
                const result = yield retrievePhpIniSettings();
                config = JSON.parse(result.stdout);
            } catch (error) {
                console.error(error);
            }
            return config;
        });
    }
    startPhpApp() {
        return __awaiter(this, void 0, void 0, function* () {
            this.processes.push(yield startPhpApp());
        });
    }
    stopScheduler() {
        if (this.schedulerInterval) {
            clearInterval(this.schedulerInterval);
            this.schedulerInterval = null;
        }
        killScheduler();
    }
    startScheduler() {
        const now = new Date();
        const delay =
            (60 - now.getSeconds()) * 1000 + (1000 - now.getMilliseconds());
        setTimeout(() => {
            console.log('Running scheduler...');
            runScheduler();
            this.schedulerInterval = setInterval(() => {
                console.log('Running scheduler...');
                runScheduler();
            }, 60 * 1000);
        }, delay);
    }
    killChildProcesses() {
        this.stopScheduler();
        this.processes
            .filter((p) => p !== undefined)
            .forEach((process) => {
                if (!process || !process.pid) return;
                if (process.killed && process.exitCode !== null) return;
                try {
                    killSync(process.pid, 'SIGTERM', true);
                    ps.kill(process.pid);
                } catch (err) {
                    console.error(err);
                }
            });
    }
}
export default new NativePHP();
