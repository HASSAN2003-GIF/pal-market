import { useEffect, useState } from 'react';
import ProductCard from '../components/ProductCard';
import { getProducts } from '../lib/api';

export default function MarketplacePage() {
    const [products, setProducts] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    async function loadProducts(searchTerm = '') {
        try {
            setLoading(true);
            setError('');

            const response = await getProducts({
                search: searchTerm,
                per_page: 12,
            });

            setProducts(response.data || []);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadProducts();
    }, []);

    function handleSubmit(event) {
        event.preventDefault();
        loadProducts(search);
    }

    return (
        <section className="min-h-screen bg-surface-soft">
            <div className="mx-auto max-w-7xl px-6 py-10 lg:py-12">
                <div className="mb-8">
                    <p className="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-pal-600">
                        Marketplace
                    </p>

                    <h1 className="text-4xl font-bold tracking-tight text-ink-950">
                        Find what you need
                    </h1>

                    <p className="mt-3 max-w-2xl text-base leading-7 text-ink-600">
                        Search construction materials and compare what
                        suppliers currently have available.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="mb-10 flex max-w-4xl gap-3 rounded-2xl border border-border bg-white p-2 shadow-sm"
                >
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search cement, pipes, windows..."
                        className="min-w-0 flex-1 rounded-xl bg-transparent px-4 py-3 text-sm text-ink-900 outline-none placeholder:text-ink-500 focus:bg-surface-soft"
                    />

                    <button
                        type="submit"
                        className="rounded-xl bg-pal-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-pal-700"
                    >
                        Search
                    </button>
                </form>

                {loading && (
                    <div className="py-20 text-center text-sm text-ink-500">
                        Loading products...
                    </div>
                )}

                {!loading && error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {!loading && !error && products.length === 0 && (
                    <div className="rounded-2xl border border-border bg-white px-6 py-16 text-center shadow-sm">
                        <h2 className="text-lg font-bold text-ink-900">
                            Nothing matched your search
                        </h2>

                        <p className="mt-2 text-sm text-ink-500">
                            Try a different product name or search term.
                        </p>
                    </div>
                )}

                {!loading && !error && products.length > 0 && (
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {products.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={product}
                            />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}