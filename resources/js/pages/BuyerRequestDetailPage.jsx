import React, { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getBuyerRequest, acceptQuotation } from '../lib/api';

export default function BuyerRequestDetailPage() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    
    const [acceptingId, setAcceptingId] = useState(null);
    const [error, setError] = useState('');

    // Fetch the request and its relations (items, quotations, etc.)
    const { data, isLoading } = useQuery({
        queryKey: ['buyer-request', id],
        queryFn: () => getBuyerRequest(id),
    });

    const requestData = data?.buyer_request;

    async function handleAccept(quotationId) {
        if (!window.confirm("Are you sure you want to accept this quotation? This will create a binding Purchase Order.")) return;
        
        setAcceptingId(quotationId);
        setError('');
        
        try {
            await acceptQuotation(quotationId);
            // Refresh the data so the UI updates to show it was accepted
            queryClient.invalidateQueries(['buyer-request', id]);
            alert("Quotation accepted successfully! A Purchase Order has been generated.");
        } catch (err) {
            setError(err.data?.message || 'Failed to accept quotation.');
        } finally {
            setAcceptingId(null);
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
                <p className="text-sm text-red-500">Request not found.</p>
                <Link to="/buyer" className="text-pal-600 hover:underline">Back to Dashboard</Link>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl px-6 py-16 font-manrope">
            <div className="mb-8 flex items-center justify-between">
                <div>
                    <Link to="/buyer" className="text-sm font-medium text-ink-500 hover:text-pal-600">
                        &larr; Back to Buyer Workspace
                    </Link>
                    <h1 className="mt-4 text-3xl font-semibold tracking-tight text-ink-950">
                        {requestData.title}
                    </h1>
                    <p className="mt-2 text-sm text-ink-600">
                        Status: <span className="font-medium uppercase tracking-wider">{requestData.status}</span>
                    </p>
                </div>
            </div>

            {error && (
                <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {error}
                </div>
            )}

            <div className="mb-8 rounded-2xl border border-border bg-white p-6 shadow-sm">
                <h3 className="mb-4 text-lg font-semibold text-ink-900">Requested Items</h3>
                <ul className="divide-y divide-border">
                    {requestData.items?.map(item => (
                        <li key={item.id} className="py-3 flex justify-between text-sm">
                            <span className="font-medium text-ink-900">{item.product?.name || 'Product'}</span>
                            <span className="text-ink-600">{item.quantity} {item.unit}</span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="mb-8">
                <h2 className="mb-4 text-2xl font-semibold text-ink-950">Supplier Quotations</h2>
                
                {!requestData.quotations || requestData.quotations.length === 0 ? (
                    <div className="rounded-2xl border border-border bg-surface p-8 text-center text-sm text-ink-500">
                        No bids received yet.
                    </div>
                ) : (
                    <div className="space-y-6">
                        {requestData.quotations.map(quotation => {
                            // Calculate total if the backend doesn't provide it
                            const totalAmount = quotation.items?.reduce((sum, item) => sum + (Number(item.unit_price) * Number(item.quantity)), 0) || 0;

                            return (
                                <div key={quotation.id} className="rounded-2xl border border-border bg-white p-6 shadow-sm transition hover:shadow-md">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-border pb-4 mb-4">
                                        <div>
                                            <h3 className="text-lg font-semibold text-ink-900">
                                                {quotation.supplier?.business_name || 'Supplier'}
                                            </h3>
                                            <p className="text-sm text-ink-500">Status: {quotation.status}</p>
                                        </div>
                                        <div className="mt-4 sm:mt-0 text-left sm:text-right">
                                            <p className="text-xs text-ink-500 uppercase tracking-wider">Total Bid</p>
                                            <p className="text-xl font-bold text-pal-600">
                                                {totalAmount.toLocaleString()} TZS
                                            </p>
                                        </div>
                                    </div>

                                    {/* Item Breakdown */}
                                    <div className="mb-6">
                                        <h4 className="text-xs font-semibold text-ink-500 uppercase tracking-wider mb-2">Item Breakdown</h4>
                                        <ul className="space-y-2 text-sm text-ink-800">
                                            {quotation.items?.map(item => (
                                                <li key={item.id} className="flex justify-between">
                                                    <span>{item.quantity} {item.unit} x {Number(item.unit_price).toLocaleString()} TZS</span>
                                                    <span className="font-medium">{(item.quantity * item.unit_price).toLocaleString()} TZS</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>

                                    {quotation.status === 'submitted' ? (
                                        <button
                                            onClick={() => handleAccept(quotation.id)}
                                            disabled={acceptingId === quotation.id}
                                            className="w-full sm:w-auto rounded-xl bg-pal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-pal-700 disabled:opacity-60"
                                        >
                                            {acceptingId === quotation.id ? 'Accepting...' : 'Accept Quotation & Create PO'}
                                        </button>
                                    ) : (
                                        <span className="inline-block rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                                            Already {quotation.status}
                                        </span>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}