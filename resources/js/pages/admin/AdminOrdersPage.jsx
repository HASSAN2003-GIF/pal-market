import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { DocNumber, Money, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';
import AdminTable from './AdminTable';

export default function AdminOrdersPage() {
    const t = useT();
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const orders = useQuery({
        queryKey: ['admin', 'orders', query],
        queryFn: () => api.admin.orders(query),
        placeholderData: keepPreviousData,
    });

    const columns = [
        { key: 'number', label: t('orders.order'), render: (o) => <DocNumber className="text-sm text-ink">{o.order_number}</DocNumber> },
        { key: 'buyer', label: t('nav.role.buyer'), render: (o) => o.buyer_profile?.business_name },
        { key: 'supplier', label: t('nav.role.supplier'), render: (o) => o.supplier?.business_name },
        { key: 'created', label: t('admin.colCreated'), render: (o) => formatDate(o.created_at) },
        { key: 'total', label: t('common.total'), align: 'right', render: (o) => <Money amount={o.total_amount} currency={o.currency} className="font-semibold" /> },
        { key: 'status', label: t('common.status'), render: (o) => <StatusBadge kind="order" status={o.status} /> },
    ];

    return (
        <>
            <PageHeader title={t('nav.adminOrders')} description={t('admin.ordersDescription')} />
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
