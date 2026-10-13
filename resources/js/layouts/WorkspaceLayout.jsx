import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import Logo from '../components/Logo';
import { Notice, cx } from '../components/ui';
import { useAuth } from '../context/AuthContext';

const NAV = {
    buyer: [
        { to: '/buyer', label: 'Overview', end: true },
        { to: '/buyer/requests', label: 'My requests' },
        { to: '/buyer/requests/new', label: 'New request' },
        { to: '/orders', label: 'Purchase orders' },
        { to: '/materials', label: 'Browse materials' },
    ],
    supplier: [
        { to: '/supplier', label: 'Overview', end: true },
        { to: '/supplier/market', label: 'Open requests' },
        { to: '/supplier/quotations', label: 'My quotations' },
        { to: '/orders', label: 'Purchase orders' },
        { to: '/supplier/catalog', label: 'Catalog & stock' },
        { to: '/supplier/locations', label: 'Locations' },
    ],
    admin: [
        { to: '/admin', label: 'Overview', end: true },
        { to: '/admin/suppliers', label: 'Suppliers' },
        { to: '/admin/buyers', label: 'Buyers' },
        { to: '/admin/users', label: 'User accounts' },
        { to: '/admin/catalog', label: 'Product catalog' },
        { to: '/admin/requests', label: 'Requests' },
        { to: '/admin/orders', label: 'Orders' },
        { to: '/admin/audit-log', label: 'Audit log' },
    ],
};

const ROLE_LABEL = { buyer: 'Buyer', supplier: 'Supplier', admin: 'Administrator' };

function NavItems({ items, onNavigate }) {
    return (
        <ul className="space-y-0.5">
            {items.map((item) => (
                <li key={item.to}>
                    <NavLink
                        to={item.to}
                        end={item.end}
                        onClick={onNavigate}
                        className={({ isActive }) =>
                            cx(
                                'flex items-center rounded-[var(--radius-control)] px-3 py-2 font-semibold',
                                isActive ? 'bg-ink text-white' : 'text-ink-soft hover:bg-concrete-deep hover:text-ink',
                            )
                        }
                    >
                        {item.label}
                    </NavLink>
                </li>
            ))}
            <li className="pt-2">
                <NavLink
                    to="/account"
                    onClick={onNavigate}
                    className={({ isActive }) =>
                        cx(
                            'flex items-center rounded-[var(--radius-control)] px-3 py-2 font-semibold',
                            isActive ? 'bg-ink text-white' : 'text-ink-soft hover:bg-concrete-deep hover:text-ink',
                        )
                    }
                >
                    Account settings
                </NavLink>
            </li>
        </ul>
    );
}

export default function WorkspaceLayout() {
    const { user, logout } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const items = NAV[user?.role] ?? NAV.buyer;

    useEffect(() => setMenuOpen(false), [location.pathname]);

    const accountBlock = (
        <div className="border-t border-rule pt-4">
            <p className="truncate font-semibold text-ink">{user?.name}</p>
            <p className="truncate text-sm text-muted">
                {ROLE_LABEL[user?.role]}
                {user?.supplier && `, ${user.supplier.business_name}`}
                {user?.buyer_profile && `, ${user.buyer_profile.business_name}`}
            </p>
            <button type="button" onClick={logout} className="mt-3 text-sm font-semibold text-pal-700 hover:underline">
                Sign out
            </button>
        </div>
    );

    return (
        <div className="min-h-screen md:grid md:grid-cols-[15rem_1fr]">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-paper focus:px-3 focus:py-2">
                Skip to content
            </a>

            {/* Desktop rail */}
            <aside className="sticky top-0 hidden h-screen flex-col gap-6 border-r border-rule bg-paper px-3 py-5 md:flex">
                <Link to="/" className="px-2" aria-label="PAL Market home">
                    <Logo />
                </Link>
                <nav aria-label="Workspace" className="flex-1 overflow-y-auto">
                    <NavItems items={items} />
                </nav>
                <div className="px-2">{accountBlock}</div>
            </aside>

            {/* Mobile bar + drawer */}
            <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-rule bg-paper px-4 md:hidden">
                <Link to="/" aria-label="PAL Market home">
                    <Logo />
                </Link>
                <button
                    type="button"
                    className="rounded-[var(--radius-control)] border border-rule px-3 py-1.5 text-sm font-semibold"
                    aria-expanded={menuOpen}
                    aria-controls="workspace-menu"
                    onClick={() => setMenuOpen((open) => !open)}
                >
                    {menuOpen ? 'Close' : 'Menu'}
                </button>
            </header>
            {menuOpen && (
                <div id="workspace-menu" className="fixed inset-x-0 top-14 bottom-0 z-20 overflow-y-auto bg-paper px-3 py-4 md:hidden">
                    <nav aria-label="Workspace">
                        <NavItems items={items} onNavigate={() => setMenuOpen(false)} />
                    </nav>
                    <div className="mt-6 px-2">{accountBlock}</div>
                </div>
            )}

            <main id="main" className="min-w-0 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                <div className="mx-auto max-w-6xl">
                    <AccountStatusBanner user={user} />
                    <Outlet />
                </div>
            </main>
        </div>
    );
}

function AccountStatusBanner({ user }) {
    if (user?.supplier?.status === 'pending') {
        return (
            <Notice tone="attention" title="Your supplier account is awaiting approval" className="mb-6">
                You can browse open requests now. Once an administrator approves your business, add your locations, products and
                prices, then start sending quotations.
            </Notice>
        );
    }
    if (user?.supplier?.status === 'suspended' || user?.buyer_profile?.status === 'suspended') {
        return (
            <Notice tone="bad" title="Trading is paused on this business profile" className="mb-6">
                You can view your history, but cannot create or change requests, quotations or orders. Contact PAL Market support to
                resolve this.
            </Notice>
        );
    }
    return null;
}
