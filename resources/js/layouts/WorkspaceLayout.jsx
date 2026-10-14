import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import Logo from '../components/Logo';
import SiteFooter from '../components/SiteFooter';
import { AreaButton, LanguageSwitch, ThemeMenu } from '../components/PreferenceControls';
import { BoxIcon, CloseIcon, HomeIcon, ListIcon, MoreIcon, PlusIcon, StoreIcon, TagIcon, UsersIcon } from '../components/icons';
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
        { to: '/admin/proposals', label: 'nav.adminProposals' },
        { to: '/admin/requests', label: 'nav.adminRequests' },
        { to: '/admin/orders', label: 'nav.adminOrders' },
        { to: '/admin/audit-log', label: 'nav.adminActivity' },
    ],
};

/** The four things each role does most, for the phone tab bar. Everything else is under "More". */
const TABS = {
    buyer: [
        { to: '/buyer', label: 'nav.tabHome', icon: HomeIcon, end: true },
        { to: '/buyer/requests/new', label: 'nav.tabAsk', icon: PlusIcon },
        { to: '/buyer/requests', label: 'nav.tabRequests', icon: ListIcon, active: (path) => path.startsWith('/buyer/requests') && path !== '/buyer/requests/new' },
        { to: '/orders', label: 'nav.tabOrders', icon: BoxIcon },
    ],
    supplier: [
        { to: '/supplier', label: 'nav.tabHome', icon: HomeIcon, end: true },
        { to: '/supplier/market', label: 'nav.tabRequests', icon: ListIcon },
        { to: '/supplier/quotations', label: 'nav.tabOffers', icon: TagIcon },
        { to: '/orders', label: 'nav.tabOrders', icon: BoxIcon },
    ],
    admin: [
        { to: '/admin', label: 'nav.tabHome', icon: HomeIcon, end: true },
        { to: '/admin/suppliers', label: 'nav.tabSellers', icon: StoreIcon },
        { to: '/admin/users', label: 'nav.tabUsers', icon: UsersIcon },
        { to: '/admin/catalog', label: 'nav.tabProducts', icon: TagIcon },
    ],
};

const itemClass = ({ isActive }) =>
    cx(
        'flex min-h-11 items-center rounded-[var(--radius-control)] px-3 py-2 font-semibold lg:min-h-10',
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

const tabClass = ({ isActive }) =>
    cx(
        'flex h-full min-w-0 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-center text-xs leading-tight font-semibold',
        isActive ? 'text-pal-700' : 'text-ink-soft',
    );

export default function WorkspaceLayout() {
    const t = useT();
    const { user, logout } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const role = user?.role ?? 'buyer';
    const items = NAV[role] ?? NAV.buyer;
    const tabs = TABS[role] ?? TABS.buyer;
    useAdminHashShortcut();

    useEffect(() => setMenuOpen(false), [location.pathname]);

    useEffect(() => {
        if (!menuOpen) return undefined;
        const onKey = (event) => event.key === 'Escape' && setMenuOpen(false);
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [menuOpen]);

    const accountBlock = (
        <div className="border-t border-rule pt-4">
            <p className="truncate font-semibold text-ink">{user?.name}</p>
            <p className="text-sm break-words text-muted">
                {t(`nav.role.${role}`)}
                {user?.supplier && `, ${user.supplier.business_name}`}
                {user?.buyer_profile && `, ${user.buyer_profile.business_name}`}
            </p>
            <button type="button" onClick={logout} className="tap mt-1 -ml-2 px-2 text-sm font-semibold text-pal-700 hover:underline">
                {t('layout.signOut')}
            </button>
        </div>
    );

    return (
        <div className="min-h-screen md:grid md:grid-cols-[14rem_1fr] lg:grid-cols-[15.5rem_1fr]">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-paper focus:px-3 focus:py-2">
                {t('layout.skipToContent')}
            </a>

            {/* Rail from tablet width up */}
            <aside className="sticky top-0 hidden h-screen flex-col gap-5 border-r border-rule bg-paper px-3 py-5 md:flex">
                <Link to="/" className="inline-block self-start px-2 py-2" aria-label={t('layout.home')}>
                    <Logo className="h-7" />
                </Link>
                <nav aria-label={t('layout.workspaceNav')} className="flex-1 overflow-y-auto">
                    <NavItems items={items} />
                </nav>
                <div className="px-2">{accountBlock}</div>
            </aside>

            <div className="flex min-w-0 flex-col">
                {/* Top bar: area, language, theme. Not sticky on short landscape screens. */}
                <header className="sticky top-0 z-30 flex min-h-14 items-center gap-2 border-b border-rule bg-paper/95 px-3 py-1.5 backdrop-blur sm:px-4 [@media(max-height:500px)]:static">
                    <Link to="/" aria-label={t('layout.home')} className="shrink-0 py-2.5 md:hidden">
                        <Logo className="h-5" />
                    </Link>
                    <AreaButton compact className="min-w-0 max-w-[11rem] sm:max-w-[16rem]" />
                    <div className="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                        <LanguageSwitch />
                        <ThemeMenu />
                    </div>
                </header>

                <main id="main" className="min-w-0 flex-1 px-4 pt-5 pb-8 sm:px-6 lg:px-10 lg:pt-8">
                    <div className="mx-auto max-w-6xl 2xl:max-w-7xl">
                        <AccountStatusBanner user={user} />
                        <Outlet />
                    </div>
                </main>
                <div className="pb-[calc(4rem+env(safe-area-inset-bottom))] md:pb-0">
                    <SiteFooter compact />
                </div>
            </div>

            {/* Phone tab bar: the four most used pages plus "More" */}
            <nav
                aria-label={t('layout.workspaceNav')}
                className="fixed inset-x-0 bottom-0 z-40 border-t border-rule bg-paper/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
            >
                <ul className="flex h-16 items-stretch [@media(max-height:500px)]:h-12">
                    {tabs.map(({ to, label, icon: TabIcon, end, active }) => (
                        <li key={to} className="flex min-w-0 flex-1">
                            <NavLink
                                to={to}
                                end={end}
                                className={({ isActive }) => tabClass({ isActive: active ? active(location.pathname) : isActive })}
                                aria-current={active?.(location.pathname) ? 'page' : undefined}
                            >
                                <TabIcon className="h-6 w-6 shrink-0 [(max-height:500px)]:h-5 [(max-height:500px)]:w-5" />
                                <span className="line-clamp-2 break-words">{t(label)}</span>
                            </NavLink>
                        </li>
                    ))}
                    <li className="flex min-w-0 flex-1">
                        <button
                            type="button"
                            className={tabClass({ isActive: menuOpen })}
                            aria-expanded={menuOpen}
                            aria-controls="workspace-menu"
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            {menuOpen ? <CloseIcon className="h-6 w-6" /> : <MoreIcon className="h-6 w-6" />}
                            <span>{t('nav.tabMore')}</span>
                        </button>
                    </li>
                </ul>
            </nav>
            {menuOpen && (
                <div
                    id="workspace-menu"
                    className="fixed inset-x-0 top-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 overflow-y-auto bg-paper px-3 pt-4 pb-6 md:hidden"
                >
                    <div className="mb-4 flex items-center justify-between px-2">
                        <Logo className="h-6" />
                        <button
                            type="button"
                            onClick={() => setMenuOpen(false)}
                            className="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-control)] border border-rule"
                        >
                            <CloseIcon />
                            <span className="sr-only">{t('layout.closeMenu')}</span>
                        </button>
                    </div>
                    <nav aria-label={t('layout.workspaceNav')}>
                        <NavItems items={items} onNavigate={() => setMenuOpen(false)} />
                    </nav>
                    <div className="mt-6 px-2">{accountBlock}</div>
                </div>
            )}
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
