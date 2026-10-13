import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import LineItemList from '../../components/LineItemList';
import { Button, ConfirmDialog, DocNumber, Money, Notice, PageHeader, Panel, QueryBoundary, StatusBadge, cx, useToast } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, formatNumber } from '../../lib/format';
import { ORDER_ACTIONS, ORDER_STEPS, orderActionLabel, statusLabel } from '../../lib/statuses';
import { regionLabel } from '../../location/regions';

export default function OrderDetailPage() {
    const { orderId } = useParams();
    const order = useQuery({ queryKey: ['purchase-order', orderId], queryFn: () => api.purchaseOrder(orderId) });

    return <QueryBoundary query={order}>{({ purchase_order: data }) => <OrderBody order={data} />}</QueryBoundary>;
}

function OrderBody({ order }) {
    const { t, locale } = useI18n();
    const { user } = useAuth();
    const toast = useToast();
    const queryClient = useQueryClient();
    const [confirming, setConfirming] = useState(null);

    const side = user?.supplier?.id === order.supplier_id ? 'supplier' : 'buyer';
    const next = ORDER_ACTIONS[order.status];
    const myTurn = next?.actor === side;
    const canCancel = side === 'buyer' && ['pending', 'confirmed', 'processing'].includes(order.status);

    const transition = useMutation({
        mutationFn: (action) => api.transitionPurchaseOrder(order.id, action),
        onSuccess: ({ purchase_order: updated }) => {
            queryClient.invalidateQueries({ queryKey: ['purchase-order', String(order.id)] });
            queryClient.invalidateQueries({ queryKey: ['purchase-orders'] });
            queryClient.invalidateQueries({ queryKey: ['summary'] });
            setConfirming(null);
            toast(t('orders.updatedToast', { status: statusLabel('order', updated.status) }));
        },
    });

    const request = order.supplier_quotation?.buyer_request;
    const counterpart = side === 'supplier' ? order.buyer_profile?.business_name : order.supplier?.business_name;
    const notGiven = t('buyer.notGiven');

    return (
        <>
            <PageHeader
                back={{ to: '/orders', label: t('nav.orders') }}
                title={request?.title ?? t('orders.order')}
                actions={
                    <>
                        {canCancel && (
                            <Button variant="danger" onClick={() => setConfirming('cancel')}>
                                {t('orders.cancel')}
                            </Button>
                        )}
                        {myTurn && (
                            <Button className="w-full sm:w-auto" onClick={() => setConfirming(next.action)}>
                                {orderActionLabel(next.action)}
                            </Button>
                        )}
                    </>
                }
            />
            <div className="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                <DocNumber className="text-lg">{order.order_number}</DocNumber>
                <StatusBadge kind="order" status={order.status} />
                <span className="text-sm text-muted">
                    {side === 'supplier' ? t('nav.role.buyer') : t('nav.role.supplier')}: <span className="font-semibold text-ink">{counterpart}</span>
                </span>
            </div>

            {next && !myTurn && order.status !== 'cancelled' && (
                <Notice
                    tone="info"
                    className="mb-6"
                    title={next.actor === 'supplier' ? t('orders.waitingForSeller', { action: orderActionLabel(next.action) }) : t('orders.waitingForBuyer', { action: orderActionLabel(next.action) })}
                />
            )}
            {myTurn && <Notice tone="attention" className="mb-6" title={t('orders.yourTurn', { action: orderActionLabel(next.action) })} />}
            {transition.error && <Notice tone="bad" className="mb-6" title={transition.error.message} />}

            <Timeline status={order.status} />

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <Panel title={t('orders.items')} bodyClassName="p-0 sm:p-0">
                    <LineItemList
                        className="sm:hidden"
                        currency={order.currency}
                        rows={order.items.map((item) => ({
                            key: item.id,
                            name: item.product?.name,
                            quantity: formatNumber(item.quantity),
                            unit: item.unit,
                            unitPrice: item.unit_price,
                            total: item.total_price,
                        }))}
                    />
                    <dl className="space-y-1 border-t border-rule px-4 py-3 text-sm sm:hidden">
                        <div className="flex justify-between">
                            <dt className="text-muted">{t('orders.subtotal')}</dt>
                            <dd><Money amount={order.subtotal} currency={order.currency} /></dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted">{t('orders.delivery')}</dt>
                            <dd><Money amount={order.delivery_fee} currency={order.currency} /></dd>
                        </div>
                        <div className="flex items-baseline justify-between border-t border-rule pt-2">
                            <dt className="font-semibold">{t('orders.total')}</dt>
                            <dd><Money amount={order.total_amount} currency={order.currency} className="text-[1.2rem] font-extrabold" /></dd>
                        </div>
                    </dl>
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full text-sm">
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
                                {order.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-2.5 sm:px-5">{item.product?.name}</td>
                                        <td className="tabular px-4 py-2.5 text-right">
                                            {formatNumber(item.quantity)} {item.unit}
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            <Money amount={item.unit_price} currency={order.currency} />
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-semibold sm:px-5">
                                            <Money amount={item.total_price} currency={order.currency} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t border-rule">
                                <tr>
                                    <th scope="row" colSpan={3} className="px-4 py-2 text-right font-normal text-muted">
                                        {t('orders.subtotal')}
                                    </th>
                                    <td className="px-4 py-2 text-right sm:px-5">
                                        <Money amount={order.subtotal} currency={order.currency} />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" colSpan={3} className="px-4 py-2 text-right font-normal text-muted">
                                        {t('orders.delivery')}
                                    </th>
                                    <td className="px-4 py-2 text-right sm:px-5">
                                        <Money amount={order.delivery_fee} currency={order.currency} />
                                    </td>
                                </tr>
                                <tr className="border-t border-rule">
                                    <th scope="row" colSpan={3} className="px-4 py-3 text-right font-semibold">
                                        {t('orders.total')}
                                    </th>
                                    <td className="px-4 py-3 text-right sm:px-5">
                                        <Money amount={order.total_amount} currency={order.currency} className="text-[1.3rem] font-extrabold" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Panel>

                <Panel title={t('orders.delivery')}>
                    <dl className="space-y-2.5 text-sm">
                        <div>
                            <dt className="text-muted">{t('buyer.fieldRegion')}</dt>
                            <dd className="font-semibold">{request?.delivery_region ? regionLabel(request.delivery_region, locale) : notGiven}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">{t('buyer.fieldAddress')}</dt>
                            <dd className="font-semibold">{request?.delivery_address || notGiven}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">{t('buyer.fieldNeededBy')}</dt>
                            <dd className="font-semibold">{request?.needed_by ? formatDate(request.needed_by) : notGiven}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">{t('orders.fromRequest')}</dt>
                            <dd>
                                <DocNumber className="text-sm">{request?.request_number}</DocNumber>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted">{t('orders.offerNumber')}</dt>
                            <dd>
                                <DocNumber className="text-sm">{order.supplier_quotation?.quotation_number}</DocNumber>
                            </dd>
                        </div>
                    </dl>
                    {order.notes && (
                        <p className="mt-4 border-t border-rule pt-3 text-sm text-ink-soft">
                            {t('orders.sellerTerms')} {order.notes}
                        </p>
                    )}
                    <p className="mt-4 border-t border-rule pt-3 text-xs text-muted">{t('orders.paymentNote')}</p>
                </Panel>
            </div>

            <ConfirmDialog
                open={Boolean(confirming)}
                title={confirming === 'cancel' ? t('orders.cancelDialogTitle') : t('orders.confirmStepTitle', { action: next ? orderActionLabel(next.action) : '' })}
                confirmLabel={confirming === 'cancel' ? t('orders.cancel') : next ? orderActionLabel(next.action) : ''}
                confirmVariant={confirming === 'cancel' ? 'danger' : 'primary'}
                busy={transition.isPending}
                error={transition.error?.message}
                onCancel={() => setConfirming(null)}
                onConfirm={() => transition.mutate(confirming)}
            >
                <p>{confirming === 'cancel' ? t('orders.cancelDialogBody', { name: counterpart ?? '' }) : t('orders.confirmStepBody', { name: counterpart ?? '' })}</p>
            </ConfirmDialog>
        </>
    );
}

function Timeline({ status }) {
    const t = useI18n().t;
    if (status === 'cancelled') {
        return <Notice tone="bad" title={t('orders.wasCancelled')} />;
    }
    const current = ORDER_STEPS.indexOf(status);

    return (
        <ol className="grid grid-cols-3 gap-2 sm:grid-cols-6" aria-label={t('orders.progress')}>
            {ORDER_STEPS.map((step, index) => {
                const done = index <= current;
                return (
                    <li key={step} aria-current={index === current ? 'step' : undefined}>
                        <span className={cx('block h-1.5 rounded-full', done ? 'bg-action' : 'bg-concrete-deep')} />
                        <span className={cx('mt-1.5 block text-xs sm:text-sm', index === current ? 'font-semibold text-ink' : 'text-muted')}>{statusLabel('order', step)}</span>
                    </li>
                );
            })}
        </ol>
    );
}
