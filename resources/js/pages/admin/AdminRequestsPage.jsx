import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { DocNumber, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';
import AdminTable from './AdminTable';

export default function AdminRequestsPage() {
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const requests = useQuery({
        queryKey: ['admin', 'requests', query],
        queryFn: () => api.admin.requests(query),
        placeholderData: keepPreviousData,
    });

    const columns = [
        {
            key: 'title',
            label: 'Request',
            render: (r) => (
                <span>
                    <span className="block font-semibold">{r.title}</span>
                    <DocNumber className="text-sm">{r.request_number}</DocNumber>
                </span>
            ),
        },
        { key: 'buyer', label: 'Buyer', render: (r) => r.buyer_profile?.business_name },
        { key: 'region', label: 'Delivery', render: (r) => r.delivery_region || 'Not given' },
        { key: 'counts', label: 'Items / quotes', render: (r) => `${r.items_count} / ${r.submitted_quotations_count}` },
        { key: 'dates', label: 'Closes', render: (r) => (r.expires_at ? formatDate(r.expires_at) : r.status === 'draft' ? 'Not published' : 'No closing date') },
        { key: 'status', label: 'Status', render: (r) => <StatusBadge kind="request" status={r.status} /> },
    ];

    return (
        <>
            <PageHeader title="Requests" description="Every buyer request on the marketplace. Read-only." />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="request" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-72"
                    label="Search requests"
                    placeholder="Title or request number"
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
            </div>
            <QueryBoundary query={requests}>
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
