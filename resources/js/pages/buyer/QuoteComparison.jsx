import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Button, ConfirmDialog, DocNumber, EmptyState, Money, StatusBadge, cx, useToast } from '../../components/ui';
import { api } from '../../lib/api';
import { formatDate, formatNumber } from '../../lib/format';

/**
 * Quotations side by side. A quote that prices every requested line is
 * "complete"; the lowest complete total is marked as the lowest bid.
 */
export default function QuoteComparison({ request }) {
    const quotes = request.quotations ?? [];
    const requested = request.items ?? [];
    const [decision, setDecision] = useState(null); // { quote, kind: 'accept' | 'reject' }
    const navigate = useNavigate();
    const toast = useToast();
    const queryClient = useQueryClient();

    const isComplete = (quote) => requested.every((item) => quote.items.some((line) => line.product_id === item.product_id));
    const live = quotes.filter((q) => q.status === 'submitted' || q.status === 'accepted');
    const complete = live.filter(isComplete);
    const lowestId = complete.length > 1 ? complete.reduce((a, b) => (Number(a.total_amount) <= Number(b.total_amount) ? a : b)).id : null;

    const decide = useMutation({
        mutationFn: ({ quote, kind }) => (kind === 'accept' ? api.acceptQuotation(quote.id, true) : api.rejectQuotation(quote.id)),
        onSuccess: (data, { kind }) => {
            queryClient.invalidateQueries({ queryKey: ['buyer-request', String(request.id)] });
            queryClient.invalidateQueries({ queryKey: ['buyer-requests'] });
            queryClient.invalidateQueries({ queryKey: ['summary'] });
            setDecision(null);
            if (kind === 'accept' && data.purchase_order) {
                toast(`Purchase order ${data.purchase_order.order_number} created`);
                navigate(`/orders/${data.purchase_order.id}`);
            } else {
                toast('Quotation declined');
            }
        },
    });

    if (quotes.length === 0) {
        return (
            <EmptyState title={request.status === 'open' ? 'No quotations yet' : 'No quotations were received'}>
                {request.status === 'open'
                    ? `Suppliers can quote until ${formatDate(request.expires_at)}. New quotations appear here.`
                    : 'This request closed without quotations.'}
            </EmptyState>
        );
    }

    return (
        <>
            <ul className="space-y-3">
                {quotes.map((quote) => {
                    const lowest = quote.id === lowestId;
                    const canDecide = request.status === 'open' && quote.status === 'submitted';
                    return (
                        <li
                            key={quote.id}
                            className={cx(
                                'overflow-hidden rounded-[var(--radius-panel)] border bg-paper',
                                lowest ? 'border-safety ring-2 ring-safety' : 'border-rule',
                                !['submitted', 'accepted'].includes(quote.status) && 'opacity-75',
                            )}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3 px-4 py-3 sm:px-5">
                                <div className="min-w-0">
                                    <p className="font-display text-[1.45rem] leading-tight font-semibold">{quote.supplier?.business_name}</p>
                                    <p className="flex flex-wrap gap-x-3 text-sm text-muted">
                                        <DocNumber className="text-sm">{quote.quotation_number}</DocNumber>
                                        {quote.valid_until && <span>Valid until {formatDate(quote.valid_until)}</span>}
                                        {!isComplete(quote) && <span className="font-semibold text-warn">Prices some items only</span>}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Money amount={quote.total_amount} currency={quote.currency} className="font-display text-[1.8rem] leading-none font-semibold" />
                                    <p className="mt-1 text-sm text-muted">
                                        incl. delivery <Money amount={quote.delivery_fee} currency={quote.currency} />
                                    </p>
                                    {lowest && <p className="mt-1 text-sm font-semibold text-warn">Lowest complete quote</p>}
                                </div>
                            </div>

                            <div className="overflow-x-auto border-t border-rule">
                                <table className="w-full min-w-[30rem] text-sm">
                                    <caption className="sr-only">Line prices from {quote.supplier?.business_name}</caption>
                                    <thead className="bg-concrete text-left text-muted">
                                        <tr>
                                            <th scope="col" className="px-4 py-2 font-semibold sm:px-5">Item</th>
                                            <th scope="col" className="px-4 py-2 text-right font-semibold">Quantity</th>
                                            <th scope="col" className="px-4 py-2 text-right font-semibold">Unit price</th>
                                            <th scope="col" className="px-4 py-2 text-right font-semibold sm:px-5">Line total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-rule">
                                        {requested.map((item) => {
                                            const line = quote.items.find((l) => l.product_id === item.product_id);
                                            return (
                                                <tr key={item.id}>
                                                    <td className="px-4 py-2 sm:px-5">{item.product?.name}</td>
                                                    {line ? (
                                                        <>
                                                            <td className="tabular px-4 py-2 text-right">
                                                                {formatNumber(line.quantity)} {line.unit}
                                                                {line.quantity !== item.quantity && <span className="block text-xs text-warn">You asked for {formatNumber(item.quantity)}</span>}
                                                            </td>
                                                            <td className="px-4 py-2 text-right"><Money amount={line.unit_price} /></td>
                                                            <td className="px-4 py-2 text-right font-semibold sm:px-5"><Money amount={line.total_price} /></td>
                                                        </>
                                                    ) : (
                                                        <td colSpan={3} className="px-4 py-2 text-right text-muted sm:px-5">Not quoted</td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {quote.notes && <p className="border-t border-rule px-4 py-2.5 text-sm text-ink-soft sm:px-5">“{quote.notes}”</p>}

                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-concrete/60 px-4 py-2.5 sm:px-5">
                                <StatusBadge kind="quotation" status={quote.status} />
                                {canDecide && (
                                    <div className="flex gap-2">
                                        <Button size="sm" variant="secondary" onClick={() => setDecision({ quote, kind: 'reject' })}>
                                            Decline
                                        </Button>
                                        <Button size="sm" onClick={() => setDecision({ quote, kind: 'accept' })}>
                                            Accept and order
                                        </Button>
                                    </div>
                                )}
                                {quote.purchase_order && (
                                    <Link to={`/orders/${quote.purchase_order.id}`} className="text-sm font-semibold text-pal-700 hover:underline">
                                        View order {quote.purchase_order.order_number}
                                    </Link>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>

            <ConfirmDialog
                open={decision?.kind === 'accept'}
                title="Accept this quotation?"
                confirmLabel="Accept and create order"
                busy={decide.isPending}
                error={decide.error?.message}
                onCancel={() => {
                    decide.reset();
                    setDecision(null);
                }}
                onConfirm={() => decide.mutate(decision)}
            >
                <p>
                    You will raise a purchase order for <Money amount={decision?.quote.total_amount} className="font-semibold" /> with{' '}
                    {decision?.quote.supplier?.business_name}. The request closes and other quotations are declined.
                </p>
            </ConfirmDialog>

            <ConfirmDialog
                open={decision?.kind === 'reject'}
                title="Decline this quotation?"
                confirmLabel="Decline quotation"
                confirmVariant="danger"
                busy={decide.isPending}
                error={decide.error?.message}
                onCancel={() => {
                    decide.reset();
                    setDecision(null);
                }}
                onConfirm={() => decide.mutate(decision)}
            >
                <p>{decision?.quote.supplier?.business_name} will see that you declined. This cannot be undone.</p>
            </ConfirmDialog>
        </>
    );
}
