import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import SearchInput from '../components/SearchInput';
import { EmptyState, ErrorState, LoadingRows, Money, Pagination, cx } from '../components/ui';
import { useListParams } from '../hooks/useListParams';
import { api } from '../lib/api';

export default function MaterialsPage() {
    const [params, update] = useListParams();
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });

    const query = { search: params.search, category_id: params.category_id, page: params.page, per_page: 20 };
    const products = useQuery({
        queryKey: ['products', query],
        queryFn: () => api.products(query),
        placeholderData: keepPreviousData,
    });

    const categories = filters.data?.categories ?? [];
    const rows = products.data?.data ?? [];

    return (
        <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <h1 className="text-[2.6rem] sm:text-[3.2rem]">Construction materials</h1>
            <p className="mt-2 max-w-2xl text-ink-soft">
                Prices shown are the lowest current list price from approved suppliers. For larger orders, add materials to a
                request and suppliers will quote for your quantities and delivery.
            </p>

            <div className="mt-8 grid gap-6 lg:grid-cols-[14rem_1fr]">
                <nav aria-label="Categories" className="min-w-0">
                    <p className="mb-2 font-semibold">Category</p>
                    <ul className="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:gap-0.5 lg:pb-0">
                        {[{ id: '', name: 'All materials' }, ...categories].map((category) => {
                            const active = String(params.category_id ?? '') === String(category.id);
                            return (
                                <li key={category.id || 'all'} className="shrink-0">
                                    <button
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() => update({ category_id: category.id })}
                                        className={cx(
                                            'w-full rounded-[var(--radius-control)] px-3 py-1.5 text-left font-medium whitespace-nowrap',
                                            active ? 'bg-ink text-white' : 'bg-paper text-ink-soft ring-1 ring-rule hover:text-ink lg:bg-transparent lg:ring-0',
                                        )}
                                    >
                                        {category.name}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <section aria-label="Products" className="min-w-0">
                    <SearchInput
                        label="Search materials"
                        placeholder="Search cement, rebar, roofing sheets…"
                        value={params.search ?? ''}
                        onChange={(search) => update({ search })}
                    />

                    <div className="mt-4" aria-busy={products.isFetching}>
                        {products.isPending ? (
                            <LoadingRows rows={6} label="Loading materials" />
                        ) : products.isError ? (
                            <ErrorState error={products.error} onRetry={() => products.refetch()} />
                        ) : rows.length === 0 ? (
                            <EmptyState title="No materials match">
                                Try a different search term or category. If a product is missing from the catalog, mention it in your
                                request notes and suppliers can quote for it.
                            </EmptyState>
                        ) : (
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((product) => (
                                    <li key={product.id}>
                                        <Link
                                            to={`/materials/${product.id}`}
                                            className="grid gap-1 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-6"
                                        >
                                            <span className="min-w-0">
                                                <span className="block font-semibold text-ink">{product.name}</span>
                                                <span className="block text-sm text-muted">
                                                    {product.category?.name}
                                                    {product.brand && `, ${product.brand.name}`}. Sold per {product.unit}
                                                </span>
                                            </span>
                                            <span className="text-sm sm:text-right">
                                                {product.from_price ? (
                                                    <>
                                                        <span className="text-muted">from </span>
                                                        <Money amount={product.from_price} className="font-semibold text-ink" />
                                                        <span className="block text-muted">
                                                            {product.offers_count} {product.offers_count === 1 ? 'supplier' : 'suppliers'}
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted">Request a quote</span>
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
