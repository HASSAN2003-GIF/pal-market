import { useId, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useT } from '../i18n';
import { api } from '../lib/api';
import { useDebounced } from '../hooks/useDebounced';
import { cx } from './ui';

/**
 * Search the catalog and pick a product. Keyboard: arrows move, Enter picks,
 * Escape closes. Products in `excludeIds` are shown as already added.
 */
export default function ProductPicker({ onPick, excludeIds = [], label, placeholder }) {
    const t = useT();
    const id = useId();
    const [text, setText] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const search = useDebounced(text.trim(), 250);

    const results = useQuery({
        queryKey: ['products', { search, per_page: 8 }],
        queryFn: () => api.products({ search, per_page: 8 }),
        enabled: open,
    });
    const products = results.data?.data ?? [];

    function pick(product) {
        if (!product || excludeIds.includes(product.id)) return;
        onPick(product);
        setText('');
        setOpen(false);
    }

    function onKeyDown(event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(i + 1, products.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter' && open && products[active]) {
            event.preventDefault();
            pick(products[active]);
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
                aria-activedescendant={open && products[active] ? `${id}-opt-${products[active].id}` : undefined}
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
                    className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-[var(--radius-control)] border border-rule bg-paper shadow-lg"
                >
                    {results.isPending && <li className="px-3 py-2.5 text-sm text-muted">{t('buyer.pickerSearching')}</li>}
                    {!results.isPending && products.length === 0 && <li className="px-3 py-2.5 text-sm text-muted">{t('buyer.pickerNone')}</li>}
                    {products.map((product, index) => {
                        const added = excludeIds.includes(product.id);
                        return (
                            <li
                                key={product.id}
                                id={`${id}-opt-${product.id}`}
                                role="option"
                                aria-selected={index === active}
                                aria-disabled={added}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => pick(product)}
                                onMouseEnter={() => setActive(index)}
                                className={cx(
                                    'flex cursor-pointer items-center justify-between gap-3 px-3 py-2.5',
                                    index === active && 'bg-pal-50',
                                    added && 'cursor-default opacity-55',
                                )}
                            >
                                <span>
                                    <span className="block font-semibold">{product.name}</span>
                                    <span className="block text-xs text-muted">
                                        {product.category?.name} · {t('common.perUnit', { unit: product.unit })}
                                    </span>
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
