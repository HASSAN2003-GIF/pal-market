import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ButtonLink, DocNumber, EmptyState, PageHeader, Panel, QueryBoundary, Stat, StatusBadge } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, pluralize } from '../../lib/format';

export default function BuyerOverviewPage() {
    const t = useT();
    const { user } = useAuth();
    const summary = useQuery({ queryKey: ['summary'], queryFn: api.summary });
    const recent = useQuery({ queryKey: ['buyer-requests', { per_page: 6 }], queryFn: () => api.buyerRequests({ per_page: 6 }) });

    const s = summary.data?.summary?.buyer;
    const byStatus = s?.requests_by_status ?? {};

    return (
        <>
            <PageHeader
                title={t('buyer.hello', { name: user?.name?.split(' ')[0] ?? '' })}
                description={t('buyer.overviewDescription')}
                actions={<ButtonLink to="/buyer/requests/new">{t('nav.newRequest')}</ButtonLink>}
            />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat
                    label={t('buyer.statOffersToReview')}
                    value={s?.quotations_awaiting_decision ?? '–'}
                    to="/buyer/requests?status=open"
                    attention={s?.quotations_awaiting_decision > 0}
                />
                <Stat label={t('status.request.open')} value={byStatus.open ?? 0} to="/buyer/requests?status=open" />
                <Stat label={t('status.request.draft')} value={byStatus.draft ?? 0} to="/buyer/requests?status=draft" />
                <Stat
                    label={t('buyer.statOrdersNeedingYou')}
                    value={s?.orders_awaiting_your_action ?? '–'}
                    to="/orders"
                    attention={s?.orders_awaiting_your_action > 0}
                />
            </div>

            <Panel
                title={t('buyer.recentRequests')}
                className="mt-6"
                bodyClassName="p-0 sm:p-0"
                actions={
                    <Link to="/buyer/requests" className="text-sm font-semibold text-pal-700 hover:underline">
                        {t('buyer.allRequests')}
                    </Link>
                }
            >
                <QueryBoundary query={recent}>
                    {({ buyer_requests: rows }) =>
                        rows.length === 0 ? (
                            <div className="p-4">
                                <EmptyState title={t('buyer.firstTitle')} action={<ButtonLink to="/buyer/requests/new">{t('product.startRequest')}</ButtonLink>}>
                                    {t('buyer.firstBody')}
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
    const t = useT();
    return (
        <ul className="divide-y divide-rule">
            {rows.map((request) => (
                <li key={request.id}>
                    <Link to={`/buyer/requests/${request.id}`} className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto] sm:items-center sm:px-5">
                        <span className="min-w-0">
                            <span className="block truncate font-semibold">{request.title}</span>
                            <span className="flex flex-wrap gap-x-3 text-sm text-muted">
                                <DocNumber className="text-sm">{request.request_number}</DocNumber>
                                <span>{pluralize(request.items?.length ?? 0, 'common.items')}</span>
                                {request.status === 'open' && <span>{t('buyer.closesOn', { date: formatDate(request.expires_at) })}</span>}
                            </span>
                        </span>
                        <span className="flex items-center gap-3 sm:justify-end">
                            {request.quotations_count > 0 && <span className="text-sm font-semibold text-ink">{pluralize(request.quotations_count, 'common.offers')}</span>}
                            <StatusBadge kind="request" status={request.status} />
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
