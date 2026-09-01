import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { createBuyerRequest } from '../lib/api';

export default function CreateRequestPage() {
    const navigate = useNavigate();
    const [form, setForm] = useState({ title: '', description: '' });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    function handleChange(event) {
        setForm({ ...form, [event.target.name]: event.target.value });
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await createBuyerRequest(form);
            // After creating the request, we will eventually route them to the specific request detail page to add items.
            // For now, we route them back to the dashboard to see it in the list.
            navigate('/dashboard');
        } catch (err) {
            setError(err.data?.message || 'Failed to create request.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="mx-auto max-w-3xl px-6 py-16">
            <div className="mb-8">
                <Link to="/dashboard" className="text-sm font-medium text-ink-500 hover:text-pal-600">
                    &larr; Back to Dashboard
                </Link>
                <h1 className="mt-4 text-3xl font-semibold tracking-tight text-ink-950">
                    Start a New Quotation Request
                </h1>
                <p className="mt-2 text-sm text-ink-600">
                    Give your request a clear title so suppliers know what project they are quoting for.
                </p>
            </div>

            <form onSubmit={handleSubmit} className="rounded-2xl border border-border bg-white p-6 shadow-sm sm:p-8">
                {error && (
                    <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        {error}
                    </div>
                )}

                <div className="space-y-6">
                    <div>
                        <label htmlFor="title" className="mb-2 block text-sm font-medium text-ink-800">
                            Project Title <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            value={form.title}
                            onChange={handleChange}
                            required
                            placeholder="e.g., Masaki Villa Foundation Materials"
                            className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                        />
                    </div>

                    <div>
                        <label htmlFor="description" className="mb-2 block text-sm font-medium text-ink-800">
                            Additional Notes (Optional)
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            value={form.description}
                            onChange={handleChange}
                            rows={4}
                            placeholder="Any specific delivery requirements or overall project notes?"
                            className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                        />
                    </div>

                    <div className="flex items-center justify-end gap-4 pt-4 border-t border-border">
                        <Link to="/dashboard" className="text-sm font-semibold text-ink-600 hover:text-ink-900">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={loading}
                            className="rounded-xl bg-pal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-pal-700 disabled:opacity-60"
                        >
                            {loading ? 'Creating...' : 'Create Request'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}