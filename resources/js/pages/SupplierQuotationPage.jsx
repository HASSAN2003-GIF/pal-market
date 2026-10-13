import React, { useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getMarketRequestDetail, request } from '../lib/api';

export default function SupplierQuotationPage() {
    const { requestId } = useParams();
    const navigate = useNavigate();

    const { data, isLoading } = useQuery({
        queryKey: ['market-request', requestId],
        queryFn: () => getMarketRequestDetail(requestId),
    });

    const requestData = data?.buyer_request;

    const [prices, setPrices] = useState({});
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    function handlePriceChange(itemId, value) {
        setPrices({ ...prices, [itemId]: value });
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            const quotationRes = await request(`/buyer-requests/${requestId}/quotations`, {
                method: 'POST',
            });
            const quotationId = quotationRes.quotation.id;

            for (const item of requestData.items) {
                const unitPrice = prices[item.id];
                if (!unitPrice) continue;

                await request(`/quotations/${quotationId}/items`, {
                    method: 'POST',
                    body: JSON.stringify({
                        product_id: item.product_id,
                        quantity: item.quantity,
                        unit: item.unit,
                        unit_price: Number(unitPrice),
                        notes: notes,
                    }),
                });
            }

            await request(`/quotations/${quotationId}/submit`, {
                method: 'POST',
            });

            navigate('/supplier', { replace: true });
        } catch (err) {
            setError(err.data?.message || err.message || 'Failed to submit quotation. You may have already placed a bid on this request.');
            setSubmitting(false);
        }
    }

    if (isLoading) {
        return (
            <div className="mx-auto max-w-7xl px-6 py-16 text-center font-manrope">
                <p className="text-sm text-ink-500">Loading request details...</p>
            </div>
        );
    }

    if (!requestData) {
        return (
            <div className="mx-auto max-w-7xl px-6 py-16 text-center font-manrope">
                <p className="text-sm text-red-500">Request not found or no longer open.</p>
                <Link to="/supplier" className="text-pal-600 hover:underline">Back to Dashboard</Link>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-4xl px-6 py-16 font-manrope">
            <div className="mb-8">
                <Link to="/supplier" className="text-sm font-medium text-ink-500 hover:text-pal-600">
                    &larr; Back to Supplier Workspace
                </Link>
            </div>

            <div className="mb-8">
                <h1 className="text-3xl font-semibold tracking-tight text-ink-950">
                    Submit Quotation: {requestData.title}
                </h1>
                <p className="mt-2 text-sm text-ink-600">
                    Buyer: <span className="font-medium text-ink-900">{requestData.buyer_profile?.business_name}</span>
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-8">
                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm sm:p-8">
                    <h3 className="text-lg font-semibold text-ink-900 mb-6">Pricing per Item (TZS)</h3>

                    {error && (
                        <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    <div className="space-y-6">
                        {requestData.items?.map((item) => (
                            <div key={item.id} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-border last:border-0 last:pb-0">
                                <div>
                                    <p className="font-semibold text-ink-900">{item.product?.name}</p>
                                    <p className="text-sm text-ink-500">Quantity requested: {item.quantity} {item.unit}</p>
                                </div>
                                <div className="w-full sm:w-64">
                                    <label htmlFor={`price-${item.id}`} className="mb-1 block text-xs font-medium text-ink-600">
                                        Unit Price (TZS)
                                    </label>
                                    <input
                                        id={`price-${item.id}`}
                                        type="number"
                                        step="0.01"
                                        required
                                        placeholder="e.g. 25000"
                                        value={prices[item.id] || ''}
                                        onChange={(e) => handlePriceChange(item.id, e.target.value)}
                                        className="w-full rounded-xl border border-border bg-surface px-4 py-2.5 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm sm:p-8">
                    <h3 className="text-lg font-semibold text-ink-900 mb-4">Delivery & Payment Terms</h3>
                    <label htmlFor="notes" className="sr-only">Notes</label>
                    <textarea
                        id="notes"
                        rows="3"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="e.g., Includes transport to site. 50% advance payment required."
                        className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                    />
                </div>

                <button
                    type="submit"
                    disabled={submitting}
                    className="w-full rounded-xl bg-pal-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-pal-700 disabled:opacity-60 cursor-pointer"
                >
                    {submitting ? 'Submitting Official Quotation...' : 'Submit Official Quotation (TZS)'}
                </button>
            </form>
        </div>
    );
}