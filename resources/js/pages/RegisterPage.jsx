import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function RegisterPage() {
    const navigate = useNavigate();
    const { register } = useAuth();

    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    function handleChange(event) {
        const { name, value } = event.target;

        setForm((current) => ({
            ...current,
            [name]: value,
        }));
    }

    async function handleSubmit(event) {
        event.preventDefault();

        setError('');
        setLoading(true);

        try {
            const response = await register(form);

            const role = response.user?.role;

            if (role === 'admin') {
                navigate('/admin', { replace: true });
            } else if (role === 'supplier') {
                navigate('/supplier', { replace: true });
            } else {
                navigate('/dashboard', { replace: true });
            }
        } catch (err) {
            if (err.data?.errors) {
                const firstError = Object.values(err.data.errors)
                    .flat()
                    .at(0);

                setError(firstError || err.message);
            } else {
                setError(err.message);
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <section className="min-h-[calc(100vh-4rem)] bg-surface">
            <div className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-6xl items-center justify-center px-6 py-16">
                <div className="w-full max-w-md">
                    <div className="mb-8">
                        <p className="text-sm font-semibold text-pal-600">
                            PAL Market
                        </p>

                        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-ink-950">
                            Create your account
                        </h1>

                        <p className="mt-2 text-sm leading-6 text-ink-600">
                            Join PAL Market and manage your construction
                            marketplace activity in one place.
                        </p>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="rounded-2xl border border-border bg-white p-6 shadow-sm"
                    >
                        {error && (
                            <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {error}
                            </div>
                        )}

                        <div className="space-y-5">
                            <div>
                                <label
                                    htmlFor="name"
                                    className="mb-2 block text-sm font-medium text-ink-800"
                                >
                                    Full name
                                </label>

                                <input
                                    id="name"
                                    name="name"
                                    type="text"
                                    value={form.name}
                                    onChange={handleChange}
                                    autoComplete="name"
                                    required
                                    className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="email"
                                    className="mb-2 block text-sm font-medium text-ink-800"
                                >
                                    Email address
                                </label>

                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value={form.email}
                                    onChange={handleChange}
                                    autoComplete="email"
                                    required
                                    className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password"
                                    className="mb-2 block text-sm font-medium text-ink-800"
                                >
                                    Password
                                </label>

                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    value={form.password}
                                    onChange={handleChange}
                                    autoComplete="new-password"
                                    required
                                    className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password_confirmation"
                                    className="mb-2 block text-sm font-medium text-ink-800"
                                >
                                    Confirm password
                                </label>

                                <input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    value={form.password_confirmation}
                                    onChange={handleChange}
                                    autoComplete="new-password"
                                    required
                                    className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={loading}
                                className="w-full rounded-xl bg-pal-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-pal-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {loading
                                    ? 'Creating account...'
                                    : 'Create account'}
                            </button>
                        </div>
                    </form>

                    <p className="mt-6 text-center text-sm text-ink-600">
                        Already have an account?{' '}
                        <Link
                            to="/login"
                            className="font-semibold text-pal-700 hover:text-pal-800"
                        >
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </section>
    );
}