import { useEffect, useId, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { useDebounced } from '../hooks/useDebounced';
import { useI18n } from '../i18n';
import { api } from '../lib/api';
import { productName } from '../lib/products';
import { SearchIcon } from './icons';
import ProductThumb from './ProductThumb';
import { cx } from './ui';

/**
 * "What do you need?" search with an optional category, like the scoped
 * search boxes on large marketplaces. Suggests products while typing
 * (names, Kiswahili names, aliases, small typos) and searches the real
 * catalog on /materials.
 */
export default function HeaderSearch({ className = '' }) {
    const { t, locale } = useI18n();
    const navigate = useNavigate();
    const location = useLocation();
    const listId = useId();
    const [searchParams] = useSearchParams();
    const onMaterials = location.pathname === '/materials';
    const [text, setText] = useState(onMaterials ? (searchParams.get('search') ?? '') : '');
    const [category, setCategory] = useState(onMaterials ? (searchParams.get('category_id') ?? '') : '');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 5 * 60_000 });
    const term = useDebounced(text.trim(), 200);

    const suggestions = useQuery({
        queryKey: ['product-suggest', term],
        queryFn: ({ signal }) => api.suggestProducts(term, { limit: 6, signal }),
        enabled: open && term.length >= 2,
        placeholderData: keepPreviousData,
        staleTime: 30_000,
    });
    const items = term.length >= 2 && suggestions.data?.mode !== 'none' ? (suggestions.data?.data ?? []) : [];
    const showList = open && term.length >= 2;

    // Follow the URL when filters change on the materials page.
    useEffect(() => {
        if (onMaterials) {
            setText(searchParams.get('search') ?? '');
            setCategory(searchParams.get('category_id') ?? '');
        }
    }, [onMaterials, searchParams]);

    // Close the list after navigating.
    useEffect(() => {
        setOpen(false);
        setActive(-1);
    }, [location.pathname, location.search]);

    const submit = (event) => {
        event.preventDefault();
        if (active >= 0 && items[active]) {
            navigate(`/materials/${items[active].id}`);
            return;
        }
        const next = new URLSearchParams(onMaterials ? searchParams : undefined);
        next.delete('page');
        if (text.trim()) next.set('search', text.trim());
        else next.delete('search');
        if (category) next.set('category_id', category);
        else next.delete('category_id');
        setOpen(false);
        navigate(`/materials${next.toString() ? `?${next}` : ''}`);
    };

    function onKeyDown(event) {
        if (event.key === 'ArrowDown' && items.length) {
            event.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(i + 1, items.length - 1));
        } else if (event.key === 'ArrowUp' && items.length) {
            event.preventDefault();
            setActive((i) => Math.max(i - 1, -1));
        } else if (event.key === 'Escape') {
            setOpen(false);
            setActive(-1);
        }
    }

    return (
        <form role="search" onSubmit={submit} className={cx('relative', className)}>
            <div className="flex h-11 min-w-0 overflow-hidden rounded-[var(--radius-control)] border-2 border-ink bg-paper focus-within:border-pal-600">
                <label className="hidden shrink-0 border-r border-rule sm:block">
                    <span className="sr-only">{t('search.category')}</span>
                    <select
                        value={category}
                        onChange={(event) => setCategory(event.target.value)}
                        className="h-full max-w-[11rem] bg-concrete px-2.5 text-sm font-semibold text-ink-soft focus:outline-none"
                    >
                        <option value="">{t('search.allCategories')}</option>
                        {(filters.data?.categories ?? []).map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="min-w-0 flex-1">
                    <span className="sr-only">{t('search.label')}</span>
                    <input
                        type="search"
                        role="combobox"
                        aria-expanded={showList}
                        aria-controls={listId}
                        aria-autocomplete="list"
                        aria-activedescendant={active >= 0 && items[active] ? `${listId}-${items[active].id}` : undefined}
                        autoComplete="off"
                        value={text}
                        onChange={(event) => {
                            setText(event.target.value);
                            setOpen(true);
                            setActive(-1);
                        }}
                        onFocus={() => setOpen(true)}
                        onBlur={() => setTimeout(() => setOpen(false), 150)}
                        onKeyDown={onKeyDown}
                        placeholder={t('search.placeholder')}
                        className="h-full w-full min-w-0 bg-transparent px-3 text-[1rem] placeholder:text-faint focus:outline-none"
                    />
                </label>
                <button type="submit" className="inline-flex shrink-0 items-center gap-2 bg-action px-4 font-bold text-on-action hover:bg-action-hover">
                    <SearchIcon />
                    <span className="hidden md:inline">{t('search.submit')}</span>
                    <span className="sr-only md:hidden">{t('search.submit')}</span>
                </button>
            </div>

            {showList && (
                <div className="absolute inset-x-0 top-full z-40 mt-1 overflow-hidden rounded-[var(--radius-control)] border border-rule bg-paper shadow-lg">
                    <ul id={listId} role="listbox" aria-label={t('discover.suggestions')} className="max-h-[60vh] overflow-y-auto">
                        {items.length === 0 && !suggestions.isFetching && <li className="px-3 py-2.5 text-sm text-muted">{t('discover.noSuggestions')}</li>}
                        {items.map((item, index) => (
                            <li
                                key={item.id}
                                id={`${listId}-${item.id}`}
                                role="option"
                                aria-selected={index === active}
                                onMouseDown={(e) => e.preventDefault()}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => navigate(`/materials/${item.id}`)}
                                className={cx('flex cursor-pointer items-center gap-3 px-3 py-2', index === active && 'bg-pal-50')}
                            >
                                <ProductThumb src={item.image} size="xs" />
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold">{productName(item, locale)}</span>
                                    <span className="block truncate text-xs text-muted">
                                        {item.matched ? t('discover.alsoCalled', { name: item.matched }) : item.category?.name}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                    {text.trim() && (
                        <button
                            type="submit"
                            onMouseDown={(e) => {
                                e.preventDefault();
                                setActive(-1);
                            }}
                            className="block w-full border-t border-rule px-3 py-2.5 text-left text-sm font-semibold text-pal-700 hover:bg-pal-50"
                        >
                            {t('discover.seeAll', { q: text.trim() })}
                        </button>
                    )}
                </div>
            )}
        </form>
    );
}
