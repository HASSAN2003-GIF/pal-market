import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { ButtonLink, EmptyState, PageHeader, Pagination, QueryBoundary, cx } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { STATUS } from '../../lib/statuses';
import { RequestRows } from './BuyerOverviewPage';

export function StatusTabs({ kind, value, onChange, include }) {
    const entries = Object.entries(STATUS[kind]).filter(([key]) => !include || include.includes(key));
    return (
        <div role="group" aria-label="Filter by status" className="flex gap-1.5 overflow-x-auto pb-1">
            {[['', { label: 'All' }], ...entries].map(([key, info]) => (
                <button
                    key={key || 'all'}
                    type="button"
                    aria-pressed={(value ?? '') === key}
                    onClick={() => onChange(key)}
                    className={cx(
                        'shrink-0 rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap',
                        (value ?? '') === key ? 'bg-ink text-white' : 'bg-paper text-ink-soft ring-1 ring-rule hover:text-ink',
                    )}
                >
                    {info.label}
                </button>
            ))}
        </div>
    );
}

export default function BuyerRequestsPage() {
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const requests = useQuery({
        queryKey: ['buyer-requests', query],
        queryFn: () => api.buyerRequests(query),
        placeholderData: keepPreviousData,
    });

    return (
        <>
            <PageHeader
                title="My requests"
                description="Every materials list you have drafted or published."
                actions={<ButtonLink to="/buyer/requests/new">New request</ButtonLink>}
            />
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <StatusTabs kind="request" value={params.status} onChange={(status) => update({ status })} />
                <SearchInput
                    className="lg:w-72"
                    label="Search requests"
                    placeholder="Search title or number"
                    value={params.search ?? ''}
                    onChange={(search) => update({ search })}
                />
            </div>
            <QueryBoundary query={requests}>
                {({ buyer_requests: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title={params.status || params.search ? 'No requests match' : 'No requests yet'}>
                            {params.status || params.search
                                ? 'Try another status or search term.'
                                : 'Start a request to collect quotations from suppliers.'}
                        </EmptyState>
                    ) : (
                        <>
                            <div className="overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                <RequestRows rows={rows} />
                            </div>
                            <Pagination meta={meta} onPage={(page) => update({ page })} />
                        </>
                    )
                }
            </QueryBoundary>
        </>
    );
}
