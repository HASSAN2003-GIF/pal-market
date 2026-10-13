import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { Button, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';
import AdminActionDialog from './AdminActionDialog';
import AdminTable from './AdminTable';
import { useAdminAction } from './useAdminAction';

export default function AdminBuyersPage() {
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const buyers = useQuery({ queryKey: ['admin', 'buyers', query], queryFn: () => api.admin.buyers(query), placeholderData: keepPreviousData });
    const action = useAdminAction();

    const columns = [
        {
            key: 'business',
            label: 'Business',
            render: (b) => (
                <span>
                    <span className="block font-semibold">{b.business_name}</span>
                    {b.business_type && <span className="block text-muted">{b.business_type}</span>}
                </span>
            ),
        },
        {
            key: 'contact',
            label: 'Contact',
            render: (b) => (
                <span>
                    <span className="block">{b.user?.name}</span>
                    <span className="block text-muted">{b.user?.email}</span>
                </span>
            ),
        },
        { key: 'activity', label: 'Activity', render: (b) => `${b.requests_count} requests, ${b.purchase_orders_count} orders` },
        { key: 'joined', label: 'Joined', render: (b) => formatDate(b.created_at) },
        { key: 'status', label: 'Status', render: (b) => <StatusBadge kind="buyer" status={b.status} /> },
        {
            key: 'actions',
            label: 'Action',
            align: 'right',
            render: (b) =>
                b.status === 'active' ? (
                    <Button
                        size="sm"
                        variant="danger"
                        onClick={() =>
                            action.ask({
                                title: `Suspend ${b.business_name}?`,
                                body: <p>They keep read access to their history but cannot create requests, accept quotations or move orders forward.</p>,
                                label: 'Suspend buyer',
                                danger: true,
                                run: () => api.admin.buyerAction(b.id, 'suspend'),
                            })
                        }
                    >
                        Suspend
                    </Button>
                ) : (
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                            action.ask({
                                title: `Reactivate ${b.business_name}?`,
                                body: <p>They can trade again immediately.</p>,
                                label: 'Reactivate buyer',
                                run: () => api.admin.buyerAction(b.id, 'reactivate'),
                            })
                        }
                    >
                        Reactivate
                    </Button>
                ),
        },
    ];

    return (
        <>
            <PageHeader title="Buyers" description="Business profiles that request quotations." />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="buyer" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput className="lg:w-72" label="Search buyers" placeholder="Business, name or email" value={params.search ?? ''} onChange={(search) => update({ search })} />
            </div>
            <QueryBoundary query={buyers}>
                {({ buyers: rows, meta }) => (
                    <>
                        <AdminTable columns={columns} rows={rows} />
                        <Pagination meta={meta} onPage={(page) => update({ page })} />
                    </>
                )}
            </QueryBoundary>
            <AdminActionDialog action={action} />
        </>
    );
}
