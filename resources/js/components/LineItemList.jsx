import { useT } from '../i18n';
import { formatMoney } from '../lib/format';

/**
 * Narrow-screen version of a price table: one block per item with the
 * amount × price on one line and the total on the right, so nothing needs
 * sideways scrolling. Pair with the table, shown from `sm` up.
 * rows: [{ key, name, quantity, unit, unitPrice, total, note, missing }]
 */
export default function LineItemList({ rows, currency = 'TZS', className = '' }) {
    const t = useT();
    return (
        <ul className={`divide-y divide-rule ${className}`}>
            {rows.map((row) => (
                <li key={row.key} className="px-4 py-3 text-sm">
                    <p className="font-semibold">{row.name}</p>
                    {row.missing ? (
                        <p className="mt-0.5 text-muted">{t('buyer.notPriced')}</p>
                    ) : (
                        <div className="mt-0.5 flex flex-wrap items-baseline justify-between gap-x-3">
                            <span className="tabular text-muted">
                                {row.quantity} {row.unit} × {formatMoney(row.unitPrice, currency)}
                            </span>
                            <span className="tabular font-bold">{formatMoney(row.total, currency)}</span>
                        </div>
                    )}
                    {row.note && <p className="mt-0.5 text-xs text-warn">{row.note}</p>}
                </li>
            ))}
        </ul>
    );
}
