import { Link } from 'react-router-dom';

export default function AppLayout({ children }) {
    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
                    <Link
                        to="/"
                        className="text-lg font-semibold tracking-tight text-slate-900"
                    >
                        PAL Market
                    </Link>

                    <nav className="flex items-center gap-6 text-sm font-medium text-slate-600">
                        <Link
                            to="/"
                            className="transition hover:text-slate-900"
                        >
                            Marketplace
                        </Link>

                        <Link
                            to="/login"
                            className="transition hover:text-slate-900"
                        >
                            Sign in
                        </Link>

                        <Link
                            to="/register"
                            className="rounded-lg bg-slate-900 px-4 py-2 text-white transition hover:bg-slate-800"
                        >
                            Get started
                        </Link>
                    </nav>
                </div>
            </header>

            <main>{children}</main>
        </div>
    );
}