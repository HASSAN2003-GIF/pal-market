import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function RegisterPage() {
    const navigate = useNavigate();
    const { register } = useAuth();

    const [form, setForm] = useState({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        role: 'buyer', // NEW
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
    await register(form);

    navigate('/dashboard', {
        replace: true,
    });
}
        catch (err) {
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
                            Create your PAL Market account and start buying
                            construction materials from trusted suppliers.
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
                            {/* Account Type Selection */}
                            <div>
                                <label className="mb-2 block text-sm font-medium text-ink-800">
                                    I want to...
                                </label>
                                <div className="grid grid-cols-2 gap-4">
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, role: 'buyer' })}
                                        className={`rounded-xl border p-3 text-sm font-semibold transition ${
                                            form.role === 'buyer'
                                                ? 'border-pal-600 bg-pal-50 text-pal-700'
                                                : 'border-border bg-white text-ink-600 hover:border-pal-300'
                                        }`}
                                    >
                                        Buy Materials
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, role: 'supplier' })}
                                        className={`rounded-xl border p-3 text-sm font-semibold transition ${
                                            form.role === 'supplier'
                                                ? 'border-pal-600 bg-pal-50 text-pal-700'
                                                : 'border-border bg-white text-ink-600 hover:border-pal-300'
                                        }`}
                                    >
                                        Sell Materials
                                    </button>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        htmlFor="first_name"
                                        className="mb-2 block text-sm font-medium text-ink-800"
                                    >
                                        First name
                                    </label>

                                    <input
                                        id="first_name"
                                        name="first_name"
                                        type="text"
                                        value={form.first_name}
                                        onChange={handleChange}
                                        autoComplete="given-name"
                                        required
                                        className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="last_name"
                                        className="mb-2 block text-sm font-medium text-ink-800"
                                    >
                                        Last name
                                    </label>

                                    <input
                                        id="last_name"
                                        name="last_name"
                                        type="text"
                                        value={form.last_name}
                                        onChange={handleChange}
                                        autoComplete="family-name"
                                        required
                                        className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                    />
                                </div>
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
                                    htmlFor="phone"
                                    className="mb-2 block text-sm font-medium text-ink-800"
                                >
                                    Phone number
                                    <span className="ml-1 text-ink-400">
                                        optional
                                    </span>
                                </label>

                                <input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    value={form.phone}
                                    onChange={handleChange}
                                    autoComplete="tel"
                                    placeholder="+255..."
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
                                    minLength={8}
                                    className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-pal-500 focus:ring-2 focus:ring-pal-100"
                                />

                                <p className="mt-2 text-xs text-ink-500">
                                    Use at least 8 characters.
                                </p>
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