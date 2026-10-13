import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { useT } from '../i18n';
import { api } from '../lib/api';
import { SearchIcon } from './icons';

/**
 * "What do you need?" search with an optional category, like the scoped
 * search boxes on large marketplaces. Searches the real catalog on /materials.
 */
export default function HeaderSearch({ className = '' }) {
    const t = useT();
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams] = useSearchParams();
    const onMaterials = location.pathname === '/materials';
    const [text, setText] = useState(onMaterials ? (searchParams.get('search') ?? '') : '');
    const [category, setCategory] = useState(onMaterials ? (searchParams.get('category_id') ?? '') : '');
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 5 * 60_000 });

    // Follow the URL when filters change on the materials page.
    useEffect(() => {
        if (onMaterials) {
            setText(searchParams.get('search') ?? '');
            setCategory(searchParams.get('category_id') ?? '');
        }
    }, [onMaterials, searchParams]);

    const submit = (event) => {
        event.preventDefault();
        const next = new URLSearchParams(onMaterials ? searchParams : undefined);
        next.delete('page');
        if (text.trim()) next.set('search', text.trim());
        else next.delete('search');
        if (category) next.set('category_id', category);
        else next.delete('category_id');
        navigate(`/materials${next.toString() ? `?${next}` : ''}`);
    };

    return (
        <form role="search" onSubmit={submit} className={`flex h-11 min-w-0 overflow-hidden rounded-[var(--radius-control)] border-2 border-ink bg-paper focus-within:border-pal-600 ${className}`}>
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
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    placeholder={t('search.placeholder')}
                    className="h-full w-full min-w-0 bg-transparent px-3 text-[1rem] placeholder:text-faint focus:outline-none"
                />
            </label>
            <button type="submit" className="inline-flex shrink-0 items-center gap-2 bg-action px-4 font-bold text-on-action hover:bg-action-hover">
                <SearchIcon />
                <span className="hidden md:inline">{t('search.submit')}</span>
                <span className="sr-only md:hidden">{t('search.submit')}</span>
            </button>
        </form>
    );
}
