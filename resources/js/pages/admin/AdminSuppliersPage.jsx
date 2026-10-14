import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { Button, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';
import AdminActionDialog from './AdminActionDialog';
import AdminTable from './AdminTable';
import { useAdminAction } from './useAdminAction';

const ACTIONS = {
    pending: { action: 'approve', label: 'admin.approve', body: 'admin.approveBody' },
    approved: { action: 'suspend', label: 'admin.suspend', danger: true, body: 'admin.suspendSellerBody' },
    suspended: { action: 'reactivate', label: 'admin.reactivate', body: 'admin.reactivateBody' },
};

export default function AdminSuppliersPage() {
    const t = useT();
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const suppliers = useQuery({ queryKey: ['admin', 'suppliers', query], queryFn: () => api.admin.suppliers(query), placeholderData: keepPreviousData });
    const action = useAdminAction();

    const columns = [
        {
            key: 'business',
            label: t('admin.colBusiness'),
            render: (s) => (
                <span>
                    <span className="block font-semibold">{s.business_name}</span>
                    <span className="block text-muted">{s.tin_number ? t('admin.tinValue', { tin: s.tin_number }) : t('admin.tinMissing')}</span>
                </span>
            ),
        },
        {
            key: 'contact',
            label: t('admin.colContact'),
            render: (s) => (
                <span>
                    <span className="block">{s.user?.name}</span>
                    <span className="block text-muted">{s.user?.email}</span>
                </span>
            ),
        },
        {
            key: 'activity',
            label: t('admin.colActivity'),
            render: (s) => t('admin.sellerActivity', { products: s.supplier_products_count, offers: s.quotations_count, orders: s.purchase_orders_count }),
        },
        { key: 'joined', label: t('admin.colJoined'), render: (s) => formatDate(s.created_at) },
        { key: 'status', label: t('common.status'), render: (s) => <StatusBadge kind="supplier" status={s.status} /> },
        {
            key: 'actions',
            label: t('common.actions'),
            align: 'right',
            render: (s) => {
                const next = ACTIONS[s.status];
                return (
                    <Button
                        size="sm"
                        variant={next.danger ? 'danger' : s.status === 'pending' ? 'primary' : 'secondary'}
                        onClick={() =>
                            action.ask({
                                title: t('admin.actionTitle', { action: t(next.label), name: s.business_name }),
                                body: <p>{t(next.body)}</p>,
                                label: t(next.label),
                                danger: next.danger,
                                run: () => api.admin.supplierAction(s.id, next.action),
                            })
                        }
                    >
                        {t(next.label)}
                    </Button>
                );
            },
        },
    ];

    return (
        <>
            <PageHeader title={t('nav.adminSellers')} description={t('admin.sellersDescription')} />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="supplier" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-72"
                    label={t('admin.searchSellers')}
                    placeholder={t('admin.searchSellersPlaceholder')}
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
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
