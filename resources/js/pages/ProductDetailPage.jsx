import React, { useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useProduct } from '../hooks/useProduct';
import { useBuyerRequests } from '../hooks/useBuyerRequests';
import { addItemToRequest } from '../lib/api';

export default function ProductDetailPage() {
    const { productId } = useParams();
    const navigate = useNavigate();
    
    // Fetch Product Data
    const { data: productData, isLoading: loadingProduct } = useProduct(productId);
    const product = productData?.data;

    // Fetch User's Requests for the Dropdown
    const { data: requestsData } = useBuyerRequests();
    const activeRequests = requestsData?.buyer_requests?.filter(req => req.status === 'draft') || [];

    // Form State
    const [form, setForm] = useState({
        request_id: '',
        quantity: 1,
        notes: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    function handleChange(e) {
        setForm({ ...form, [e.target.name]: e.target.value });
    }

    async function handleSubmit(e) {
        e.preventDefault();
        
        if (!form.request_id) {
            setError('Please select a quotation request.');
            return;
        }

        setSubmitting(true);
        setError('');

        try {
            await addItemToRequest(form.request_id, {
                product_id: product.id,
                quantity: form.quantity,
                unit: product.unit,
                notes: form.notes,
            });
            // Route the user back to the specific request so they can see the item added
            navigate(`/requests/${form.request_id}`);
        } catch (err) {
            setError(err.data?.message || 'Failed to add item to request.');
        } finally {
            setSubmitting(false);
        }
    }

    if (loadingProduct) {
        return (
            <div className="mx-auto max-w-7xl px-6 py-16 text-center">
                <p className="text-sm text-ink-500">Loading product details...</p>
            </div>
        );
    }

    if (!product) {
        return (
            <div className="mx-auto max-w-7xl px-6 py-16 text-center">
                <p className="text-sm text-red-500">Product not found.</p>
                <Link to="/marketplace" className="text-pal-600 hover:underline">Back to Marketplace</Link>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl px-6 py-16">
            <div className="mb-8">
                <Link to="/marketplace" className="text-sm font-medium text-ink-500 hover:text-pal-600">
                    &larr; Back to Marketplace
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-12 md:grid-cols-2">
                {/* Product Info */}
                <div>
                    <div className="mb-4 inline-flex items-center rounded-full bg-surface-soft px-3 py-1 text-xs font-medium text-ink-700 border border-border">
                        {product.category?.name || 'Category'}
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight text-ink-950">
                        {product.name}
                    </h1>
                    {product.brand && (
                        <p className="mt-2 text-sm text-ink-600">
                            Brand: <span className="font-medium text-ink-900">{product.brand.name}</span>
                        </p>
                    )}
                    <div className="mt-6 border-t border-border pt-6">
                        <p className="text-sm text-ink-600">Standard Unit</p>
                        <p className="text-lg font-medium text-ink-900">{product.unit}</p>
                    </div>
                </div>

                {/* Add to Request Form */}
                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm sm:p-8">
                    <h3 className="text-lg font-semibold text-ink-900 mb-6">Add to Quotation Request</h3>
                    
                    {error && (
                        <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-ink-800">
                                Select Project / Request <span className="text-red-500">*</span>
                            </label>
                            <select
                                name="request_id"
                                value={form.request_id}
                                onChange={handleChange}
                                required
                                className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                            >
                                <option value="">-- Choose a draft request --</option>
                                {activeRequests.map(req => (
                                    <option key={req.id} value={req.id}>{req.title}</option>
                                ))}
                            </select>
                            {activeRequests.length === 0 && (
                                <p className="mt-2 text-xs text-red-500">
                                    You don't have any active requests. <Link to="/requests/new" className="underline">Create one first.</Link>
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-ink-800">
                                Quantity (in {product.unit}) <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                value={form.quantity}
                                onChange={handleChange}
                                required
                                className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                            />
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-ink-800">
                                Special Notes (Optional)
                            </label>
                            <textarea
                                name="notes"
                                rows="2"
                                value={form.notes}
                                onChange={handleChange}
                                placeholder="e.g., Must be delivered by Friday"
                                className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={submitting || activeRequests.length === 0}
                            className="w-full rounded-xl bg-pal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-pal-700 disabled:opacity-60"
                        >
                            {submitting ? 'Adding...' : 'Add to Request'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}