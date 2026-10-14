import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import SearchInput from '../../components/SearchInput';
import { PinIcon } from '../../components/icons';
import { Button, DocNumber, EmptyState, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, pluralize, relativeTime } from '../../lib/format';
import { useArea } from '../../location/LocationContext';
import { REGION_NAMES, regionLabel } from '../../location/regions';

export default function MarketPage() {
    const { t, locale } = useI18n();
    const { region: myArea } = useArea();
    const [params, update] = useListParams();
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });
    const query = { search: params.search, region: params.region, category_id: params.category_id, quoted: params.quoted, page: params.page };
    const market = useQuery({ queryKey: ['market', query], queryFn: () => api.marketRequests(query), placeholderData: keepPreviousData });

    return (
        <>
            <PageHeader title={t('nav.market')} description={t('supplier.marketDescription')} />

            <div className="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <SearchInput label={t('buyer.searchLabel')} placeholder={t('buyer.searchPlaceholder')} value={params.search ?? ''} onChange={(search) => update({ search })} />
                <label>
                    <span className="sr-only">{t('buyer.fieldRegion')}</span>
                    <select className="field-input" value={params.region ?? ''} onChange={(e) => update({ region: e.target.value })}>
                        <option value="">{t('supplier.allRegions')}</option>
                        {REGION_NAMES.map((region) => (
                            <option key={region} value={region}>
                                {regionLabel(region, locale)}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="sr-only">{t('search.category')}</span>
                    <select className="field-input" value={params.category_id ?? ''} onChange={(e) => update({ category_id: e.target.value })}>
                        <option value="">{t('search.allCategories')}</option>
                        {(filters.data?.categories ?? []).map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="sr-only">{t('supplier.quotedFilter')}</span>
                    <select className="field-input" value={params.quoted ?? ''} onChange={(e) => update({ quoted: e.target.value })}>
                        <option value="">{t('supplier.quotedAll')}</option>
                        <option value="no">{t('supplier.quotedNo')}</option>
                        <option value="yes">{t('supplier.quotedYes')}</option>
                    </select>
                </label>
            </div>
            {myArea && params.region !== myArea && (
                <Button size="sm" variant="secondary" className="mb-4" onClick={() => update({ region: myArea })}>
                    <PinIcon className="h-4 w-4" />
                    {t('supplier.onlyMyArea', { region: regionLabel(myArea, locale) })}
                </Button>
            )}

            <QueryBoundary query={market}>
                {({ data: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title={t('supplier.marketEmptyTitle')}>{t('supplier.marketEmptyBody')}</EmptyState>
                    ) : (
                        <>
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((request) => (
                                    <li key={request.id}>
                                        <Link to={`/supplier/market/${request.id}`} className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto] sm:items-center sm:px-5">
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold">{request.title}</span>
                                                <span className="flex flex-wrap items-center gap-x-3 text-sm text-muted">
                                                    <DocNumber className="text-sm">{request.request_number}</DocNumber>
                                                    <span>{request.buyer.business_name}</span>
                                                    {request.delivery_region && <span>{regionLabel(request.delivery_region, locale)}</span>}
                                                    <span>{pluralize(request.items_count, 'common.items')}</span>
                                                    {myArea && request.delivery_region === myArea && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-bold text-ok">
                                                            <PinIcon className="h-3.5 w-3.5" />
                                                            {t('location.inYourArea')}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="mt-1 block truncate text-sm text-ink-soft">
                                                    {request.items
                                                        .slice(0, 3)
                                                        .map((item) => item.product?.name)
                                                        .join(', ')}
                                                    {request.items.length > 3 && ` ${t('supplier.andMore', { n: request.items.length - 3 })}`}
                                                </span>
                                            </span>
                                            <span className="flex flex-wrap items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                                                {request.my_quotation ? (
                                                    <StatusBadge kind="quotation" status={request.my_quotation.status} />
                                                ) : (
                                                    <span className="text-sm font-semibold text-pal-700">{t('supplier.sendOfferNow')}</span>
                                                )}
                                                <span className="text-sm text-muted" title={formatDate(request.expires_at)}>
                                                    {t('supplier.closesIn', { relative: relativeTime(request.expires_at) })}
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <Pagination meta={meta} onPage={(page) => update({ page })} />
                        </>
                    )
                }
            </QueryBoundary>
        </>
    );
}
