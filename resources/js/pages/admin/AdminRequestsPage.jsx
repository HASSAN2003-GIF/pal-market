import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { DocNumber, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { regionLabel } from '../../location/regions';
import { StatusTabs } from '../buyer/BuyerRequestsPage';
import AdminTable from './AdminTable';

export default function AdminRequestsPage() {
    const { t, locale } = useI18n();
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
            label: t('admin.colRequest'),
            render: (r) => (
                <span>
                    <span className="block font-semibold">{r.title}</span>
                    <DocNumber className="text-sm">{r.request_number}</DocNumber>
                </span>
            ),
        },
        { key: 'buyer', label: t('nav.role.buyer'), render: (r) => r.buyer_profile?.business_name },
        { key: 'region', label: t('orders.delivery'), render: (r) => (r.delivery_region ? regionLabel(r.delivery_region, locale) : t('buyer.notGiven')) },
        { key: 'counts', label: t('admin.colItemsOffers'), render: (r) => `${r.items_count} / ${r.submitted_quotations_count}` },
        {
            key: 'dates',
            label: t('buyer.closes'),
            render: (r) => (r.expires_at ? formatDate(r.expires_at) : r.status === 'draft' ? t('status.request.draft') : t('admin.noClosingDate')),
        },
        { key: 'status', label: t('common.status'), render: (r) => <StatusBadge kind="request" status={r.status} /> },
    ];

    return (
        <>
            <PageHeader title={t('nav.adminRequests')} description={t('admin.requestsDescription')} />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="request" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-72"
                    label={t('buyer.searchLabel')}
                    placeholder={t('buyer.searchPlaceholder')}
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
