const moneyFormatters = {};

export function formatMoney(amount, currency = 'TZS') {
    if (amount === null || amount === undefined || amount === '') return '—';
    moneyFormatters[currency] ??= new Intl.NumberFormat('en-TZ', {
        style: 'currency',
        currency,
        maximumFractionDigits: currency === 'TZS' ? 0 : 2,
    });
    return moneyFormatters[currency].format(Number(amount));
}

export function formatNumber(value) {
    return new Intl.NumberFormat('en-TZ').format(Number(value ?? 0));
}

export function formatDate(value, options = { day: 'numeric', month: 'short', year: 'numeric' }) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('en-GB', options);
}

export function formatDateTime(value) {
    return formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** "in 3 days", "2 hours ago" */
export function relativeTime(value) {
    if (!value) return '';
    const diff = new Date(value).getTime() - Date.now();
    const units = [
        ['day', 86400000],
        ['hour', 3600000],
        ['minute', 60000],
    ];
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    for (const [unit, ms] of units) {
        if (Math.abs(diff) >= ms || unit === 'minute') return rtf.format(Math.round(diff / ms), unit);
    }
    return '';
}

export function pluralize(count, singular, plural = `${singular}s`) {
    return `${formatNumber(count)} ${count === 1 ? singular : plural}`;
}

export const TANZANIA_REGIONS = [
    'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera', 'Katavi', 'Kigoma', 'Kilimanjaro',
    'Lindi', 'Manyara', 'Mara', 'Mbeya', 'Morogoro', 'Mtwara', 'Mwanza', 'Njombe', 'Pemba North',
    'Pemba South', 'Pwani', 'Rukwa', 'Ruvuma', 'Shinyanga', 'Simiyu', 'Singida', 'Songwe', 'Tabora',
    'Tanga', 'Unguja North', 'Unguja South', 'Mjini Magharibi',
];
