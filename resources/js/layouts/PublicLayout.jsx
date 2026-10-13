import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import HeaderSearch from '../components/HeaderSearch';
import Logo from '../components/Logo';
import SiteFooter from '../components/SiteFooter';
import { AreaButton, LanguageSwitch, ThemeMenu } from '../components/PreferenceControls';
import { CloseIcon, MenuIcon } from '../components/icons';
import { ButtonLink, Notice, cx } from '../components/ui';
import { homePathFor, useAuth } from '../context/AuthContext';
import { useT } from '../i18n';

/** Sends the optional /#admin shortcut into the protected /admin route. */
export function useAdminHashShortcut() {
    const location = useLocation();
    const navigate = useNavigate();
    useEffect(() => {
        if (location.hash === '#admin') navigate('/admin', { replace: true });
    }, [location.hash, navigate]);
}

const navLinkClass = ({ isActive }) =>
    cx('whitespace-nowrap rounded-[6px] px-2.5 py-1.5 text-sm font-semibold', isActive ? 'bg-pal-50 text-pal-700' : 'text-ink-soft hover:bg-concrete hover:text-ink');

export default function PublicLayout() {
    const t = useT();
    const { user, sessionNotice, dismissSessionNotice } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    useAdminHashShortcut();

    useEffect(() => setMenuOpen(false), [location.pathname]);

    const accountLinks = user ? (
        <ButtonLink to={homePathFor(user)} size="sm">
            {t('layout.myWorkspace')}
        </ButtonLink>
    ) : (
        <>
            <ButtonLink to="/login" variant="ghost" size="sm">
                {t('layout.signIn')}
            </ButtonLink>
            <ButtonLink to="/register" size="sm">
                {t('layout.createAccount')}
            </ButtonLink>
        </>
    );

    const links = [
        ['/materials', 'layout.navMaterials'],
        [user?.role === 'buyer' ? '/buyer/requests/new' : '/register?role=buyer', 'layout.navRequest'],
        ['/register?role=supplier', 'layout.navSell'],
        ['/help', 'layout.navHelp'],
    ].filter(([to]) => !(user && to.startsWith('/register')));

    return (
        <div className="flex min-h-screen flex-col">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-paper focus:px-3 focus:py-2">
                {t('layout.skipToContent')}
            </a>

            <header className="sticky top-0 z-30 border-b border-rule bg-paper/95 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2.5 sm:px-6 lg:gap-5">
                    <Link to="/" aria-label={t('layout.home')} className="shrink-0 py-1">
                        <Logo className="h-6 sm:h-7" />
                    </Link>
                    <div className="hidden max-w-[11rem] shrink-0 lg:block">
                        <AreaButton className="max-w-full" />
                    </div>
                    <div className="hidden min-w-0 flex-1 md:block">
                        <HeaderSearch />
                    </div>
                    <div className="ml-auto flex items-center gap-2 md:ml-0">
                        <LanguageSwitch />
                        <ThemeMenu />
                        <div className="hidden items-center gap-2 lg:flex">{accountLinks}</div>
                        <button
                            type="button"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-control)] border border-rule lg:hidden"
                            aria-expanded={menuOpen}
                            aria-controls="public-menu"
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            {menuOpen ? <CloseIcon /> : <MenuIcon />}
                            <span className="sr-only">{menuOpen ? t('layout.closeMenu') : t('layout.openMenu')}</span>
                        </button>
                    </div>
                </div>

                {/* Small screens: search and area get their own rows. */}
                <div className="space-y-1 px-4 pb-2.5 sm:px-6 md:hidden">
                    <HeaderSearch />
                </div>
                <div className="border-t border-rule bg-concrete/60 px-3 lg:hidden">
                    <AreaButton compact className="max-w-full py-1.5" />
                </div>

                <nav aria-label={t('layout.mainNav')} className="hidden border-t border-rule lg:block">
                    <div className="mx-auto flex max-w-6xl items-center gap-1 px-4 py-1.5 sm:px-6">
                        {links.map(([to, label]) => (
                            <NavLink key={label} to={to} end={to === '/materials'} className={navLinkClass}>
                                {t(label)}
                            </NavLink>
                        ))}
                    </div>
                </nav>

                {menuOpen && (
                    <div id="public-menu" className="border-t border-rule bg-paper px-4 py-4 lg:hidden">
                        <nav aria-label={t('layout.mainNav')}>
                            <ul className="space-y-1">
                                {links.map(([to, label]) => (
                                    <li key={label}>
                                        <NavLink to={to} className={({ isActive }) => cx(navLinkClass({ isActive }), 'block py-2.5 text-base')}>
                                            {t(label)}
                                        </NavLink>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                        <div className="mt-4 flex flex-wrap gap-2 border-t border-rule pt-4">{accountLinks}</div>
                    </div>
                )}
            </header>

            {sessionNotice && (
                <div className="mx-auto mt-4 w-full max-w-6xl px-4 sm:px-6">
                    <Notice
                        tone="attention"
                        title={t(sessionNotice)}
                        action={
                            <button type="button" className="text-sm font-semibold text-pal-700 hover:underline" onClick={dismissSessionNotice}>
                                {t('common.dismiss')}
                            </button>
                        }
                    />
                </div>
            )}

            <main id="main" className="flex-1">
                <Outlet />
            </main>

            <SiteFooter />
        </div>
    );
}
