import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getProduct } from '../lib/api';

export default function ProductDetailPage() {
    const { productId } = useParams();

    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        async function loadProduct() {
            try {
                setLoading(true);
                setError('');

                const response = await getProduct(productId);

                setProduct(response.data);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        }

        loadProduct();
    }, [productId]);

    if (loading) {
        return (
            <div className="min-h-screen bg-slate-50 px-6 py-20 text-center text-sm text-slate-500">
                Loading product...
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-slate-50 px-6 py-20">
                <div className="mx-auto max-w-3xl rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    {error}
                </div>
            </div>
        );
    }

    if (!product) {
        return null;
    }

    return (
        <section className="min-h-screen bg-slate-50">
            <div className="mx-auto max-w-7xl px-6 py-10">
                <Link
                    to="/marketplace"
                    className="text-sm font-medium text-slate-500 hover:text-slate-900"
                >
                    ← Back to marketplace
                </Link>

                <div className="mt-8 grid gap-10 lg:grid-cols-[1fr_1.2fr]">
                    <div className="flex min-h-[420px] items-center justify-center rounded-2xl border border-slate-200 bg-white">
                        <span className="text-sm text-slate-400">
                            Product image
                        </span>
                    </div>

                    <div>
                        <p className="text-sm font-medium text-slate-400">
                            {product.category?.name}
                        </p>

                        <h1 className="mt-2 text-4xl font-semibold tracking-tight text-slate-950">
                            {product.name}
                        </h1>

                        {product.brand && (
                            <p className="mt-3 text-sm text-slate-500">
                                Brand: {product.brand.name}
                            </p>
                        )}

                        {product.description && (
                            <p className="mt-6 max-w-2xl text-base leading-7 text-slate-600">
                                {product.description}
                            </p>
                        )}

                        <div className="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Unit
                            </p>

                            <p className="mt-1 text-sm font-medium text-slate-900">
                                {product.unit}
                            </p>
                        </div>

                        <div className="mt-10">
                            <h2 className="text-xl font-semibold text-slate-900">
                                Available suppliers
                            </h2>

                            {product.supplier_offerings?.length === 0 ? (
                                <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">
                                    No suppliers currently have this product
                                    available.
                                </div>
                            ) : (
                                <div className="mt-4 space-y-4">
                                    {product.supplier_offerings.map(
                                        (offering) => (
                                            <div
                                                key={
                                                    offering.supplier_product_id
                                                }
                                                className="rounded-2xl border border-slate-200 bg-white p-5"
                                            >
                                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                    <div>
                                                        <h3 className="font-semibold text-slate-900">
                                                            {
                                                                offering
                                                                    .supplier
                                                                    .business_name
                                                            }
                                                        </h3>

                                                        <p className="mt-1 text-sm text-slate-500">
                                                            {
                                                                offering
                                                                    .location
                                                                    .name
                                                            }
                                                            {offering.location
                                                                .region &&
                                                                ` · ${offering.location.region}`}
                                                        </p>
                                                    </div>

                                                    <div className="sm:text-right">
                                                        <p className="text-lg font-semibold text-slate-950">
                                                            {Number(
                                                                offering.price,
                                                            ).toLocaleString(
                                                                'en-TZ',
                                                            )}{' '}
                                                            {
                                                                offering.currency
                                                            }
                                                        </p>

                                                        <p className="text-xs text-slate-500">
                                                            per{' '}
                                                            {offering.unit}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                                                    <span className="text-sm text-slate-500">
                                                        {offering.quantity.toLocaleString()}{' '}
                                                        {offering.unit}s
                                                        available
                                                    </span>

                                                    <button
                                                        type="button"
                                                        className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                                                    >
                                                        Request quotation
                                                    </button>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}