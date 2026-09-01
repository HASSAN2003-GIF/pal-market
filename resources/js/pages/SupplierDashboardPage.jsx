import React from 'react';
import { useAuth } from '../context/AuthContext';
import { Link } from 'react-router-dom';
import { useMarketRequests } from '../hooks/useMarketRequests';

export default function SupplierDashboardPage() {
    const { user } = useAuth();
    const { data, isLoading } = useMarketRequests();

    const requests = data?.buyer_requests || [];

    return (
        <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="mb-8">
                <h1 className="text-3xl font-semibold tracking-tight text-ink-950">
                    Supplier Workspace
                </h1>
                <p className="mt-2 text-sm text-ink-600">
                    Welcome back, {user?.name}. Browse open market requests and submit your quotations.
                </p>
            </div>

            <div className="mb-6 flex border-b border-border">
                <button className="border-b-2 border-pal-600 px-4 py-3 text-sm font-semibold text-pal-600">
                    Open Market Requests
                </button>
                <button className="border-b-2 border-transparent px-4 py-3 text-sm font-medium text-ink-500 hover:text-ink-700">
                    My Active Bids
                </button>
            </div>

            {isLoading ? (
                <div className="rounded-2xl border border-border bg-white shadow-sm p-12 text-center">
                    <p className="text-sm text-ink-500">Loading market feed...</p>
                </div>
            ) : requests.length === 0 ? (
                <div className="rounded-2xl border border-border bg-white shadow-sm p-12 text-center">
                    <h3 className="text-base font-semibold text-ink-900">No open requests</h3>
                    <p className="mt-2 text-sm text-ink-500">
                        There are currently no active buyer requests on the market. Check back later.
                    </p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <ul className="divide-y divide-border">
                        {requests.map((req) => (
                            <li key={req.id} className="p-6 transition hover:bg-surface-soft">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 className="text-base font-semibold text-ink-900">
                                            {req.title}
                                        </h4>
                                        <p className="mt-1 text-sm text-ink-500">
                                            Requested by: <span className="font-medium text-ink-700">{req.buyer_profile?.business_name || 'A Buyer'}</span>
                                        </p>
                                        <p className="mt-1 text-sm text-ink-500">
                                            {req.items?.length || 0} items requested
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="rounded-xl bg-pal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-pal-700"
                                    >
                                        Submit Quotation
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}