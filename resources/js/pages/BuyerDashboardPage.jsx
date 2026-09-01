import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useBuyerRequests } from '../hooks/useBuyerRequests';

export default function BuyerDashboardPage() {
    const { user } = useAuth();
    const { data, isLoading } = useBuyerRequests();

    // The backend returns { buyer_requests: [...] }
    const requests = data?.buyer_requests || [];

    return (
        <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight text-ink-950">
                        Buyer Dashboard
                    </h1>
                    <p className="mt-2 text-sm text-ink-600">
                        Welcome back, {user?.name}. Manage your material requests and supplier quotations here.
                    </p>
                </div>
                
                <Link
                    to="/requests/new"
                    className="shrink-0 rounded-xl bg-pal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-pal-700"
                >
                    + New Request
                </Link>
            </div>

            {isLoading ? (
                <div className="rounded-2xl border border-border bg-white p-12 text-center shadow-sm">
                    <p className="text-sm text-ink-500">Loading your requests...</p>
                </div>
            ) : requests.length === 0 ? (
                <div className="rounded-2xl border border-border bg-white p-12 text-center shadow-sm">
                    <h3 className="text-base font-semibold text-ink-900">
                        No active requests
                    </h3>
                    <p className="mt-2 text-sm text-ink-500">
                        You haven't requested any quotations from suppliers yet. Browse the marketplace to get started.
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
                                            {req.items?.length || 0} items • Status:{' '}
                                            <span className="font-medium text-pal-600 capitalize">
                                                {req.status}
                                            </span>
                                        </p>
                                    </div>
                                    <Link
                                        to={`/requests/${req.id}`}
                                        className="text-sm font-semibold text-pal-600 transition hover:text-pal-700"
                                    >
                                        View details &rarr;
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}