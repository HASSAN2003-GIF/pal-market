/**
 * Plain-language names and tones for every status the API returns, so the
 * same state reads the same everywhere.
 * tone: neutral | info | progress | good | bad | attention
 */
export const STATUS = {
    request: {
        draft: { label: 'Draft', tone: 'neutral', hint: 'Only you can see this request.' },
        open: { label: 'Open for quotes', tone: 'info', hint: 'Approved suppliers can send quotations.' },
        closed: { label: 'Quote accepted', tone: 'good', hint: 'You accepted a quotation.' },
        cancelled: { label: 'Cancelled', tone: 'bad', hint: 'Withdrawn from the market.' },
        expired: { label: 'Expired', tone: 'neutral', hint: 'The quoting window ended.' },
    },
    quotation: {
        draft: { label: 'Draft', tone: 'neutral' },
        submitted: { label: 'Awaiting decision', tone: 'info' },
        accepted: { label: 'Accepted', tone: 'good' },
        rejected: { label: 'Declined', tone: 'bad' },
        expired: { label: 'Expired', tone: 'neutral' },
        cancelled: { label: 'Withdrawn', tone: 'neutral' },
    },
    order: {
        pending: { label: 'Awaiting supplier', tone: 'attention' },
        confirmed: { label: 'Confirmed', tone: 'info' },
        processing: { label: 'Being prepared', tone: 'progress' },
        shipped: { label: 'On the way', tone: 'progress' },
        delivered: { label: 'Delivered', tone: 'good' },
        completed: { label: 'Completed', tone: 'good' },
        cancelled: { label: 'Cancelled', tone: 'bad' },
    },
    supplier: {
        pending: { label: 'Awaiting approval', tone: 'attention' },
        approved: { label: 'Approved', tone: 'good' },
        suspended: { label: 'Suspended', tone: 'bad' },
    },
    buyer: {
        active: { label: 'Active', tone: 'good' },
        suspended: { label: 'Suspended', tone: 'bad' },
    },
};

export function statusInfo(kind, status) {
    return STATUS[kind]?.[status] ?? { label: status ?? 'Unknown', tone: 'neutral' };
}

/** The steps an order moves through, in order (cancelled sits outside). */
export const ORDER_STEPS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed'];

/** Which side acts next on an order, and what the action is called. */
export const ORDER_ACTIONS = {
    pending: { actor: 'supplier', action: 'confirm', label: 'Confirm order' },
    confirmed: { actor: 'supplier', action: 'process', label: 'Start preparing' },
    processing: { actor: 'supplier', action: 'ship', label: 'Mark as dispatched' },
    shipped: { actor: 'buyer', action: 'deliver', label: 'Confirm delivery' },
    delivered: { actor: 'buyer', action: 'complete', label: 'Complete order' },
};
