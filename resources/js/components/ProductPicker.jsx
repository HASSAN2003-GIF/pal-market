import { useId, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useI18n } from '../i18n';
import { api } from '../lib/api';
import { productName, secondaryName } from '../lib/products';
import { useDebounced } from '../hooks/useDebounced';
import ProductThumb from './ProductThumb';
import { cx } from './ui';

/**
 * Search the catalog and pick a product. Matches partial names, Kiswahili
 * names, aliases and small spelling mistakes (server side). Keyboard: arrows
 * move, Enter picks, Escape closes. Products in `excludeIds` are shown as
 * already added. With `onPropose`, the last option asks to add a product
 * that is not in the catalog.
 */
export default function ProductPicker({ onPick, onPropose, excludeIds = [], label, placeholder }) {
    const { t, locale } = useI18n();
    const id = useId();
    const [text, setText] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const search = useDebounced(text.trim(), 200);

    const results = useQuery({
        queryKey: ['product-suggest', search],
        queryFn: ({ signal }) => (search ? api.suggestProducts(search, { limit: 8, signal }) : api.products({ per_page: 8, sort: 'name' })),
        enabled: open,
        placeholderData: keepPreviousData,
        staleTime: 30_000,
    });
    const products = results.data?.data ?? [];
    const related = results.data?.mode === 'related';
    const proposeOption = onPropose && search.length >= 3 ? { propose: true, id: 'propose' } : null;
    const options = proposeOption ? [...products, proposeOption] : products;

    function choose(option) {
        if (!option) return;
        if (option.propose) {
            onPropose(text.trim());
            setOpen(false);
            return;
        }
        if (excludeIds.includes(option.id)) return;
        onPick(option);
        setText('');
        setOpen(false);
    }

    function onKeyDown(event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(i + 1, options.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter' && open && options[active]) {
            event.preventDefault();
            choose(options[active]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    }

    return (
        <div className="relative">
            <label htmlFor={id} className="mb-1.5 block text-sm font-semibold">
                {label ?? t('buyer.pickerLabel')}
            </label>
            <input
                id={id}
                type="search"
                role="combobox"
                aria-expanded={open}
                aria-controls={`${id}-list`}
                aria-activedescendant={open && options[active] ? `${id}-opt-${options[active].id}` : undefined}
                aria-autocomplete="list"
                autoComplete="off"
                className="field-input"
                placeholder={placeholder ?? t('buyer.pickerPlaceholder')}
                value={text}
                onChange={(e) => {
                    setText(e.target.value);
                    setOpen(true);
                    setActive(0);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                onKeyDown={onKeyDown}
            />
            {open && (
                <ul
                    id={`${id}-list`}
                    role="listbox"
                    className="absolute z-20 mt-1 max-h-80 w-full overflow-y-auto rounded-[var(--radius-control)] border border-rule bg-paper shadow-lg"
                >
                    {results.isPending && <li className="px-3 py-2.5 text-sm text-muted">{t('buyer.pickerSearching')}</li>}
                    {!results.isPending && products.length === 0 && <li className="px-3 py-2.5 text-sm text-muted">{t('buyer.pickerNone')}</li>}
                    {related && products.length > 0 && (
                        <li className="border-b border-rule bg-concrete px-3 py-1.5 text-xs text-muted" role="presentation">
                            {t('discover.relatedTitle', { q: search })}
                        </li>
                    )}
                    {options.map((option, index) => {
                        if (option.propose) {
                            return (
                                <li
                                    key="propose"
                                    id={`${id}-opt-propose`}
                                    role="option"
                                    aria-selected={index === active}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => choose(option)}
                                    onMouseEnter={() => setActive(index)}
                                    className={cx('cursor-pointer border-t border-rule px-3 py-2.5 font-semibold text-pal-700', index === active && 'bg-pal-50')}
                                >
                                    {t('proposals.notListed', { name: text.trim() })}
                                </li>
                            );
                        }
                        const added = excludeIds.includes(option.id);
                        const other = secondaryName(option, locale);
                        return (
                            <li
                                key={option.id}
                                id={`${id}-opt-${option.id}`}
                                role="option"
                                aria-selected={index === active}
                                aria-disabled={added}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => choose(option)}
                                onMouseEnter={() => setActive(index)}
                                className={cx(
                                    'flex cursor-pointer items-center gap-3 px-3 py-2',
                                    index === active && 'bg-pal-50',
                                    added && 'cursor-default opacity-55',
                                )}
                            >
                                <ProductThumb src={option.image?.thumb ?? option.image} size="xs" />
                                <span className="min-w-0 flex-1">
                                    <span className="block font-semibold">{productName(option, locale)}</span>
                                    <span className="block text-xs text-muted">
                                        {other && `${other} · `}
                                        {option.category?.name} · {t('common.perUnit', { unit: option.unit })}
                                    </span>
                                    {option.matched && <span className="block text-xs text-pal-700">{t('discover.alsoCalled', { name: option.matched })}</span>}
                                </span>
                                {added && <span className="text-xs font-semibold text-muted">{t('buyer.pickerAdded')}</span>}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
