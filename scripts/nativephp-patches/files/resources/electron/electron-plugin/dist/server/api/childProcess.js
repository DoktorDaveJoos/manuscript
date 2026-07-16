var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
import express from 'express';
import { utilityProcess } from 'electron';
import state from '../state.js';
import { notifyLaravel } from '../utils.js';
import { getAppPath, getDefaultEnvironmentVariables, getDefaultPhpIniSettings, runningSecureBuild, } from '../php.js';
import killSync from 'kill-sync';
import { fileURLToPath } from 'url';
import { join } from 'path';
const router = express.Router();
const startingProcesses = new Map();
function notifyStartupError(alias, error) {
    console.error(`Failed to start process [${alias}]: ${error.message}`);
    notifyLaravel('events', {
        event: 'Native\\Desktop\\Events\\ChildProcess\\StartupError',
        payload: {
            alias,
            error: error.toString(),
        },
    });
}
function startProcess(settings_1) {
    return __awaiter(this, arguments, void 0, function* (settings, useNodeRuntime = false) {
        var _a;
        const { alias, cmd, cwd, env, persistent } = settings;
        const spawnTimeout = (_a = settings.spawnTimeout) !== null && _a !== void 0 ? _a : (alias.startsWith('queue_') ? 10000 : 30000);
        if (getProcess(alias) !== undefined) {
            return state.processes[alias];
        }
        if (startingProcesses.has(alias)) {
            return startingProcesses.get(alias);
        }
        const startupPromise = new Promise((resolve, reject) => {
            let proc;
            let startTimeout;
            let startupSettled = false;
            let didBecomeReady = false;
            const failStartup = (error) => {
                if (startupSettled) {
                    return;
                }
                startupSettled = true;
                clearTimeout(startTimeout);
                notifyStartupError(alias, error);
                try {
                    proc.kill();
                }
                catch (_error) {
                }
                reject(error);
            };
            try {
                proc = utilityProcess.fork(fileURLToPath(new URL('../../electron-plugin/dist/server/childProcess.js', import.meta.url)), cmd, {
                    cwd,
                    stdio: 'pipe',
                    serviceName: alias,
                    env: Object.assign(Object.assign(Object.assign({}, process.env), env), { USE_NODE_RUNTIME: useNodeRuntime ? '1' : '0' }),
                });
            }
            catch (error) {
                notifyStartupError(alias, error);
                reject(error);
                return;
            }
            startTimeout = setTimeout(() => {
                failStartup(new Error(`Process [${alias}] did not acknowledge startup within ${spawnTimeout}ms.`));
            }, spawnTimeout);
            proc.stdout.on('data', (data) => {
                notifyLaravel('events', {
                    event: 'Native\\Desktop\\Events\\ChildProcess\\MessageReceived',
                    payload: {
                        alias,
                        data: data.toString(),
                    },
                });
            });
            proc.stderr.on('data', (data) => {
                console.error('Process [' + alias + '] ERROR:', data.toString().trim());
                notifyLaravel('events', {
                    event: 'Native\\Desktop\\Events\\ChildProcess\\ErrorReceived',
                    payload: {
                        alias,
                        data: data.toString(),
                    },
                });
            });
            proc.on('spawn', () => {
                console.log('Process wrapper [' + alias + '] spawned.');
            });
            proc.on('message', (message) => {
                if ((message === null || message === void 0 ? void 0 : message.type) === 'nativephp-child-process-startup-error') {
                    failStartup(new Error(message.error ||
                        `Process [${alias}] failed before startup.`));
                    return;
                }
                if ((message === null || message === void 0 ? void 0 : message.type) !== 'nativephp-child-process-ready' ||
                    startupSettled) {
                    return;
                }
                startupSettled = true;
                didBecomeReady = true;
                clearTimeout(startTimeout);
                console.log('Process [' + alias + '] acknowledged startup.');
                state.processes[alias] = {
                    pid: proc.pid,
                    proc,
                    settings,
                };
                notifyLaravel('events', {
                    event: 'Native\\Desktop\\Events\\ChildProcess\\ProcessSpawned',
                    payload: [alias, proc.pid],
                });
                resolve(state.processes[alias]);
            });
            proc.on('error', (type, location) => {
                if (!didBecomeReady) {
                    failStartup(new Error(`Process wrapper [${alias}] failed with ${type} at ${location}.`));
                }
            });
            proc.on('exit', (code) => {
                clearTimeout(startTimeout);
                if (!didBecomeReady) {
                    failStartup(new Error(`Process [${alias}] exited with code [${code}] before acknowledging startup.`));
                    return;
                }
                console.log(`Process [${alias}] exited with code [${code}].`);
                notifyLaravel('events', {
                    event: 'Native\\Desktop\\Events\\ChildProcess\\ProcessExited',
                    payload: {
                        alias,
                        code,
                    },
                });
                const settings = Object.assign({}, getSettings(alias));
                delete state.processes[alias];
                if (settings === null || settings === void 0 ? void 0 : settings.persistent) {
                    console.log('Process [' + alias + '] watchdog restarting...');
                    setTimeout(() => {
                        startProcess(settings).catch((error) => {
                            console.error(`Persistent process [${alias}] restart failed:`, error);
                        });
                    }, 1000);
                }
            });
        });
        startingProcesses.set(alias, startupPromise);
        try {
            return yield startupPromise;
        }
        finally {
            if (startingProcesses.get(alias) === startupPromise) {
                startingProcesses.delete(alias);
            }
        }
    });
}
function startPhpProcess(settings) {
    const defaultEnv = getDefaultEnvironmentVariables(state.randomSecret, state.electronApiPort);
    const customIniSettings = settings.iniSettings || {};
    const iniSettings = Object.assign(Object.assign(Object.assign({}, getDefaultPhpIniSettings()), state.phpIni), customIniSettings);
    const iniArgs = Object.keys(iniSettings)
        .map((key) => {
        return ['-d', `${key}=${iniSettings[key]}`];
    })
        .flat();
    if (settings.cmd[0] === 'artisan' && runningSecureBuild()) {
        settings.cmd.unshift(join(getAppPath(), 'build', '__nativephp_app_bundle'));
    }
    settings = Object.assign(Object.assign({}, settings), { cmd: [state.php, ...iniArgs, ...settings.cmd], env: Object.assign(Object.assign({}, settings.env), defaultEnv) });
    return startProcess(settings);
}
function stopProcess(alias) {
    const proc = getProcess(alias);
    if (proc === undefined) {
        return;
    }
    state.processes[alias].settings.persistent = false;
    console.log('Process [' + alias + '] stopping with PID [' + proc.pid + '].');
    killSync(proc.pid, 'SIGTERM', true);
    proc.kill();
}
export function stopAllProcesses() {
    for (const alias in state.processes) {
        stopProcess(alias);
    }
}
function getProcess(alias) {
    var _a;
    return (_a = state.processes[alias]) === null || _a === void 0 ? void 0 : _a.proc;
}
function getSettings(alias) {
    var _a;
    return (_a = state.processes[alias]) === null || _a === void 0 ? void 0 : _a.settings;
}
function serializeProcess(runtimeProcess) {
    var _a;
    if (!runtimeProcess) {
        return null;
    }
    return Object.assign({ pid: (_a = runtimeProcess.pid) !== null && _a !== void 0 ? _a : null, settings: runtimeProcess.settings }, (runtimeProcess.error ? { error: runtimeProcess.error } : {}));
}
function serializeProcesses(processes) {
    return Object.fromEntries(Object.entries(processes).map(([alias, runtimeProcess]) => [
        alias,
        serializeProcess(runtimeProcess),
    ]));
}
router.post('/start', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    try {
        const proc = yield startProcess(req.body);
        res.json(serializeProcess(proc));
    }
    catch (error) {
        res.status(503).json({ error: error.message });
    }
}));
router.post('/start-node', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    try {
        const proc = yield startProcess(req.body, true);
        res.json(serializeProcess(proc));
    }
    catch (error) {
        res.status(503).json({ error: error.message });
    }
}));
router.post('/start-php', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    try {
        const proc = yield startPhpProcess(req.body);
        res.json(serializeProcess(proc));
    }
    catch (error) {
        res.status(503).json({ error: error.message });
    }
}));
router.post('/stop', (req, res) => {
    const { alias } = req.body;
    stopProcess(alias);
    res.sendStatus(200);
});
router.post('/restart', (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    const { alias } = req.body;
    const existingSettings = getSettings(alias);
    if (existingSettings === undefined) {
        res.sendStatus(410);
        return;
    }
    const settings = Object.assign({}, existingSettings);
    stopProcess(alias);
    const waitForProcessDeletion = (timeout, retry) => __awaiter(void 0, void 0, void 0, function* () {
        const start = Date.now();
        while (state.processes[alias] !== undefined) {
            if (Date.now() - start > timeout) {
                return;
            }
            yield new Promise((resolve) => setTimeout(resolve, retry));
        }
    });
    yield waitForProcessDeletion(5000, 100);
    console.log('Process [' + alias + '] restarting...');
    try {
        const proc = yield startProcess(settings);
        res.json(serializeProcess(proc));
    }
    catch (error) {
        res.status(503).json({ error: error.message });
    }
}));
router.get('/get/:alias', (req, res) => {
    const { alias } = req.params;
    const proc = state.processes[alias];
    if (proc === undefined) {
        res.sendStatus(410);
        return;
    }
    res.json(serializeProcess(proc));
});
router.get('/', (req, res) => {
    res.json(serializeProcesses(state.processes));
});
router.post('/message', (req, res) => {
    const { alias, message } = req.body;
    const proc = getProcess(alias);
    if (proc === undefined) {
        res.sendStatus(200);
        return;
    }
    proc.postMessage(message);
    res.sendStatus(200);
});
export default router;
