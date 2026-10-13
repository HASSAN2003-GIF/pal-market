import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Money, PageHeader, Panel, QueryBoundary, Stat } from '../../components/ui';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDateTime, formatNumber } from '../../lib/format';
import { STATUS, statusLabel } from '../../lib/statuses';

function Breakdown({ kind, counts, to }) {
    const keys = Object.keys(STATUS[kind]);
    const total = keys.reduce((sum, key) => sum + Number(counts?.[key] ?? 0), 0);
    return (
        <ul className="space-y-2">
            {keys.map((key) => {
                const value = Number(counts?.[key] ?? 0);
                return (
                    <li key={key}>
                        <Link to={`${to}?status=${key}`} className="group block">
                            <span className="flex justify-between text-sm">
                                <span className="text-ink-soft group-hover:text-ink">{statusLabel(kind, key)}</span>
                                <span className="tabular font-semibold">{formatNumber(value)}</span>
                            </span>
                            <span className="mt-1 block h-1.5 rounded-full bg-concrete-deep">
                                <span className="block h-full rounded-full bg-action" style={{ width: total ? `${(value / total) * 100}%` : 0 }} />
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

export default function AdminOverviewPage() {
    const t = useT();
    const dashboard = useQuery({ queryKey: ['admin', 'dashboard'], queryFn: api.admin.dashboard });

    return (
        <>
            <PageHeader title={t('admin.overviewTitle')} description={t('admin.overviewDescription')} />
            <QueryBoundary query={dashboard}>
                {({ statistics: s, attention, recent_activity: activity }) => (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Stat
                                label={t('admin.statSellersWaiting')}
                                value={attention.suppliers_awaiting_approval}
                                to="/admin/suppliers?status=pending"
                                attention={attention.suppliers_awaiting_approval > 0}
                            />
                            <Stat
                                label={t('admin.statOrdersStuck')}
                                value={attention.orders_pending_confirmation_over_48h}
                                to="/admin/orders?status=pending"
                                attention={attention.orders_pending_confirmation_over_48h > 0}
                            />
                            <Stat label={t('admin.statRequestsOpen')} value={s.buyer_requests_by_status?.open ?? 0} to="/admin/requests?status=open" />
                            <Stat label={t('admin.statSuspended')} value={s.suspended_users} to="/admin/users?status=suspended" />
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Stat label={t('nav.adminUsers')} value={formatNumber(s.users)} to="/admin/users" />
                            <Stat label={t('admin.statNew30')} value={formatNumber(s.new_users_last_30_days)} to="/admin/users?sort=newest" />
                            <Stat label={t('admin.statApprovedSellers')} value={formatNumber(s.suppliers.approved)} to="/admin/suppliers?status=approved" />
                            <Stat label={t('admin.statActiveProducts')} value={`${formatNumber(s.active_products)} / ${formatNumber(s.products)}`} to="/admin/catalog" />
                        </div>

                        <div className="mt-6 grid gap-6 lg:grid-cols-3">
                            <Panel title={t('nav.adminRequests')}>
                                <Breakdown kind="request" counts={s.buyer_requests_by_status} to="/admin/requests" />
                            </Panel>
                            <Panel title={t('nav.adminOrders')}>
                                <Breakdown kind="order" counts={s.purchase_orders_by_status} to="/admin/orders" />
                                <div className="mt-4 border-t border-rule pt-3">
                                    <p className="text-sm text-muted">{t('admin.completedValue')}</p>
                                    {Object.keys(s.completed_order_value ?? {}).length === 0 ? (
                                        <p className="font-semibold">{t('admin.noneYet')}</p>
                                    ) : (
                                        Object.entries(s.completed_order_value).map(([currency, total]) => (
                                            <Money key={currency} amount={total} currency={currency} className="block text-[1.4rem] font-extrabold" />
                                        ))
                                    )}
                                    <p className="mt-1 text-xs text-muted">{t('admin.completedValueNote')}</p>
                                </div>
                            </Panel>
                            <Panel
                                title={t('admin.recentActivity')}
                                actions={
                                    <Link to="/admin/audit-log" className="text-sm font-semibold text-pal-700 hover:underline">
                                        {t('nav.adminActivity')}
                                    </Link>
                                }
                            >
                                {activity.length === 0 ? (
                                    <p className="text-sm text-muted">{t('admin.noActivity')}</p>
                                ) : (
                                    <ul className="space-y-3 text-sm">
                                        {activity.map((entry) => (
                                            <li key={entry.id}>
                                                <p>{entry.description}</p>
                                                <p className="text-xs text-muted">
                                                    {entry.actor_label}, {formatDateTime(entry.created_at)}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        </div>
                    </>
                )}
            </QueryBoundary>
        </>
    );
}
