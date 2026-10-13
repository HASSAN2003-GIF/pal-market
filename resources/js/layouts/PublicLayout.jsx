import { Link, NavLink, Outlet } from 'react-router-dom';
import Logo from '../components/Logo';
import { ButtonLink, Notice } from '../components/ui';
import { homePathFor, useAuth } from '../context/AuthContext';

export default function PublicLayout() {
    const { user, sessionNotice, dismissSessionNotice } = useAuth();

    return (
        <div className="flex min-h-screen flex-col">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-paper focus:px-3 focus:py-2">
                Skip to content
            </a>
            <header className="border-b border-rule bg-paper">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
                    <Link to="/" aria-label="PAL Market home">
                        <Logo />
                    </Link>
                    <nav aria-label="Main" className="hidden sm:block">
                        <NavLink
                            to="/materials"
                            className={({ isActive }) =>
                                `font-semibold hover:text-pal-700 ${isActive ? 'text-pal-700' : 'text-ink-soft'}`
                            }
                        >
                            Browse materials
                        </NavLink>
                    </nav>
                    <div className="ml-auto flex items-center gap-2">
                        {user ? (
                            <ButtonLink to={homePathFor(user)} size="sm">
                                Open workspace
                            </ButtonLink>
                        ) : (
                            <>
                                <ButtonLink to="/login" variant="ghost" size="sm">
                                    Sign in
                                </ButtonLink>
                                <ButtonLink to="/register" size="sm">
                                    Create account
                                </ButtonLink>
                            </>
                        )}
                    </div>
                </div>
            </header>

            {sessionNotice && (
                <div className="mx-auto mt-4 w-full max-w-6xl px-4 sm:px-6">
                    <Notice
                        tone="attention"
                        title={sessionNotice}
                        action={
                            <button type="button" className="text-sm font-semibold text-pal-700 hover:underline" onClick={dismissSessionNotice}>
                                Dismiss
                            </button>
                        }
                    />
                </div>
            )}

            <main id="main" className="flex-1">
                <Outlet />
            </main>

            <footer className="border-t border-rule bg-paper">
                <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-muted sm:flex-row sm:justify-between sm:px-6">
                    <p>PAL Market connects construction-material buyers with approved suppliers in Tanzania.</p>
                    <nav aria-label="Footer" className="flex gap-4">
                        <Link className="hover:text-ink" to="/materials">
                            Materials
                        </Link>
                        <Link className="hover:text-ink" to="/register?role=supplier">
                            Sell on PAL Market
                        </Link>
                    </nav>
                </div>
            </footer>
        </div>
    );
}
