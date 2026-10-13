import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { DocNumber, Money, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';
import AdminTable from './AdminTable';

export default function AdminOrdersPage() {
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const orders = useQuery({
        queryKey: ['admin', 'orders', query],
        queryFn: () => api.admin.orders(query),
        placeholderData: keepPreviousData,
    });

    const columns = [
        { key: 'number', label: 'Order', render: (o) => <DocNumber className="text-sm text-ink">{o.order_number}</DocNumber> },
        { key: 'buyer', label: 'Buyer', render: (o) => o.buyer_profile?.business_name },
        { key: 'supplier', label: 'Supplier', render: (o) => o.supplier?.business_name },
        { key: 'created', label: 'Raised', render: (o) => formatDate(o.created_at) },
        { key: 'total', label: 'Total', align: 'right', render: (o) => <Money amount={o.total_amount} currency={o.currency} className="font-semibold" /> },
        { key: 'status', label: 'Status', render: (o) => <StatusBadge kind="order" status={o.status} /> },
    ];

    return (
        <>
            <PageHeader title="Purchase orders" description="All orders raised between buyers and suppliers. Read-only." />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="order" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-64"
                    label="Search orders"
                    placeholder="Order number"
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
            </div>
            <QueryBoundary query={orders}>
                {({ data: rows, meta }) => (
                    <>
                        <AdminTable columns={columns} rows={rows} />
                        <Pagination meta={meta} onPage={(page) => update({ page })} />
                    </>
                )}
            </QueryBoundary>
        </>
    );
}
