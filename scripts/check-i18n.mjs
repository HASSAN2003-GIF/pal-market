#!/usr/bin/env node
/**
 * Checks the English and Kiswahili interface dictionaries:
 *  - every key exists in both languages (plural keys count as one key),
 *  - placeholders such as {name} match between the two,
 *  - every literal t('…') / translate('…') key used in resources/js exists.
 * Run with `npm run check:i18n`. Exits 1 when something is wrong.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
const localesDir = join(root, 'resources/js/i18n/locales');
const sourceDir = join(root, 'resources/js');

function loadLocale(lang) {
    const dir = join(localesDir, lang);
    const messages = {};
    for (const file of readdirSync(dir)) {
        if (!file.endsWith('.js') || file === 'index.js') continue;
        const ns = file.replace(/\.js$/, '');
        const body = readFileSync(join(dir, file), 'utf8').replace(/^\s*export default\s*/m, 'return ');
        const object = new Function(body)();
        for (const [key, text] of Object.entries(object)) messages[`${ns}.${key}`] = text;
    }
    return messages;
}

const base = (key) => key.replace(/_(zero|one|two|few|many|other)$/, '');
const placeholders = (text) => [...String(text).matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort().join(',');

const en = loadLocale('en');
const sw = loadLocale('sw');
const problems = [];

const enBases = new Set(Object.keys(en).map(base));
const swBases = new Set(Object.keys(sw).map(base));
for (const key of enBases) if (!swBases.has(key)) problems.push(`Missing in sw: ${key}`);
for (const key of swBases) if (!enBases.has(key)) problems.push(`Only in sw (remove or add to en): ${key}`);

for (const [key, text] of Object.entries(en)) {
    if (key in sw && placeholders(text) !== placeholders(sw[key])) {
        problems.push(`Placeholders differ for ${key}: en {${placeholders(text)}} vs sw {${placeholders(sw[key])}}`);
    }
    if (typeof text !== 'string' || !text.trim()) problems.push(`Empty text: en ${key}`);
}
for (const [key, text] of Object.entries(sw)) if (typeof text !== 'string' || !text.trim()) problems.push(`Empty text: sw ${key}`);

function* sourceFiles(dir) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) {
            if (!path.endsWith('/i18n')) yield* sourceFiles(path);
        } else if (/\.(jsx?|mjs)$/.test(entry)) yield path;
    }
}

let used = 0;
for (const file of sourceFiles(sourceDir)) {
    const text = readFileSync(file, 'utf8');
    for (const match of text.matchAll(/\b(?:t|translate)\(\s*'([a-zA-Z0-9_.]+)'/g)) {
        used += 1;
        const key = match[1];
        if (!enBases.has(key)) problems.push(`Unknown key ${key} in ${relative(root, file)}`);
    }
    // Keys referenced as plain strings in lookup tables, e.g. ['x', 'ns.key'].
    for (const match of text.matchAll(/'((?:common|layout|nav|search|location|status|home|materials|product|auth|account|help|buyer|orders|supplier|admin)\.[a-zA-Z0-9_.]+)'/g)) {
        const key = match[1];
        if (!enBases.has(key) && !/\.$/.test(key)) problems.push(`Unknown key ${key} in ${relative(root, file)}`);
    }
}

const unique = [...new Set(problems)];
if (unique.length) {
    console.error(unique.join('\n'));
    console.error(`\n${unique.length} i18n problem(s).`);
    process.exit(1);
}
console.log(`i18n OK: ${enBases.size} keys in English and Kiswahili, ${used} uses checked.`);
