import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { PageHeader, Pagination, QueryBoundary } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDateTime } from '../../lib/format';
import AdminTable from './AdminTable';

// Action prefixes stored in the audit log, with their filter labels.
const AREAS = [
    ['', 'admin.areaAll'],
    ['user.', 'admin.areaUsers'],
    ['supplier.', 'admin.areaSellers'],
    ['buyer.', 'admin.areaBuyers'],
    ['catalog.', 'admin.areaCatalog'],
    ['quotation.', 'admin.areaOffers'],
    ['purchase_order.', 'admin.areaOrders'],
    ['account.', 'admin.areaAccount'],
    ['admin.', 'admin.areaAdmins'],
];

export default function AdminAuditLogPage() {
    const t = useT();
    const [params, update] = useListParams();
    const query = { action: params.action, search: params.search, page: params.page };
    const logs = useQuery({
        queryKey: ['admin', 'audit', query],
        queryFn: () => api.admin.auditLogs(query),
        placeholderData: keepPreviousData,
    });

    const columns = [
        { key: 'when', label: t('admin.colWhen'), render: (l) => <span className="whitespace-nowrap">{formatDateTime(l.created_at)}</span> },
        { key: 'who', label: t('admin.colBy'), render: (l) => l.actor_label },
        {
            key: 'what',
            label: t('admin.colWhat'),
            render: (l) => (
                <span>
                    <span className="block">{l.description}</span>
                    {l.metadata?.reason && <span className="block text-muted">{t('admin.reasonValue', { reason: l.metadata.reason })}</span>}
                </span>
            ),
        },
        { key: 'ip', label: t('admin.colIp'), render: (l) => l.ip_address ?? t('admin.commandLine') },
    ];

    return (
        <>
            <PageHeader title={t('nav.adminActivity')} description={t('admin.auditDescription')} />
            <div className="mb-4 grid gap-3 sm:grid-cols-[14rem_1fr]">
                <label>
                    <span className="sr-only">{t('admin.area')}</span>
                    <select className="field-input" value={params.action ?? ''} onChange={(e) => update({ action: e.target.value })}>
                        {AREAS.map(([value, label]) => (
                            <option key={label} value={value}>
                                {t(label)}
                            </option>
                        ))}
                    </select>
                </label>
                <SearchInput label={t('admin.searchAudit')} placeholder={t('admin.searchAuditPlaceholder')} value={params.search ?? ''} onChange={(search) => update({ search })} />
            </div>
            <p className="mb-3 text-xs text-muted">{t('admin.auditLanguageNote')}</p>
            <QueryBoundary query={logs}>
                {({ data: rows, meta }) => (
                    <>
                        <AdminTable columns={columns} rows={rows} empty={t('admin.auditEmpty')} />
                        <Pagination meta={meta} onPage={(page) => update({ page })} />
                    </>
                )}
            </QueryBoundary>
        </>
    );
}
