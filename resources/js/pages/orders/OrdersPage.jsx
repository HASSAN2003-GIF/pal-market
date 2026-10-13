import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import SearchInput from '../../components/SearchInput';
import { DocNumber, EmptyState, Money, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';

export default function OrdersPage() {
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
            <PageHeader
                title="Purchase orders"
                description={isSupplier ? 'Orders buyers have placed with you. Confirm and dispatch them here.' : 'Orders raised from accepted quotations.'}
            />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="order" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput className="lg:w-64" label="Search orders" placeholder="Order number" value={params.search ?? ''} onChange={(search) => update({ search })} />
            </div>
            <QueryBoundary query={orders}>
                {({ data: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title={params.status || params.search ? 'No orders match' : 'No purchase orders yet'}>
                            {isSupplier
                                ? 'When a buyer accepts one of your quotations, the order appears here.'
                                : 'Accept a quotation on one of your requests to raise a purchase order.'}
                        </EmptyState>
                    ) : (
                        <>
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((order) => (
                                    <li key={order.id}>
                                        <Link to={`/orders/${order.id}`} className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:gap-6 sm:px-5">
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold">
                                                    {order.supplier_quotation?.buyer_request?.title ?? 'Purchase order'}
                                                </span>
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
