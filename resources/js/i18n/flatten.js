/** { ns: { key: text } } -> { 'ns.key': text } */
export function flatten(namespaces) {
    const out = {};
    for (const [ns, messages] of Object.entries(namespaces)) {
        for (const [key, text] of Object.entries(messages)) out[`${ns}.${key}`] = text;
    }
    return out;
}
