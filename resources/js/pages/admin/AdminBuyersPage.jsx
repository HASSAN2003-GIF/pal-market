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

export default function AdminBuyersPage() {
    const t = useT();
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const buyers = useQuery({ queryKey: ['admin', 'buyers', query], queryFn: () => api.admin.buyers(query), placeholderData: keepPreviousData });
    const action = useAdminAction();

    const columns = [
        {
            key: 'business',
            label: t('admin.colBusiness'),
            render: (b) => (
                <span>
                    <span className="block font-semibold">{b.business_name}</span>
                    {b.business_type && <span className="block text-muted">{b.business_type}</span>}
                </span>
            ),
        },
        {
            key: 'contact',
            label: t('admin.colContact'),
            render: (b) => (
                <span>
                    <span className="block">{b.user?.name}</span>
                    <span className="block text-muted">{b.user?.email}</span>
                </span>
            ),
        },
        { key: 'activity', label: t('admin.colActivity'), render: (b) => t('admin.buyerActivity', { requests: b.requests_count, orders: b.purchase_orders_count }) },
        { key: 'joined', label: t('admin.colJoined'), render: (b) => formatDate(b.created_at) },
        { key: 'status', label: t('common.status'), render: (b) => <StatusBadge kind="buyer" status={b.status} /> },
        {
            key: 'actions',
            label: t('common.actions'),
            align: 'right',
            render: (b) =>
                b.status === 'active' ? (
                    <Button
                        size="sm"
                        variant="danger"
                        onClick={() =>
                            action.ask({
                                title: t('admin.actionTitle', { action: t('admin.suspend'), name: b.business_name }),
                                body: <p>{t('admin.suspendBuyerBody')}</p>,
                                label: t('admin.suspend'),
                                danger: true,
                                run: () => api.admin.buyerAction(b.id, 'suspend'),
                            })
                        }
                    >
                        {t('admin.suspend')}
                    </Button>
                ) : (
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                            action.ask({
                                title: t('admin.actionTitle', { action: t('admin.reactivate'), name: b.business_name }),
                                body: <p>{t('admin.reactivateBody')}</p>,
                                label: t('admin.reactivate'),
                                run: () => api.admin.buyerAction(b.id, 'reactivate'),
                            })
                        }
                    >
                        {t('admin.reactivate')}
                    </Button>
                ),
        },
    ];

    return (
        <>
            <PageHeader title={t('nav.adminBuyers')} description={t('admin.buyersDescription')} />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="buyer" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-72"
                    label={t('admin.searchBuyers')}
                    placeholder={t('admin.searchBuyersPlaceholder')}
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
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
