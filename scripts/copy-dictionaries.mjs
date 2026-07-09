import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const LANGUAGES = [
    ['de', 'de_DE'],
    ['en', 'en_US'],
    ['es', 'es_ES'],
    ['fr', 'fr_FR'],
    ['it', 'it_IT'],
    ['nl', 'nl_NL'],
    ['pt', 'pt_PT'],
    ['sv', 'sv_SE'],
];

for (const [lang, locale] of LANGUAGES) {
    const pkg = join(root, 'node_modules', `dictionary-${lang}`);
    const out = join(root, 'public', 'dictionaries', lang);
    mkdirSync(out, { recursive: true });
    copyFileSync(join(pkg, 'index.aff'), join(out, `${locale}.aff`));
    copyFileSync(join(pkg, 'index.dic'), join(out, `${locale}.dic`));
    console.log(`dictionaries: ${lang} -> ${locale}`);
}
