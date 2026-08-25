import { Link } from 'react-router-dom';

export default function ProductCard({ product }) {
    return (
        <Link
            to={`/products/${product.id}`}
            className="group block overflow-hidden rounded-2xl border border-border bg-white transition hover:-translate-y-0.5 hover:border-pal-200 hover:shadow-lg hover:shadow-pal-900/5"
        >
            <div className="flex h-44 items-center justify-center bg-surface-soft">
                <span className="text-sm font-medium text-ink-500">
                    Product image
                </span>
            </div>

            <div className="space-y-3 p-5">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-xs font-bold uppercase tracking-wide text-pal-600">
                        {product.category?.name}
                    </span>

                    {product.brand && (
                        <span className="text-xs font-medium text-ink-500">
                            {product.brand.name}
                        </span>
                    )}
                </div>

                <h3 className="text-base font-bold text-ink-900 transition group-hover:text-pal-700">
                    {product.name}
                </h3>

                <p className="text-sm text-ink-500">
                    Sold by {product.unit}
                </p>
            </div>
        </Link>
    );
}