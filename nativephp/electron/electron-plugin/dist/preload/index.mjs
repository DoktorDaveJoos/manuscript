import remote from "@electron/remote";
import { ipcRenderer, contextBridge, webFrame } from "electron";
const Native = {
    on: (event, callback) => {
        ipcRenderer.on('native-event', (_, data) => {
            event = event.replace(/^(\\)+/, '');
            data.event = data.event.replace(/^(\\)+/, '');
            if (event === data.event) {
                return callback(data.payload, event);
            }
        });
    },
    contextMenu: (template) => {
        let menu = remote.Menu.buildFromTemplate(template);
        menu.popup({ window: remote.getCurrentWindow() });
    }
};
contextBridge.exposeInMainWorld('Native', Native);
const Spellcheck = {
    isWordMisspelled: (word) => webFrame.isWordMisspelled(word),
    getWordSuggestions: (word) => webFrame.getWordSuggestions(word),
    setLanguages: (languages) => {
        try {
            remote.session.defaultSession.setSpellCheckerLanguages(languages);
        }
        catch (e) {
            console.error('[Spellcheck] setLanguages failed:', e);
        }
    },
    addToDictionary: (word) => {
        try {
            remote.session.defaultSession.addWordToSpellCheckerDictionary(word);
        }
        catch (e) {
            console.error('[Spellcheck] addToDictionary failed:', e);
        }
    },
};
contextBridge.exposeInMainWorld('Spellcheck', Spellcheck);
ipcRenderer.on('log', (event, { level, message, context }) => {
    if (level === 'error') {
        console.error(`[${level}] ${message}`, context);
    }
    else if (level === 'warn') {
        console.warn(`[${level}] ${message}`, context);
    }
    else {
        console.log(`[${level}] ${message}`, context);
    }
});
ipcRenderer.on('native-event', (event, data) => {
    data.event = data.event.replace(/^(\\)+/, '');
    window.postMessage({
        type: 'native-event',
        event: data.event,
        payload: data.payload
    }, '*');
});
contextBridge.exposeInMainWorld('native:initialized', (function () {
    window.dispatchEvent(new CustomEvent('native:init'));
    return true;
})());
