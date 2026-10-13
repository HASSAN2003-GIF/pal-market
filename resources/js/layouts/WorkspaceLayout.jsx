import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import Logo from '../components/Logo';
import SiteFooter from '../components/SiteFooter';
import { AreaButton, LanguageSwitch, ThemeMenu } from '../components/PreferenceControls';
import { CloseIcon, MenuIcon } from '../components/icons';
import { Notice, cx } from '../components/ui';
import { useAuth } from '../context/AuthContext';
import { useT } from '../i18n';
import { useAdminHashShortcut } from './PublicLayout';

const NAV = {
    buyer: [
        { to: '/buyer', label: 'nav.buyerHome', end: true },
        { to: '/buyer/requests/new', label: 'nav.newRequest' },
        { to: '/buyer/requests', label: 'nav.myRequests', end: true },
        { to: '/orders', label: 'nav.orders' },
        { to: '/materials', label: 'nav.materials' },
    ],
    supplier: [
        { to: '/supplier', label: 'nav.sellerHome', end: true },
        { to: '/supplier/market', label: 'nav.market' },
        { to: '/supplier/quotations', label: 'nav.myOffers' },
        { to: '/orders', label: 'nav.orders' },
        { to: '/supplier/catalog', label: 'nav.catalog' },
        { to: '/supplier/locations', label: 'nav.locations' },
    ],
    admin: [
        { to: '/admin', label: 'nav.adminHome', end: true },
        { to: '/admin/suppliers', label: 'nav.adminSellers' },
        { to: '/admin/buyers', label: 'nav.adminBuyers' },
        { to: '/admin/users', label: 'nav.adminUsers' },
        { to: '/admin/catalog', label: 'nav.adminCatalog' },
        { to: '/admin/requests', label: 'nav.adminRequests' },
        { to: '/admin/orders', label: 'nav.adminOrders' },
        { to: '/admin/audit-log', label: 'nav.adminActivity' },
    ],
};

const itemClass = ({ isActive }) =>
    cx(
        'flex items-center rounded-[var(--radius-control)] px-3 py-2.5 font-semibold',
        isActive ? 'bg-inverse text-on-inverse' : 'text-ink-soft hover:bg-concrete-deep hover:text-ink',
    );

function NavItems({ items, onNavigate }) {
    const t = useT();
    return (
        <ul className="space-y-0.5">
            {items.map((item) => (
                <li key={item.to}>
                    <NavLink to={item.to} end={item.end} onClick={onNavigate} className={itemClass}>
                        {t(item.label)}
                    </NavLink>
                </li>
            ))}
            <li className="pt-2">
                <NavLink to="/account" onClick={onNavigate} className={itemClass}>
                    {t('nav.account')}
                </NavLink>
            </li>
            <li>
                <NavLink to="/help" onClick={onNavigate} className={itemClass}>
                    {t('nav.help')}
                </NavLink>
            </li>
        </ul>
    );
}

export default function WorkspaceLayout() {
    const t = useT();
    const { user, logout } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const items = NAV[user?.role] ?? NAV.buyer;
    useAdminHashShortcut();

    useEffect(() => setMenuOpen(false), [location.pathname]);

    const accountBlock = (
        <div className="border-t border-rule pt-4">
            <p className="truncate font-semibold text-ink">{user?.name}</p>
            <p className="truncate text-sm text-muted">
                {t(`nav.role.${user?.role ?? 'buyer'}`)}
                {user?.supplier && `, ${user.supplier.business_name}`}
                {user?.buyer_profile && `, ${user.buyer_profile.business_name}`}
            </p>
            <button type="button" onClick={logout} className="mt-3 text-sm font-semibold text-pal-700 hover:underline">
                {t('layout.signOut')}
            </button>
        </div>
    );

    return (
        <div className="min-h-screen md:grid md:grid-cols-[15.5rem_1fr]">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-paper focus:px-3 focus:py-2">
                {t('layout.skipToContent')}
            </a>

            {/* Desktop rail */}
            <aside className="sticky top-0 hidden h-screen flex-col gap-5 border-r border-rule bg-paper px-3 py-5 md:flex">
                <Link to="/" className="px-2" aria-label={t('layout.home')}>
                    <Logo className="h-7" />
                </Link>
                <nav aria-label={t('layout.workspaceNav')} className="flex-1 overflow-y-auto">
                    <NavItems items={items} />
                </nav>
                <div className="px-2">{accountBlock}</div>
            </aside>

            <div className="flex min-w-0 flex-col">
                {/* Top bar: area, language, theme (and the menu on small screens) */}
                <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-rule bg-paper/95 px-3 backdrop-blur sm:px-4">
                    <Link to="/" aria-label={t('layout.home')} className="shrink-0 md:hidden">
                        <Logo className="h-5" />
                    </Link>
                    <AreaButton compact className="max-w-[10rem] sm:max-w-[14rem]" />
                    <div className="ml-auto flex items-center gap-2">
                        <LanguageSwitch />
                        <ThemeMenu />
                        <button
                            type="button"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-control)] border border-rule md:hidden"
                            aria-expanded={menuOpen}
                            aria-controls="workspace-menu"
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            {menuOpen ? <CloseIcon /> : <MenuIcon />}
                            <span className="sr-only">{menuOpen ? t('layout.closeMenu') : t('layout.openMenu')}</span>
                        </button>
                    </div>
                </header>
                {menuOpen && (
                    <div id="workspace-menu" className="fixed inset-x-0 top-14 bottom-0 z-20 overflow-y-auto bg-paper px-3 py-4 md:hidden">
                        <nav aria-label={t('layout.workspaceNav')}>
                            <NavItems items={items} onNavigate={() => setMenuOpen(false)} />
                        </nav>
                        <div className="mt-6 px-2">{accountBlock}</div>
                    </div>
                )}

                <main id="main" className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                    <div className="mx-auto max-w-6xl">
                        <AccountStatusBanner user={user} />
                        <Outlet />
                    </div>
                </main>
                <SiteFooter compact />
            </div>
        </div>
    );
}

function AccountStatusBanner({ user }) {
    const t = useT();
    if (user?.supplier?.status === 'pending') {
        return (
            <Notice tone="attention" title={t('nav.pendingTitle')} className="mb-6">
                {t('nav.pendingBody')}
            </Notice>
        );
    }
    if (user?.supplier?.status === 'suspended' || user?.buyer_profile?.status === 'suspended') {
        return (
            <Notice tone="bad" title={t('nav.pausedTitle')} className="mb-6">
                {t('nav.pausedBody')}
            </Notice>
        );
    }
    return null;
}
