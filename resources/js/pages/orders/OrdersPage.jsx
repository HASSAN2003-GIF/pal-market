import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import SearchInput from '../../components/SearchInput';
import { DocNumber, EmptyState, Money, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';

export default function OrdersPage() {
    const t = useT();
    const { user } = useAuth();
    const isSupplier = user?.role === 'supplier';
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const orders = useQuery({
        queryKey: ['purchase-orders', query],
        queryFn: () => api.purchaseOrders(query),
        placeholderData: keepPreviousData,
    });

    return (
        <>
            <PageHeader title={t('nav.orders')} description={isSupplier ? t('orders.sellerDescription') : t('orders.buyerDescription')} />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="order" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-64"
                    label={t('orders.searchLabel')}
                    placeholder={t('orders.searchPlaceholder')}
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
            </div>
            <QueryBoundary query={orders}>
                {({ data: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title={params.status || params.search ? t('orders.noneMatch') : t('orders.noneYet')}>
                            {isSupplier ? t('orders.noneYetSeller') : t('orders.noneYetBuyer')}
                        </EmptyState>
                    ) : (
                        <>
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((order) => (
                                    <li key={order.id}>
                                        <Link to={`/orders/${order.id}`} className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:gap-6 sm:px-5">
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold">{order.supplier_quotation?.buyer_request?.title ?? t('orders.order')}</span>
                                                <span className="flex flex-wrap gap-x-3 text-sm text-muted">
                                                    <DocNumber className="text-sm">{order.order_number}</DocNumber>
                                                    <span>{isSupplier ? order.buyer_profile?.business_name : order.supplier?.business_name}</span>
                                                    <span>{formatDate(order.created_at)}</span>
                                                </span>
                                            </span>
                                            <Money amount={order.total_amount} currency={order.currency} className="font-semibold sm:text-right" />
                                            <StatusBadge kind="order" status={order.status} />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <Pagination meta={meta} onPage={(page) => update({ page })} />
                        </>
                    )
                }
            </QueryBoundary>
        </>
    );
}
