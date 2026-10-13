import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Button, ConfirmDialog, DocNumber, Money, Notice, PageHeader, Panel, QueryBoundary, StatusBadge, cx, useToast } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../lib/api';
import { formatDate, formatNumber } from '../../lib/format';
import { ORDER_ACTIONS, ORDER_STEPS, statusInfo } from '../../lib/statuses';

export default function OrderDetailPage() {
    const { orderId } = useParams();
    const order = useQuery({ queryKey: ['purchase-order', orderId], queryFn: () => api.purchaseOrder(orderId) });

    return <QueryBoundary query={order}>{({ purchase_order: data }) => <OrderBody order={data} />}</QueryBoundary>;
}

function OrderBody({ order }) {
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
            toast(`Order ${statusInfo('order', updated.status).label.toLowerCase()}`);
        },
    });

    const request = order.supplier_quotation?.buyer_request;
    const counterpart = side === 'supplier' ? order.buyer_profile?.business_name : order.supplier?.business_name;

    return (
        <>
            <PageHeader
                back={{ to: '/orders', label: 'Purchase orders' }}
                title={request?.title ?? 'Purchase order'}
                actions={
                    <>
                        {canCancel && (
                            <Button variant="danger" onClick={() => setConfirming('cancel')}>
                                Cancel order
                            </Button>
                        )}
                        {myTurn && <Button onClick={() => setConfirming(next.action)}>{next.label}</Button>}
                    </>
                }
            />
            <div className="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                <DocNumber className="text-lg">{order.order_number}</DocNumber>
                <StatusBadge kind="order" status={order.status} />
                <span className="text-sm text-muted">
                    {side === 'supplier' ? 'Buyer' : 'Supplier'}: <span className="font-semibold text-ink">{counterpart}</span>
                </span>
            </div>

            {next && !myTurn && order.status !== 'cancelled' && (
                <Notice tone="info" className="mb-6" title={`Waiting for the ${next.actor} to ${next.label.toLowerCase()}`} />
            )}
            {transition.error && <Notice tone="bad" className="mb-6" title={transition.error.message} />}

            <Timeline status={order.status} />

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <Panel title="Items" bodyClassName="p-0 sm:p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[30rem] text-sm">
                            <thead className="bg-concrete text-left text-muted">
                                <tr>
                                    <th scope="col" className="px-4 py-2 font-semibold sm:px-5">Item</th>
                                    <th scope="col" className="px-4 py-2 text-right font-semibold">Quantity</th>
                                    <th scope="col" className="px-4 py-2 text-right font-semibold">Unit price</th>
                                    <th scope="col" className="px-4 py-2 text-right font-semibold sm:px-5">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-rule">
                                {order.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-2.5 sm:px-5">{item.product?.name}</td>
                                        <td className="tabular px-4 py-2.5 text-right">
                                            {formatNumber(item.quantity)} {item.unit}
                                        </td>
                                        <td className="px-4 py-2.5 text-right"><Money amount={item.unit_price} currency={order.currency} /></td>
                                        <td className="px-4 py-2.5 text-right font-semibold sm:px-5"><Money amount={item.total_price} currency={order.currency} /></td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t border-rule">
                                <tr>
                                    <th scope="row" colSpan={3} className="px-4 py-2 text-right font-normal text-muted">Subtotal</th>
                                    <td className="px-4 py-2 text-right sm:px-5"><Money amount={order.subtotal} currency={order.currency} /></td>
                                </tr>
                                <tr>
                                    <th scope="row" colSpan={3} className="px-4 py-2 text-right font-normal text-muted">Delivery</th>
                                    <td className="px-4 py-2 text-right sm:px-5"><Money amount={order.delivery_fee} currency={order.currency} /></td>
                                </tr>
                                <tr className="border-t border-rule">
                                    <th scope="row" colSpan={3} className="px-4 py-3 text-right font-semibold">Order total</th>
                                    <td className="px-4 py-3 text-right sm:px-5">
                                        <Money amount={order.total_amount} currency={order.currency} className="font-display text-[1.4rem] font-semibold" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Panel>

                <Panel title="Delivery">
                    <dl className="space-y-2.5 text-sm">
                        <div>
                            <dt className="text-muted">Region</dt>
                            <dd className="font-semibold">{request?.delivery_region || 'Not given'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">Address</dt>
                            <dd className="font-semibold">{request?.delivery_address || 'Not given'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">Needed by</dt>
                            <dd className="font-semibold">{request?.needed_by ? formatDate(request.needed_by) : 'Not given'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">From request</dt>
                            <dd><DocNumber className="text-sm">{request?.request_number}</DocNumber></dd>
                        </div>
                        <div>
                            <dt className="text-muted">Quotation</dt>
                            <dd><DocNumber className="text-sm">{order.supplier_quotation?.quotation_number}</DocNumber></dd>
                        </div>
                    </dl>
                    {order.notes && <p className="mt-4 border-t border-rule pt-3 text-sm text-ink-soft">Supplier terms: {order.notes}</p>}
                    <p className="mt-4 border-t border-rule pt-3 text-xs text-muted">
                        Payment is arranged directly between buyer and supplier. PAL Market does not process payments.
                    </p>
                </Panel>
            </div>

            <ConfirmDialog
                open={Boolean(confirming)}
                title={confirming === 'cancel' ? 'Cancel this order?' : `${ORDER_ACTIONS[order.status]?.label ?? ''}?`}
                confirmLabel={confirming === 'cancel' ? 'Cancel order' : ORDER_ACTIONS[order.status]?.label}
                confirmVariant={confirming === 'cancel' ? 'danger' : 'primary'}
                busy={transition.isPending}
                error={transition.error?.message}
                onCancel={() => setConfirming(null)}
                onConfirm={() => transition.mutate(confirming)}
            >
                <p>
                    {confirming === 'cancel'
                        ? `${counterpart} will see the order as cancelled. This cannot be undone.`
                        : `${counterpart} will see this update on the order.`}
                </p>
            </ConfirmDialog>
        </>
    );
}

function Timeline({ status }) {
    if (status === 'cancelled') {
        return <Notice tone="bad" title="This order was cancelled" />;
    }
    const current = ORDER_STEPS.indexOf(status);

    return (
        <ol className="grid grid-cols-3 gap-2 sm:grid-cols-6" aria-label="Order progress">
            {ORDER_STEPS.map((step, index) => {
                const done = index <= current;
                return (
                    <li key={step} aria-current={index === current ? 'step' : undefined}>
                        <span className={cx('block h-1.5 rounded-full', done ? 'bg-pal-600' : 'bg-concrete-deep')} />
                        <span className={cx('mt-1.5 block text-xs sm:text-sm', index === current ? 'font-semibold text-ink' : 'text-muted')}>
                            {statusInfo('order', step).label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
