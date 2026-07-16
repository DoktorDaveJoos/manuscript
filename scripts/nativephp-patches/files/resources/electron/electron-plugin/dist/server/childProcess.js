import { spawn, fork } from 'child_process';
const useNodeRuntime = process.env.USE_NODE_RUNTIME === '1';
const [command, ...args] = process.argv.slice(2);
const proc = useNodeRuntime
    ? fork(command, args, {
        stdio: ['pipe', 'pipe', 'pipe', 'ipc'],
        execPath: process.execPath,
    })
    : spawn(command, args);
proc.once('spawn', () => {
    process.parentPort.postMessage({
        type: 'nativephp-child-process-ready',
    });
});
proc.once('error', (error) => {
    process.parentPort.postMessage({
        type: 'nativephp-child-process-startup-error',
        error: error.message,
    });
});
process.parentPort.on('message', (message) => {
    proc.stdin.write(message.data);
});
proc.stdout.on('data', (data) => {
    console.log(data.toString());
});
proc.stderr.on('data', (data) => {
    console.error(data.toString());
});
proc.on('close', (code) => {
    process.exit(code !== null && code !== void 0 ? code : 1);
});
