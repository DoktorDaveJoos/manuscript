import {app} from 'electron'
import NativePHP from '#plugin'
import path from 'path'

// Inherit User's PATH in Process & ChildProcess
import fixPath from 'fix-path';
// Manuscript patch: fixPath() runs `$SHELL -ilc env` synchronously with no
// timeout before the app is even ready — ~0.5s per launch with a typical
// zsh setup, and a shell config that blocks keeps the app from ever
// starting. Packaged Manuscript spawns only absolute paths (bundled PHP,
// /bin/launchctl, /usr/bin/plutil), so only development needs the PATH.
if (!app.isPackaged) {
    fixPath();
}

const buildPath = path.resolve(import.meta.dirname, import.meta.env.MAIN_VITE_NATIVEPHP_BUILD_PATH);
const defaultIcon = path.join(buildPath, 'icon.png')
const certificate = path.join(buildPath, 'cacert.pem')

const executable = process.platform === 'win32' ? 'php.exe' : 'php';
const phpBinary = path.join(buildPath,'php', executable);
const appPath = path.join(buildPath, 'app')

/**
 * Turn on the lights for the NativePHP app.
 */
NativePHP.bootstrap(
    app,
    defaultIcon,
    phpBinary,
    certificate,
    appPath
);
