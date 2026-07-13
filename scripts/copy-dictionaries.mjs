import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const LANGUAGES = [
    ['ca', 'ca_ES'],
    ['cs', 'cs_CZ'],
    ['da', 'da_DK'],
    ['de', 'de_DE'],
    ['en', 'en_US'],
    ['es', 'es_ES'],
    ['et', 'et_EE'],
    ['fr', 'fr_FR'],
    ['ga', 'ga_IE'],
    ['hr', 'hr_HR'],
    ['hu', 'hu_HU'],
    ['is', 'is_IS'],
    ['it', 'it_IT'],
    ['lb', 'lb_LU'],
    ['lt', 'lt_LT'],
    ['lv', 'lv_LV'],
    ['nb', 'nb_NO'],
    ['nl', 'nl_NL'],
    ['nn', 'nn_NO'],
    ['pl', 'pl_PL'],
    ['pt', 'pt_PT', 'pt-pt'],
    ['ro', 'ro_RO'],
    ['sk', 'sk_SK'],
    ['sl', 'sl_SI'],
    ['sv', 'sv_SE'],
    ['tr', 'tr_TR'],
];

for (const [lang, locale, packageCode = lang] of LANGUAGES) {
    const pkg = join(root, 'node_modules', `dictionary-${packageCode}`);
    const out = join(root, 'public', 'dictionaries', lang);
    mkdirSync(out, { recursive: true });
    copyFileSync(join(pkg, 'index.aff'), join(out, `${locale}.aff`));
    copyFileSync(join(pkg, 'index.dic'), join(out, `${locale}.dic`));
    const license = join(pkg, 'license');
    if (existsSync(license)) {
        copyFileSync(license, join(out, 'LICENSE'));
    }
    console.log(`dictionaries: ${lang} -> ${locale}`);
}
