import { getLocale, intlLocale, translate } from '../i18n';
import { REGION_NAMES } from '../location/regions';

/*
 * Numbers, money and dates in the interface language (en-TZ / sw-TZ).
 * Components re-render on language change because they read t() from context.
 */
const cache = new Map();
function formatter(kind, options) {
    const key = `${getLocale()}|${kind}|${JSON.stringify(options)}`;
    if (!cache.has(key)) {
        const locale = intlLocale();
        cache.set(
            key,
            kind === 'number'
                ? new Intl.NumberFormat(locale, options)
                : kind === 'relative'
                  ? new Intl.RelativeTimeFormat(locale, options)
                  : new Intl.DateTimeFormat(locale, options),
        );
    }
    return cache.get(key);
}

export function formatMoney(amount, currency = 'TZS') {
    if (amount === null || amount === undefined || amount === '') return '—';
    return formatter('number', { style: 'currency', currency, maximumFractionDigits: currency === 'TZS' ? 0 : 2 }).format(Number(amount));
}

export function formatNumber(value) {
    return formatter('number', {}).format(Number(value ?? 0));
}

export function formatDate(value, options = { day: 'numeric', month: 'short', year: 'numeric' }) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : formatter('date', options).format(date);
}

export function formatDateTime(value) {
    return formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** "in 3 days", "2 hours ago" / "baada ya siku 3", "saa 2 zilizopita" */
export function relativeTime(value) {
    if (!value) return '';
    const diff = new Date(value).getTime() - Date.now();
    const units = [
        ['day', 86400000],
        ['hour', 3600000],
        ['minute', 60000],
    ];
    for (const [unit, ms] of units) {
        if (Math.abs(diff) >= ms || unit === 'minute') return formatter('relative', { numeric: 'auto' }).format(Math.round(diff / ms), unit);
    }
    return '';
}

/** "3 items" via the dictionary: pluralize(3, 'common.items') reads common.items_one / _other with {n}. */
export function pluralize(count, key) {
    return translate(key, { count, n: formatNumber(count) });
}

/** Region values as stored by the API. Use regionLabel() for display. */
export const TANZANIA_REGIONS = REGION_NAMES;
