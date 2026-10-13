import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ButtonLink, DocNumber, EmptyState, PageHeader, Panel, QueryBoundary, Stat, StatusBadge } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';

export default function BuyerOverviewPage() {
    const { user } = useAuth();
    const summary = useQuery({ queryKey: ['summary'], queryFn: api.summary });
    const recent = useQuery({ queryKey: ['buyer-requests', { per_page: 6 }], queryFn: () => api.buyerRequests({ per_page: 6 }) });

    const s = summary.data?.summary?.buyer;
    const byStatus = s?.requests_by_status ?? {};

    return (
        <>
            <PageHeader
                title={`Hello, ${user?.name?.split(' ')[0] ?? 'there'}`}
                description="Requests, quotations and orders for your sites."
                actions={<ButtonLink to="/buyer/requests/new">New request</ButtonLink>}
            />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat
                    label="Quotations to review"
                    value={s?.quotations_awaiting_decision ?? '–'}
                    to="/buyer/requests?status=open"
                    attention={s?.quotations_awaiting_decision > 0}
                />
                <Stat label="Open for quotes" value={byStatus.open ?? 0} to="/buyer/requests?status=open" />
                <Stat label="Drafts" value={byStatus.draft ?? 0} to="/buyer/requests?status=draft" />
                <Stat
                    label="Orders needing you"
                    value={s?.orders_awaiting_your_action ?? '–'}
                    to="/orders"
                    attention={s?.orders_awaiting_your_action > 0}
                />
            </div>

            <Panel
                title="Recent requests"
                className="mt-6"
                bodyClassName="p-0 sm:p-0"
                actions={
                    <Link to="/buyer/requests" className="text-sm font-semibold text-pal-700 hover:underline">
                        All requests
                    </Link>
                }
            >
                <QueryBoundary query={recent}>
                    {({ buyer_requests: rows }) =>
                        rows.length === 0 ? (
                            <div className="p-4">
                                <EmptyState
                                    title="Post your first materials list"
                                    action={<ButtonLink to="/buyer/requests/new">Start a request</ButtonLink>}
                                >
                                    Add the products and quantities your site needs. Suppliers send quotations once you publish it.
                                </EmptyState>
                            </div>
                        ) : (
                            <RequestRows rows={rows} />
                        )
                    }
                </QueryBoundary>
            </Panel>
        </>
    );
}

export function RequestRows({ rows }) {
    return (
        <ul className="divide-y divide-rule">
            {rows.map((request) => (
                <li key={request.id}>
                    <Link
                        to={`/buyer/requests/${request.id}`}
                        className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto] sm:items-center sm:px-5"
                    >
                        <span className="min-w-0">
                            <span className="block truncate font-semibold">{request.title}</span>
                            <span className="flex flex-wrap gap-x-3 text-sm text-muted">
                                <DocNumber className="text-sm">{request.request_number}</DocNumber>
                                <span>
                                    {request.items?.length ?? 0} {request.items?.length === 1 ? 'item' : 'items'}
                                </span>
                                {request.status === 'open' && <span>Closes {formatDate(request.expires_at)}</span>}
                            </span>
                        </span>
                        <span className="flex items-center gap-3 sm:justify-end">
                            {request.quotations_count > 0 && (
                                <span className="text-sm font-semibold text-ink">
                                    {request.quotations_count} {request.quotations_count === 1 ? 'quote' : 'quotes'}
                                </span>
                            )}
                            <StatusBadge kind="request" status={request.status} />
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
