import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { AreaButton } from '../components/PreferenceControls';
import { CloseIcon, PinIcon } from '../components/icons';
import { EmptyState, ErrorState, LoadingRows, Money, Pagination, cx } from '../components/ui';
import { useListParams } from '../hooks/useListParams';
import { useI18n } from '../i18n';
import { api } from '../lib/api';
import { pluralize } from '../lib/format';
import { useArea } from '../location/LocationContext';
import { regionLabel } from '../location/regions';

const SORTS = [
    ['', 'materials.sortBest'],
    ['price_asc', 'materials.sortPriceLow'],
    ['price_desc', 'materials.sortPriceHigh'],
    ['name', 'materials.sortName'],
];

export default function MaterialsPage() {
    const { t, locale } = useI18n();
    const [params, update] = useListParams();
    const { region } = useArea();
    const [filtersOpen, setFiltersOpen] = useState(false);
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });

    const onlyLocal = Boolean(region) && params.only_local === '1';
    const query = {
        search: params.search,
        category_id: params.category_id,
        sort: params.sort,
        region: region ?? undefined,
        only_local: onlyLocal ? 1 : undefined,
        page: params.page,
        per_page: 20,
    };
    const products = useQuery({
        queryKey: ['products', query],
        queryFn: () => api.products(query),
        placeholderData: keepPreviousData,
    });

    const categories = filters.data?.categories ?? [];
    const rows = products.data?.data ?? [];
    const total = products.data?.meta?.total;
    const category = categories.find((c) => String(c.id) === String(params.category_id ?? ''));
    const areaName = region ? regionLabel(region, locale) : null;

    const applied = [
        params.search && { key: 'search', label: `“${params.search}”`, clear: { search: '' } },
        category && { key: 'category', label: category.name, clear: { category_id: '' } },
        onlyLocal && { key: 'local', label: t('location.onlyMyArea', { region: areaName }), clear: { only_local: '' } },
        params.sort && { key: 'sort', label: t(SORTS.find(([v]) => v === params.sort)?.[1] ?? 'materials.sortBest'), clear: { sort: '' } },
    ].filter(Boolean);

    const filterControls = (
        <div className="space-y-5">
            <div>
                <p className="mb-2 font-semibold">{t('search.category')}</p>
                <ul className="flex flex-col gap-0.5">
                    {[{ id: '', name: t('materials.allMaterials') }, ...categories].map((c) => {
                        const active = String(params.category_id ?? '') === String(c.id);
                        return (
                            <li key={c.id || 'all'}>
                                <button
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => {
                                        update({ category_id: c.id });
                                        setFiltersOpen(false);
                                    }}
                                    className={cx(
                                        'min-h-11 w-full rounded-[var(--radius-control)] px-3 py-2 text-left font-medium lg:min-h-10',
                                        active ? 'bg-inverse text-on-inverse' : 'text-ink-soft hover:bg-concrete-deep hover:text-ink',
                                    )}
                                >
                                    {c.name}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </div>
            <div>
                <p className="mb-2 font-semibold">{t('location.yourArea')}</p>
                {region ? (
                    <label className="flex items-start gap-2.5 rounded-[var(--radius-control)] border border-rule bg-paper px-3 py-2.5 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5 h-4 w-4 accent-[var(--color-action)]"
                            checked={onlyLocal}
                            onChange={(event) => update({ only_local: event.target.checked ? '1' : '' })}
                        />
                        <span>{t('location.onlyMyArea', { region: areaName })}</span>
                    </label>
                ) : (
                    <div className="rounded-[var(--radius-control)] border border-dashed border-rule px-3 py-2.5 text-sm text-muted">
                        <p>{t('location.setAreaHint')}</p>
                        <AreaButton compact className="mt-1" />
                    </div>
                )}
            </div>
        </div>
    );

    return (
        <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10 2xl:max-w-7xl">
            <h1 className="text-[1.9rem] sm:text-[2.5rem] lg:text-[2.9rem]">{t('materials.title')}</h1>
            <p className="mt-2 max-w-2xl text-ink-soft">{t('materials.lead')}</p>

            <div className="mt-7 grid gap-6 lg:grid-cols-[15rem_1fr]">
                <aside aria-label={t('materials.filters')} className="hidden min-w-0 lg:block">
                    {filterControls}
                </aside>

                <section aria-label={t('materials.results')} className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            className="inline-flex h-10 items-center rounded-[var(--radius-control)] border border-rule bg-paper px-3 font-semibold lg:hidden"
                            aria-expanded={filtersOpen}
                            aria-controls="mobile-filters"
                            onClick={() => setFiltersOpen((open) => !open)}
                        >
                            {t('materials.filters')}
                            {applied.length > 0 && <span className="ml-2 rounded-full bg-action px-2 text-xs text-on-action">{applied.length}</span>}
                        </button>
                        <p className="text-sm text-muted" aria-live="polite">
                            {total !== undefined && pluralize(total, 'common.products')}
                        </p>
                        <label className="ml-auto flex items-center gap-2 text-sm">
                            <span className="font-semibold">{t('materials.sortBy')}</span>
                            <select className="field-input h-10 min-h-0 w-auto py-1.5" value={params.sort ?? ''} onChange={(event) => update({ sort: event.target.value })}>
                                {SORTS.map(([value, label]) => (
                                    <option key={value || 'best'} value={value}>
                                        {t(label)}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    {filtersOpen && (
                        <div id="mobile-filters" className="mt-3 rounded-[var(--radius-panel)] border border-rule bg-paper p-4 lg:hidden">
                            {filterControls}
                        </div>
                    )}

                    {applied.length > 0 && (
                        <ul className="mt-3 flex flex-wrap items-center gap-2" aria-label={t('materials.appliedFilters')}>
                            {applied.map((filter) => (
                                <li key={filter.key}>
                                    <button
                                        type="button"
                                        onClick={() => update(filter.clear)}
                                        className="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-pal-200 bg-pal-50 py-1.5 pr-2.5 pl-3.5 text-sm font-semibold text-pal-700 hover:border-pal-500 lg:min-h-0"
                                        aria-label={t('common.removeFilter', { name: filter.label })}
                                    >
                                        {filter.label}
                                        <CloseIcon className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                            <li>
                                <button
                                    type="button"
                                    className="tap text-sm font-semibold text-ink-soft underline hover:text-ink"
                                    onClick={() => update({ search: '', category_id: '', only_local: '', sort: '' })}
                                >
                                    {t('common.clearAll')}
                                </button>
                            </li>
                        </ul>
                    )}

                    <div className="mt-4" aria-busy={products.isFetching}>
                        {products.isPending ? (
                            <LoadingRows rows={6} label={t('materials.loading')} />
                        ) : products.isError ? (
                            <ErrorState error={products.error} onRetry={() => products.refetch()} />
                        ) : rows.length === 0 ? (
                            <EmptyState title={t('materials.emptyTitle')}>{onlyLocal ? t('materials.emptyLocal', { region: areaName }) : t('materials.emptyBody')}</EmptyState>
                        ) : (
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((product) => (
                                    <li key={product.id}>
                                        <Link
                                            to={`/materials/${product.id}`}
                                            className="grid gap-1.5 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-6"
                                        >
                                            <span className="min-w-0">
                                                <span className="block font-bold text-ink">{product.name}</span>
                                                <span className="block text-sm text-muted">
                                                    {product.category?.name}
                                                    {product.brand && `, ${product.brand.name}`} · {t('common.perUnit', { unit: product.unit })}
                                                </span>
                                                {region && product.local_offers_count > 0 && (
                                                    <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-bold text-ok">
                                                        <PinIcon className="h-3.5 w-3.5" />
                                                        {t('location.stockedInArea', { count: product.local_offers_count, n: product.local_offers_count, region: areaName })}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="text-sm sm:text-right">
                                                {product.from_price ? (
                                                    <>
                                                        <span className="text-muted">{t('materials.from')} </span>
                                                        <Money amount={product.from_price} className="text-base font-bold text-ink" />
                                                        <span className="block text-muted">{pluralize(product.offers_count, 'common.sellers')}</span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted">{t('materials.askForPrice')}</span>
                                                )}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <Pagination meta={products.data?.meta} onPage={(page) => update({ page })} />
                </section>
            </div>
        </div>
    );
}
