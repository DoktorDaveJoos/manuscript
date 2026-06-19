import express from "express";
import electronUpdater from 'electron-updater';
const { autoUpdater } = electronUpdater;
import { notifyLaravel } from "../utils.js";
// Manuscript patch: disable silent install-on-quit.
// electron-updater defaults autoInstallOnAppQuit=true, which stages a Squirrel
// install that runs when the app quits WITHOUT relaunching
// (launchAfterInstallation=false). If the user reopens the app during that
// install window, the running instance blocks ShipIt ("App Still Running
// Error"), launchd respawns ShipIt, and it loops forever burning CPU while the
// update never applies. Updates now apply only via the explicit Install action
// (/quit-and-install -> quitAndInstall), which relaunches the app afterwards
// (autoRunAppAfterInstall=true). See StaleUpdateGuard for the boot-time backstop.
autoUpdater.autoInstallOnAppQuit = false;
const router = express.Router();
router.post("/check-for-updates", (req, res) => {
    autoUpdater.checkForUpdates();
    res.sendStatus(200);
});
router.post("/download-update", (req, res) => {
    autoUpdater.downloadUpdate();
    res.sendStatus(200);
});
router.post("/quit-and-install", (req, res) => {
    autoUpdater.quitAndInstall();
    res.sendStatus(200);
});
autoUpdater.addListener("checking-for-update", () => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\CheckingForUpdate`,
    });
});
autoUpdater.addListener("update-available", (event) => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\UpdateAvailable`,
        payload: {
            version: event.version,
            files: event.files,
            releaseDate: event.releaseDate,
            releaseName: event.releaseName,
            releaseNotes: event.releaseNotes,
            stagingPercentage: event.stagingPercentage,
            minimumSystemVersion: event.minimumSystemVersion,
        },
    });
});
autoUpdater.addListener("update-not-available", (event) => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\UpdateNotAvailable`,
        payload: {
            version: event.version,
            files: event.files,
            releaseDate: event.releaseDate,
            releaseName: event.releaseName,
            releaseNotes: event.releaseNotes,
            stagingPercentage: event.stagingPercentage,
            minimumSystemVersion: event.minimumSystemVersion,
        },
    });
});
autoUpdater.addListener("error", (error) => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\Error`,
        payload: {
            name: error.name,
            message: error.message,
            stack: error.stack,
        },
    });
});
autoUpdater.addListener("download-progress", (progressInfo) => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\DownloadProgress`,
        payload: {
            total: progressInfo.total,
            delta: progressInfo.delta,
            transferred: progressInfo.transferred,
            percent: progressInfo.percent,
            bytesPerSecond: progressInfo.bytesPerSecond,
        },
    });
});
autoUpdater.addListener("update-downloaded", (event) => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\UpdateDownloaded`,
        payload: {
            downloadedFile: event.downloadedFile,
            version: event.version,
            files: event.files,
            releaseDate: event.releaseDate,
            releaseName: event.releaseName,
            releaseNotes: event.releaseNotes,
            stagingPercentage: event.stagingPercentage,
            minimumSystemVersion: event.minimumSystemVersion,
        },
    });
});
autoUpdater.addListener("update-cancelled", (event) => {
    notifyLaravel("events", {
        event: `\\Native\\Desktop\\Events\\AutoUpdater\\UpdateCancelled`,
        payload: {
            version: event.version,
            files: event.files,
            releaseDate: event.releaseDate,
            releaseName: event.releaseName,
            releaseNotes: event.releaseNotes,
            stagingPercentage: event.stagingPercentage,
            minimumSystemVersion: event.minimumSystemVersion,
        },
    });
});
export default router;
