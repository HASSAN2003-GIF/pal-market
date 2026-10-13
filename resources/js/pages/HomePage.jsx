import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { AreaButton } from '../components/PreferenceControls';
import { ButtonLink } from '../components/ui';
import { homePathFor, useAuth } from '../context/AuthContext';
import { useI18n } from '../i18n';
import { api } from '../lib/api';
import { formatMoney, pluralize } from '../lib/format';
import { useArea } from '../location/LocationContext';
import { regionLabel } from '../location/regions';

// Illustrative figures for the example request. Not live data, and labelled so.
const EXAMPLE_ITEMS = [
    ['home.exampleItem1', 'home.exampleQty1'],
    ['home.exampleItem2', 'home.exampleQty2'],
    ['home.exampleItem3', 'home.exampleQty3'],
];
const EXAMPLE_OFFERS = [
    ['A', 6140000, 'home.exampleNote1'],
    ['B', 5895000, 'home.exampleNote2'],
    ['C', 6320000, 'home.exampleNote3'],
];

function ExampleRequest() {
    const { t } = useI18n();
    const lowest = Math.min(...EXAMPLE_OFFERS.map(([, total]) => total));

    return (
        <figure className="relative overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper shadow-[0_18px_40px_-24px_rgba(0,0,0,0.45)]">
            <div className="tape-edge h-2" aria-hidden="true" />
            <div className="flex items-baseline justify-between gap-3 border-b border-rule px-5 py-3">
                <span className="doc-number text-lg text-ink">{t('home.exampleNumber')}</span>
                <span className="text-sm text-muted">{t('status.request.open')}</span>
            </div>
            <div className="px-5 py-4">
                <p className="text-lg font-extrabold">{t('home.exampleTitle')}</p>
                <ul className="mt-3 divide-y divide-rule border-y border-rule text-sm">
                    {EXAMPLE_ITEMS.map(([item, qty]) => (
                        <li key={item} className="flex justify-between gap-4 py-2">
                            <span>{t(item)}</span>
                            <span className="tabular text-muted">{t(qty)}</span>
                        </li>
                    ))}
                </ul>
                <p className="mt-4 text-sm font-semibold">{t('home.exampleOffers')}</p>
                <ul className="mt-2 space-y-1.5">
                    {EXAMPLE_OFFERS.map(([letter, total, note]) => {
                        const isLowest = total === lowest;
                        return (
                            <li
                                key={letter}
                                className={`flex items-center justify-between gap-3 rounded-[var(--radius-control)] px-3 py-2 ${
                                    isLowest ? 'bg-safety-soft ring-2 ring-safety' : 'bg-concrete'
                                }`}
                            >
                                <span>
                                    <span className="font-semibold">{t('home.exampleSeller', { letter })}</span>
                                    <span className="block text-xs text-muted">{t(note)}</span>
                                </span>
                                <span className="text-right">
                                    <span className="tabular font-semibold">{formatMoney(total)}</span>
                                    {isLowest && <span className="block text-xs font-semibold text-warn">{t('home.exampleLowest')}</span>}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            </div>
            <figcaption className="border-t border-rule bg-concrete px-5 py-2 text-xs text-muted">{t('home.exampleCaption')}</figcaption>
        </figure>
    );
}

const STEPS = [
    ['home.step1Title', 'home.step1Body'],
    ['home.step2Title', 'home.step2Body'],
    ['home.step3Title', 'home.step3Body'],
];

export default function HomePage() {
    const { t, locale } = useI18n();
    const { user } = useAuth();
    const { region } = useArea();
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });
    const categories = (filters.data?.categories ?? []).filter((c) => c.products_count > 0);
    const requestLink = user?.role === 'buyer' ? '/buyer/requests/new' : user ? homePathFor(user) : '/register?role=buyer';

    return (
        <>
            <section className="border-b border-rule">
                <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:py-16">
                    <div>
                        <h1 className="brand-dot text-[2.6rem] leading-[1.02] sm:text-[3.6rem] lg:text-[4.2rem]">{t('home.title')}</h1>
                        <p className="mt-5 max-w-xl text-lg text-ink-soft">{t('home.lead')}</p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <ButtonLink to={requestLink} size="lg">
                                {user && user.role !== 'buyer' ? t('layout.myWorkspace') : t('home.ctaRequest')}
                            </ButtonLink>
                            <ButtonLink to="/materials" size="lg" variant="secondary">
                                {t('home.ctaBrowse')}
                            </ButtonLink>
                        </div>
                        <div className="mt-6 inline-flex flex-wrap items-center gap-2 rounded-[var(--radius-control)] border border-rule bg-paper px-2 py-1.5 text-sm">
                            <AreaButton compact />
                            <span className="pr-2 text-muted">
                                {region ? t('home.areaSet', { region: regionLabel(region, locale) }) : t('location.setAreaHint')}
                            </span>
                        </div>
                    </div>
                    <ExampleRequest />
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6" aria-labelledby="how-it-works">
                <h2 id="how-it-works" className="text-[2rem]">
                    {t('home.howTitle')}
                </h2>
                <ol className="mt-6 grid gap-6 md:grid-cols-3">
                    {STEPS.map(([title, body], index) => (
                        <li key={title} className="rounded-[var(--radius-panel)] border border-rule bg-paper p-5">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-action text-lg font-extrabold text-on-action">
                                {index + 1}
                            </span>
                            <h3 className="mt-3 text-[1.3rem]">{t(title)}</h3>
                            <p className="mt-1.5 text-ink-soft">{t(body)}</p>
                        </li>
                    ))}
                </ol>
                <p className="mt-5 text-sm text-muted">
                    {t('home.noPayments')}{' '}
                    <Link to="/help" className="font-semibold text-pal-700 hover:underline">
                        {t('home.readHelp')}
                    </Link>
                </p>
            </section>

            {categories.length > 0 && (
                <section className="border-y border-rule bg-paper" aria-labelledby="categories">
                    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2 id="categories" className="text-[2rem]">
                                {t('home.categoriesTitle')}
                            </h2>
                            <Link to="/materials" className="font-semibold text-pal-700 hover:underline">
                                {t('home.seeAll')}
                            </Link>
                        </div>
                        <ul className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {categories.map((category) => (
                                <li key={category.id}>
                                    <Link
                                        to={`/materials?category_id=${category.id}`}
                                        className="flex items-center justify-between gap-3 rounded-[var(--radius-control)] border border-rule px-4 py-3.5 font-semibold hover:border-pal-500 hover:text-pal-700"
                                    >
                                        {category.name}
                                        <span className="text-sm font-normal text-muted">{pluralize(category.products_count, 'common.products')}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
            )}

            <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <div className="grid gap-6 rounded-[var(--radius-panel)] bg-inverse px-6 py-8 text-on-inverse sm:px-10 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <h2 className="text-[1.9rem] text-on-inverse">{t('home.sellTitle')}</h2>
                        <p className="mt-2 max-w-xl opacity-80">{t('home.sellBody')}</p>
                    </div>
                    <ButtonLink to={user ? homePathFor(user) : '/register?role=supplier'} variant="secondary" size="lg">
                        {user ? t('layout.myWorkspace') : t('home.sellCta')}
                    </ButtonLink>
                </div>
            </section>
        </>
    );
}
