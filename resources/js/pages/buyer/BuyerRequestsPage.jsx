import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { ButtonLink, EmptyState, PageHeader, Pagination, QueryBoundary, cx } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { STATUS, statusLabel } from '../../lib/statuses';
import { RequestRows } from './BuyerOverviewPage';

export function StatusTabs({ kind, value, onChange, include }) {
    const t = useT();
    const keys = Object.keys(STATUS[kind]).filter((key) => !include || include.includes(key));
    return (
        <div role="group" aria-label={t('buyer.filterByStatus')} className="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 lg:flex-wrap">
            {['', ...keys].map((key) => (
                <button
                    key={key || 'all'}
                    type="button"
                    aria-pressed={(value ?? '') === key}
                    onClick={() => onChange(key)}
                    className={cx(
                        'min-h-10 shrink-0 rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap lg:min-h-0 lg:py-1.5',
                        (value ?? '') === key ? 'bg-inverse text-on-inverse' : 'bg-paper text-ink-soft ring-1 ring-rule hover:text-ink',
                    )}
                >
                    {key ? statusLabel(kind, key) : t('common.all')}
                </button>
            ))}
        </div>
    );
}

export default function BuyerRequestsPage() {
    const t = useT();
    const [params, update] = useListParams();
    const query = { status: params.status, search: params.search, page: params.page };
    const requests = useQuery({
        queryKey: ['buyer-requests', query],
        queryFn: () => api.buyerRequests(query),
        placeholderData: keepPreviousData,
    });
    const filtered = params.status || params.search;

    return (
        <>
            <PageHeader
                title={t('nav.myRequests')}
                description={t('buyer.listDescription')}
                actions={<ButtonLink to="/buyer/requests/new">{t('nav.newRequest')}</ButtonLink>}
            />
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
                {({ buyer_requests: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState
                            title={filtered ? t('buyer.noneMatchTitle') : t('buyer.noneYetTitle')}
                            action={!filtered && <ButtonLink to="/buyer/requests/new">{t('nav.newRequest')}</ButtonLink>}
                        >
                            {filtered ? t('buyer.noneMatchBody') : t('buyer.noneYetBody')}
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
