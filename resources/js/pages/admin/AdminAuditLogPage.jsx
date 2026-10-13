import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { PageHeader, Pagination, QueryBoundary } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { formatDateTime } from '../../lib/format';
import AdminTable from './AdminTable';

const AREAS = [
    ['', 'All activity'],
    ['user.', 'User accounts'],
    ['supplier.', 'Suppliers'],
    ['buyer.', 'Buyers'],
    ['catalog.', 'Catalog'],
    ['quotation.', 'Quotations'],
    ['purchase_order.', 'Purchase orders'],
    ['account.', 'Sign-in and passwords'],
    ['admin.', 'Administrators'],
];

export default function AdminAuditLogPage() {
    const [params, update] = useListParams();
    const query = { action: params.action, search: params.search, page: params.page };
    const logs = useQuery({
        queryKey: ['admin', 'audit', query],
        queryFn: () => api.admin.auditLogs(query),
        placeholderData: keepPreviousData,
    });

    const columns = [
        { key: 'when', label: 'When', render: (l) => <span className="whitespace-nowrap">{formatDateTime(l.created_at)}</span> },
        { key: 'who', label: 'By', render: (l) => l.actor_label },
        {
            key: 'what',
            label: 'What happened',
            render: (l) => (
                <span>
                    <span className="block">{l.description}</span>
                    {l.metadata?.reason && <span className="block text-muted">Reason: {l.metadata.reason}</span>}
                </span>
            ),
        },
        { key: 'ip', label: 'IP address', render: (l) => l.ip_address ?? 'Command line' },
    ];

    return (
        <>
            <PageHeader
                title="Audit log"
                description="Security-relevant and administrative actions, newest first. Entries cannot be edited or deleted from the app."
            />
            <div className="mb-4 grid gap-3 sm:grid-cols-[14rem_1fr]">
                <label>
                    <span className="sr-only">Area</span>
                    <select className="field-input" value={params.action ?? ''} onChange={(e) => update({ action: e.target.value })}>
                        {AREAS.map(([value, label]) => (
                            <option key={label} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <SearchInput
                    label="Search the audit log"
                    placeholder="Search descriptions or people"
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
            </div>
            <QueryBoundary query={logs}>
                {({ data: rows, meta }) => (
                    <>
                        <AdminTable columns={columns} rows={rows} empty="No recorded activity matches." />
                        <Pagination meta={meta} onPage={(page) => update({ page })} />
                    </>
                )}
            </QueryBoundary>
        </>
    );
}
