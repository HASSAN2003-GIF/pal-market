import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { login } = useAuth();

    const [form, setForm] = useState({
        email: '',
        password: '',
    });

    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const redirectTo = location.state?.from || '/dashboard';

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
            const response = await login(form);

            const role = response.user?.role;

            if (role === 'admin') {
                navigate('/admin', { replace: true });
            } else if (role === 'supplier') {
                navigate('/supplier', { replace: true });
            } else {
                navigate(redirectTo, { replace: true });
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
                            Welcome back
                        </h1>

                        <p className="mt-2 text-sm leading-6 text-ink-600">
                            Sign in to manage your marketplace activity.
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
                                    autoComplete="current-password"
                                    required
                                    className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={loading}
                                className="w-full rounded-xl bg-pal-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-pal-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {loading ? 'Signing in...' : 'Sign in'}
                            </button>
                        </div>
                                            <div className="my-6 flex items-center gap-4">
                        <div className="h-px flex-1 bg-border" />
                        <span className="text-xs font-medium uppercase tracking-wide text-ink-500">
                            or
                        </span>
                        <div className="h-px flex-1 bg-border" />
                    </div>

                    <a
                        href="/auth/google/redirect"
                        className="flex w-full items-center justify-center gap-3 rounded-xl border border-border bg-white px-4 py-3 text-sm font-semibold text-ink-800 transition hover:border-ink-300 hover:bg-surface-soft"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            className="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path
                                fill="#4285F4"
                                d="M21.35 12.27c0-.71-.06-1.4-.18-2.05H12v3.88h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.22Z"
                            />
                            <path
                                fill="#34A853"
                                d="M12 21.5c2.63 0 4.84-.87 6.45-2.36l-3.14-2.45c-.87.58-1.98.93-3.31.93-2.54 0-4.69-1.72-5.46-4.03H3.29v2.53A9.74 9.74 0 0 0 12 21.5Z"
                            />
                            <path
                                fill="#FBBC05"
                                d="M6.54 13.59A5.86 5.86 0 0 1 6.23 12c0-.55.11-1.08.31-1.59V7.88H3.29A9.5 9.5 0 0 0 2.25 12c0 1.53.37 2.98 1.04 4.12l3.25-2.53Z"
                            />
                            <path
                                fill="#EA4335"
                                d="M12 6.38c1.43 0 2.71.49 3.72 1.45l2.79-2.79C16.84 3.5 14.63 2.5 12 2.5a9.74 9.74 0 0 0-8.71 5.38l3.25 2.53C7.31 8.1 9.46 6.38 12 6.38Z"
                            />
                        </svg>

                        Continue with Google
                    </a>
                    </form>

                    <p className="mt-6 text-center text-sm text-ink-600">
                        Don't have an account?{' '}
                        <Link
                            to="/register"
                            className="font-semibold text-pal-700 hover:text-pal-800"
                        >
                            Create one
                        </Link>
                    </p>
                </div>
            </div>
        </section>
    );
}