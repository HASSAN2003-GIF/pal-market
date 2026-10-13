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

const ACTIONS = {
    pending: { action: 'approve', label: 'Approve', body: 'They will be able to list products, record stock and send quotations.' },
    approved: { action: 'suspend', label: 'Suspend', danger: true, body: 'They will keep read access but cannot change their catalog, quote, or move orders forward.' },
    suspended: { action: 'reactivate', label: 'Reactivate', body: 'They can trade again immediately.' },
};

export default function AdminSuppliersPage() {
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const suppliers = useQuery({ queryKey: ['admin', 'suppliers', query], queryFn: () => api.admin.suppliers(query), placeholderData: keepPreviousData });
    const action = useAdminAction();

    const columns = [
        {
            key: 'business',
            label: 'Business',
            render: (s) => (
                <span>
                    <span className="block font-semibold">{s.business_name}</span>
                    <span className="block text-muted">TIN {s.tin_number || 'not provided'}</span>
                </span>
            ),
        },
        {
            key: 'contact',
            label: 'Contact',
            render: (s) => (
                <span>
                    <span className="block">{s.user?.name}</span>
                    <span className="block text-muted">{s.user?.email}</span>
                </span>
            ),
        },
        { key: 'activity', label: 'Activity', render: (s) => `${s.supplier_products_count} products, ${s.quotations_count} quotes, ${s.purchase_orders_count} orders` },
        { key: 'joined', label: 'Joined', render: (s) => formatDate(s.created_at) },
        { key: 'status', label: 'Status', render: (s) => <StatusBadge kind="supplier" status={s.status} /> },
        {
            key: 'actions',
            label: 'Action',
            align: 'right',
            render: (s) => {
                const next = ACTIONS[s.status];
                return (
                    <Button
                        size="sm"
                        variant={next.danger ? 'danger' : s.status === 'pending' ? 'primary' : 'secondary'}
                        onClick={() =>
                            action.ask({
                                title: `${next.label} ${s.business_name}?`,
                                body: <p>{next.body}</p>,
                                label: next.label,
                                danger: next.danger,
                                run: () => api.admin.supplierAction(s.id, next.action),
                            })
                        }
                    >
                        {next.label}
                    </Button>
                );
            },
        },
    ];

    return (
        <>
            <PageHeader title="Suppliers" description="Review new suppliers before they can trade, and pause those who break the rules." />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="supplier" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput className="lg:w-72" label="Search suppliers" placeholder="Business, TIN or email" value={params.search ?? ''} onChange={(search) => update({ search })} />
            </div>
            <QueryBoundary query={suppliers}>
                {({ suppliers: rows, meta }) => (
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
