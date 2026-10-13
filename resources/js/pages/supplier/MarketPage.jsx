import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import SearchInput from '../../components/SearchInput';
import { DocNumber, EmptyState, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { TANZANIA_REGIONS, formatDate, relativeTime } from '../../lib/format';

export default function MarketPage() {
    const [params, update] = useListParams();
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });
    const query = { search: params.search, region: params.region, category_id: params.category_id, quoted: params.quoted, page: params.page };
    const market = useQuery({ queryKey: ['market', query], queryFn: () => api.marketRequests(query), placeholderData: keepPreviousData });

    return (
        <>
            <PageHeader title="Open requests" description="Buyers' materials lists that are accepting quotations now." />

            <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <SearchInput label="Search requests" placeholder="Title or request number" value={params.search ?? ''} onChange={(search) => update({ search })} />
                <label>
                    <span className="sr-only">Delivery region</span>
                    <select className="field-input" value={params.region ?? ''} onChange={(e) => update({ region: e.target.value })}>
                        <option value="">All regions</option>
                        {TANZANIA_REGIONS.map((region) => (
                            <option key={region}>{region}</option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="sr-only">Category</span>
                    <select className="field-input" value={params.category_id ?? ''} onChange={(e) => update({ category_id: e.target.value })}>
                        <option value="">All categories</option>
                        {(filters.data?.categories ?? []).map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="sr-only">Quoted</span>
                    <select className="field-input" value={params.quoted ?? ''} onChange={(e) => update({ quoted: e.target.value })}>
                        <option value="">Quoted and not quoted</option>
                        <option value="no">Not quoted yet</option>
                        <option value="yes">Already quoted</option>
                    </select>
                </label>
            </div>

            <QueryBoundary query={market}>
                {({ data: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title="No open requests match">
                            New requests appear here as buyers publish them. Clear the filters to see everything that is open.
                        </EmptyState>
                    ) : (
                        <>
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((request) => (
                                    <li key={request.id}>
                                        <Link to={`/supplier/market/${request.id}`} className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto] sm:items-center sm:px-5">
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold">{request.title}</span>
                                                <span className="flex flex-wrap gap-x-3 text-sm text-muted">
                                                    <DocNumber className="text-sm">{request.request_number}</DocNumber>
                                                    <span>{request.buyer.business_name}</span>
                                                    {request.delivery_region && <span>{request.delivery_region}</span>}
                                                    <span>
                                                        {request.items_count} {request.items_count === 1 ? 'item' : 'items'}
                                                    </span>
                                                </span>
                                                <span className="mt-1 block truncate text-sm text-ink-soft">
                                                    {request.items.slice(0, 3).map((item) => item.product?.name).join(', ')}
                                                    {request.items.length > 3 && ` and ${request.items.length - 3} more`}
                                                </span>
                                            </span>
                                            <span className="flex flex-wrap items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                                                {request.my_quotation ? (
                                                    <StatusBadge kind="quotation" status={request.my_quotation.status} />
                                                ) : (
                                                    <span className="text-sm font-semibold text-pal-700">Quote now</span>
                                                )}
                                                <span className="text-sm text-muted" title={formatDate(request.expires_at)}>
                                                    Closes {relativeTime(request.expires_at)}
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
