import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function AppLayout({ children }) {
    const { user, isAuthenticated, logout } = useAuth();

    async function handleLogout() {
        await logout();
    }

    return (
        <div className="min-h-screen bg-surface text-ink-900">
            <header className="border-b border-border bg-white">
                <div className="mx-auto max-w-7xl px-6">
                    <div className="flex h-[72px] items-center gap-8">
                        <Link
                            to="/"
                            className="shrink-0 text-xl font-extrabold tracking-[-0.03em] text-ink-950"
                        >
                            PAL<span className="text-pal-600">.</span>
                        </Link>

                        <Link
                            to="/marketplace"
                            className="hidden text-sm font-semibold text-ink-700 transition hover:text-pal-600 md:block"
                        >
                            Marketplace
                        </Link>

                        <div className="hidden h-6 w-px bg-border md:block" />

                        <nav className="ml-auto flex items-center gap-7 text-sm font-semibold">
                            <Link
                                to="/marketplace"
                                className="text-ink-700 transition hover:text-pal-600"
                            >
                                Browse
                            </Link>

                            {isAuthenticated ? (
                                <>
                                    <Link
                                        to="/dashboard"
                                        className="text-ink-700 transition hover:text-pal-600"
                                    >
                                        Dashboard
                                    </Link>

                                    <span className="hidden text-ink-500 md:block">
                                        {user?.name}
                                    </span>

                                    <button
                                        type="button"
                                        onClick={handleLogout}
                                        className="rounded-lg border border-border bg-white px-4 py-2.5 text-ink-700 transition hover:border-pal-200 hover:text-pal-700"
                                    >
                                        Sign out
                                    </button>
                                </>
                            ) : (
                                <>
                                    <Link
                                        to="/login"
                                        className="text-ink-700 transition hover:text-pal-600"
                                    >
                                        Sign in
                                    </Link>

                                    <Link
                                        to="/register"
                                        className="rounded-lg bg-pal-600 px-4 py-2.5 text-white shadow-sm transition hover:bg-pal-700"
                                    >
                                        Create account
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </div>
            </header>

            <main>{children}</main>
        </div>
    );
}