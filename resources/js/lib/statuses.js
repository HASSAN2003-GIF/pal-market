import { translate } from '../i18n';

/**
 * Tones for every status the API returns, so the same state reads the same
 * everywhere. Labels and hints live in the dictionaries under
 * status.<kind>.<status> and status.<kind>.<status>Hint.
 * tone: neutral | info | progress | good | bad | attention
 */
export const STATUS = {
    request: { draft: 'neutral', open: 'info', closed: 'good', cancelled: 'bad', expired: 'neutral' },
    quotation: { draft: 'neutral', submitted: 'info', accepted: 'good', rejected: 'bad', expired: 'neutral', cancelled: 'neutral' },
    order: {
        pending: 'attention',
        confirmed: 'info',
        processing: 'progress',
        shipped: 'progress',
        delivered: 'good',
        completed: 'good',
        cancelled: 'bad',
    },
    supplier: { pending: 'attention', approved: 'good', suspended: 'bad' },
    buyer: { active: 'good', suspended: 'bad' },
};

/** Statuses that have a hint line in the dictionary. */
const HINTS = { request: ['draft', 'open', 'closed', 'cancelled', 'expired'] };

export function statusInfo(kind, status) {
    const tone = STATUS[kind]?.[status];
    if (!tone) return { label: status ?? translate('common.unknown'), tone: 'neutral' };
    return {
        label: translate(`status.${kind}.${status}`),
        tone,
        hint: HINTS[kind]?.includes(status) ? translate(`status.${kind}.${status}Hint`) : undefined,
    };
}

/** Label for a status used in filters and selects. */
export function statusLabel(kind, status) {
    return statusInfo(kind, status).label;
}

/** The steps an order moves through, in order (cancelled sits outside). */
export const ORDER_STEPS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed'];

/** Which side acts next on an order. Labels: status.action.<action>. */
export const ORDER_ACTIONS = {
    pending: { actor: 'supplier', action: 'confirm' },
    confirmed: { actor: 'supplier', action: 'process' },
    processing: { actor: 'supplier', action: 'ship' },
    shipped: { actor: 'buyer', action: 'deliver' },
    delivered: { actor: 'buyer', action: 'complete' },
};

export function orderActionLabel(action) {
    return translate(`status.action.${action}`);
}
