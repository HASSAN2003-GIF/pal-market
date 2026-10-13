import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ButtonLink } from '../components/ui';
import { homePathFor, useAuth } from '../context/AuthContext';
import { api } from '../lib/api';
import { formatMoney } from '../lib/format';

// Illustrative figures for the example docket. Not live data, and labelled so.
const EXAMPLE_ITEMS = [
    ['Cement 42.5R', '120 bags'],
    ['Twisted rebar 12mm', '80 lengths'],
    ['Roofing sheets, gauge 28', '60 sheets'],
];
const EXAMPLE_BIDS = [
    ['Supplier A', 6140000, 'Delivers in 2 days'],
    ['Supplier B', 5895000, 'Delivers in 3 days'],
    ['Supplier C', 6320000, 'Delivers tomorrow'],
];

function ExampleDocket() {
    const lowest = Math.min(...EXAMPLE_BIDS.map(([, total]) => total));

    return (
        <figure className="relative overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper shadow-[0_18px_40px_-24px_rgba(26,36,48,0.45)]">
            <div className="tape-edge h-2" aria-hidden="true" />
            <div className="flex items-baseline justify-between border-b border-rule px-5 py-3">
                <span className="doc-number text-lg text-ink">REQ-EXAMPLE</span>
                <span className="text-sm text-muted">Open for quotes</span>
            </div>
            <div className="px-5 py-4">
                <p className="font-display text-xl font-semibold">Ground floor slab, Kibamba</p>
                <ul className="mt-3 divide-y divide-rule border-y border-rule text-sm">
                    {EXAMPLE_ITEMS.map(([item, qty]) => (
                        <li key={item} className="flex justify-between gap-4 py-2">
                            <span>{item}</span>
                            <span className="tabular text-muted">{qty}</span>
                        </li>
                    ))}
                </ul>
                <p className="mt-4 text-sm font-semibold">Quotations received</p>
                <ul className="mt-2 space-y-1.5">
                    {EXAMPLE_BIDS.map(([name, total, note]) => {
                        const isLowest = total === lowest;
                        return (
                            <li
                                key={name}
                                className={`flex items-center justify-between gap-3 rounded-[var(--radius-control)] px-3 py-2 ${
                                    isLowest ? 'bg-safety-soft ring-2 ring-safety' : 'bg-concrete'
                                }`}
                            >
                                <span>
                                    <span className="font-semibold">{name}</span>
                                    <span className="block text-xs text-muted">{note}</span>
                                </span>
                                <span className="text-right">
                                    <span className="tabular font-semibold">{formatMoney(total)}</span>
                                    {isLowest && <span className="block text-xs font-semibold text-warn">Lowest total</span>}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            </div>
            <figcaption className="border-t border-rule bg-concrete px-5 py-2 text-xs text-muted">
                Example only. Real requests show each supplier's line prices, delivery fee and validity.
            </figcaption>
        </figure>
    );
}

const STEPS = [
    ['Post your materials list', 'Add products from the catalog with quantities, delivery region and the date you need them.'],
    ['Approved suppliers quote', 'Suppliers price each line and add delivery. You see every quotation side by side.'],
    ['Accept and raise the order', 'Accepting creates a purchase order. Track it until the materials are on site.'],
];

export default function HomePage() {
    const { user } = useAuth();
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });
    const categories = (filters.data?.categories ?? []).filter((c) => c.products_count > 0);

    return (
        <>
            <section className="border-b border-rule">
                <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
                    <div>
                        <h1 className="text-[3rem] leading-[0.95] sm:text-[4.25rem] lg:text-[5rem]">
                            One materials list.
                            <br />
                            Every supplier's price.
                        </h1>
                        <p className="mt-6 max-w-xl text-lg text-ink-soft">
                            Post what your site needs once. Approved hardware suppliers across Tanzania send quotations, and you choose
                            the one that works on price, delivery and timing.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            {user ? (
                                <ButtonLink to={homePathFor(user)} size="lg">
                                    Open your workspace
                                </ButtonLink>
                            ) : (
                                <ButtonLink to="/register" size="lg">
                                    Post a materials list
                                </ButtonLink>
                            )}
                            <ButtonLink to="/materials" size="lg" variant="secondary">
                                Browse materials
                            </ButtonLink>
                        </div>
                    </div>
                    <ExampleDocket />
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                <h2 className="text-[2.2rem]">How buying works</h2>
                <ol className="mt-6 grid gap-6 md:grid-cols-3">
                    {STEPS.map(([title, body], index) => (
                        <li key={title} className="border-t-2 border-ink pt-4">
                            <span className="font-display text-[2.5rem] leading-none font-semibold text-pal-600">{index + 1}</span>
                            <h3 className="mt-2 text-[1.5rem]">{title}</h3>
                            <p className="mt-1.5 text-ink-soft">{body}</p>
                        </li>
                    ))}
                </ol>
            </section>

            {categories.length > 0 && (
                <section className="border-y border-rule bg-paper">
                    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2 className="text-[2.2rem]">Materials in the catalog</h2>
                            <Link to="/materials" className="font-semibold text-pal-700 hover:underline">
                                See all materials
                            </Link>
                        </div>
                        <ul className="mt-6 grid gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                            {categories.map((category) => (
                                <li key={category.id} className="border-b border-rule">
                                    <Link
                                        to={`/materials?category_id=${category.id}`}
                                        className="flex items-center justify-between py-3 font-semibold hover:text-pal-700"
                                    >
                                        {category.name}
                                        <span className="text-sm font-normal text-muted">
                                            {category.products_count} {category.products_count === 1 ? 'product' : 'products'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
            )}

            <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                <div className="grid gap-6 rounded-[var(--radius-panel)] bg-ink px-6 py-8 text-white sm:px-10 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <h2 className="text-[2rem] text-white">Supply construction materials?</h2>
                        <p className="mt-2 max-w-xl text-white/75">
                            Register your business, list the products you stock with prices and locations, and quote on buyers' open
                            requests once we approve your account.
                        </p>
                    </div>
                    <ButtonLink to="/register?role=supplier" variant="secondary" size="lg">
                        Register as a supplier
                    </ButtonLink>
                </div>
            </section>
        </>
    );
}
