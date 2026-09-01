import React, { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useBuyerRequest } from '../hooks/useBuyerRequest';
import { publishBuyerRequest } from '../lib/api';

export default function RequestDetailPage() {
    const { requestId } = useParams();
    const { data, isLoading, error } = useBuyerRequest(requestId);

    const [publishing, setPublishing] = useState(false);

    if (isLoading) {
        return (
            <div className="mx-auto max-w-7xl px-6 py-16 text-center">
                <p className="text-sm text-ink-500">Loading request details...</p>
            </div>
        );
    }

    if (error || !data?.buyer_request) {
        return (
            <div className="mx-auto max-w-7xl px-6 py-16 text-center">
                <p className="text-sm text-red-500">Failed to load request.</p>
                <Link to="/dashboard" className="mt-4 inline-block text-pal-600 hover:underline">
                    Return to Dashboard
                </Link>
            </div>
        );
    }

    const request = data.buyer_request;
    const items = request.items || [];

    async function handlePublish() {
        if (!window.confirm("Are you sure? Once published, suppliers will begin preparing quotations.")) {
            return;
        }
        
        setPublishing(true);
        try {
            await publishBuyerRequest(request.id);
            // Refresh the page to get the updated status from the server
            window.location.reload(); 
        } catch (err) {
            alert(err.data?.message || 'Failed to publish request.');
            setPublishing(false);
        }
    }

    return (
        <div className="mx-auto max-w-5xl px-6 py-16">
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link to="/dashboard" className="text-sm font-medium text-ink-500 hover:text-pal-600">
                        &larr; Back to Dashboard
                    </Link>
                    <h1 className="mt-4 text-3xl font-semibold tracking-tight text-ink-950">
                        {request.title}
                    </h1>
                    {request.description && (
                        <p className="mt-2 text-sm text-ink-600 max-w-2xl">
                            {request.description}
                        </p>
                    )}
                    <div className="mt-4 inline-flex items-center rounded-full bg-surface-soft px-3 py-1 text-xs font-medium text-ink-700 capitalize border border-border">
                        Status: {request.status}
                    </div>
                </div>

                {request.status === 'draft' && (
                    <button
                        type="button"
                        onClick={handlePublish}
                        disabled={items.length === 0 || publishing}
                        className="shrink-0 rounded-xl bg-ink-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink-950 disabled:opacity-50"
                    >
                        {publishing ? 'Publishing...' : 'Publish to Suppliers'}
                    </button>
                )}
            </div>

            <div className="rounded-2xl border border-border bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-border p-6">
                    <h3 className="text-lg font-semibold text-ink-900">Requested Items</h3>
                    {request.status === 'draft' && (
                        <Link
                            to="/marketplace"
                            className="text-sm font-semibold text-pal-600 hover:text-pal-700"
                        >
                            + Add items from Marketplace
                        </Link>
                    )}
                </div>

                {items.length === 0 ? (
                    <div className="p-12 text-center">
                        <p className="text-sm text-ink-500">
                            No items added yet. Browse the marketplace to add materials to this request.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border">
                        {items.map((item) => (
                            <li key={item.id} className="p-6 flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-ink-900">{item.product?.name}</p>
                                    <p className="text-sm text-ink-500">
                                        Quantity: {item.quantity} {item.unit}
                                    </p>
                                </div>
                                {item.notes && (
                                    <p className="text-sm text-ink-500 italic">Note: {item.notes}</p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}