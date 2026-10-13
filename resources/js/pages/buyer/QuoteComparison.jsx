import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Button, ConfirmDialog, DocNumber, EmptyState, Money, StatusBadge, cx, useToast } from '../../components/ui';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, formatMoney, formatNumber } from '../../lib/format';

/**
 * Price offers side by side. An offer that prices every requested item is
 * "complete"; the lowest complete total is marked.
 */
export default function QuoteComparison({ request }) {
    const t = useT();
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
                toast(t('buyer.orderCreatedToast', { number: data.purchase_order.order_number }));
                navigate(`/orders/${data.purchase_order.id}`);
            } else {
                toast(t('buyer.declinedToast'));
            }
        },
    });

    if (quotes.length === 0) {
        return (
            <EmptyState title={request.status === 'open' ? t('buyer.noOffersYet') : t('buyer.noOffersReceived')}>
                {request.status === 'open' ? t('buyer.noOffersYetBody', { date: formatDate(request.expires_at) }) : t('buyer.noOffersReceivedBody')}
            </EmptyState>
        );
    }

    return (
        <>
            {live.length > 1 && <p className="mb-3 text-sm text-muted">{t('buyer.compareTip')}</p>}
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
                                    <p className="text-[1.25rem] leading-tight font-extrabold">{quote.supplier?.business_name}</p>
                                    <p className="flex flex-wrap gap-x-3 text-sm text-muted">
                                        <DocNumber className="text-sm">{quote.quotation_number}</DocNumber>
                                        {quote.valid_until && <span>{t('buyer.validUntil', { date: formatDate(quote.valid_until) })}</span>}
                                        {!isComplete(quote) && <span className="font-semibold text-warn">{t('buyer.someItemsOnly')}</span>}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Money amount={quote.total_amount} currency={quote.currency} className="text-[1.6rem] leading-none font-extrabold" />
                                    <p className="mt-1 text-sm text-muted">{t('buyer.inclDelivery', { amount: formatMoney(quote.delivery_fee, quote.currency) })}</p>
                                    {lowest && <p className="mt-1 text-sm font-semibold text-warn">{t('buyer.lowestComplete')}</p>}
                                </div>
                            </div>

                            <div className="overflow-x-auto border-t border-rule">
                                <table className="w-full min-w-[30rem] text-sm">
                                    <caption className="sr-only">{t('buyer.pricesFrom', { name: quote.supplier?.business_name })}</caption>
                                    <thead className="bg-concrete text-left text-muted">
                                        <tr>
                                            <th scope="col" className="px-4 py-2 font-semibold sm:px-5">
                                                {t('buyer.colItem')}
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right font-semibold">
                                                {t('buyer.colQuantity')}
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right font-semibold">
                                                {t('buyer.colUnitPrice')}
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right font-semibold sm:px-5">
                                                {t('buyer.colLineTotal')}
                                            </th>
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
                                                                {line.quantity !== item.quantity && (
                                                                    <span className="block text-xs text-warn">{t('buyer.youAskedFor', { n: formatNumber(item.quantity) })}</span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-2 text-right">
                                                                <Money amount={line.unit_price} />
                                                            </td>
                                                            <td className="px-4 py-2 text-right font-semibold sm:px-5">
                                                                <Money amount={line.total_price} />
                                                            </td>
                                                        </>
                                                    ) : (
                                                        <td colSpan={3} className="px-4 py-2 text-right text-muted sm:px-5">
                                                            {t('buyer.notPriced')}
                                                        </td>
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
                                            {t('buyer.decline')}
                                        </Button>
                                        <Button size="sm" onClick={() => setDecision({ quote, kind: 'accept' })}>
                                            {t('buyer.accept')}
                                        </Button>
                                    </div>
                                )}
                                {quote.purchase_order && (
                                    <Link to={`/orders/${quote.purchase_order.id}`} className="text-sm font-semibold text-pal-700 hover:underline">
                                        {t('buyer.viewOrder', { number: quote.purchase_order.order_number })}
                                    </Link>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>

            <ConfirmDialog
                open={decision?.kind === 'accept'}
                title={t('buyer.acceptDialogTitle')}
                confirmLabel={t('buyer.acceptDialogConfirm')}
                busy={decide.isPending}
                error={decide.error?.message}
                onCancel={() => {
                    decide.reset();
                    setDecision(null);
                }}
                onConfirm={() => decide.mutate(decision)}
            >
                <p>
                    {t('buyer.acceptDialogBody', {
                        amount: formatMoney(decision?.quote.total_amount, decision?.quote.currency),
                        name: decision?.quote.supplier?.business_name ?? '',
                    })}
                </p>
            </ConfirmDialog>

            <ConfirmDialog
                open={decision?.kind === 'reject'}
                title={t('buyer.declineDialogTitle')}
                confirmLabel={t('buyer.declineDialogConfirm')}
                confirmVariant="danger"
                busy={decide.isPending}
                error={decide.error?.message}
                onCancel={() => {
                    decide.reset();
                    setDecision(null);
                }}
                onConfirm={() => decide.mutate(decision)}
            >
                <p>{t('buyer.declineDialogBody', { name: decision?.quote.supplier?.business_name ?? '' })}</p>
            </ConfirmDialog>
        </>
    );
}
