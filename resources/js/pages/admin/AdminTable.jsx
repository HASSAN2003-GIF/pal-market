import { EmptyState } from '../../components/ui';
import { useT } from '../../i18n';

/**
 * Admin data in two layouts chosen by available width, not by device:
 * - from lg up (the table needs about 700px), a real <table> with every column;
 * - below lg, one card per row: the first column as the card title, the
 *   rest as label/value pairs, and the "actions" column as full-width buttons.
 * columns: [{ key, label, render(row), align }]
 */
export default function AdminTable({ columns, rows, rowKey = 'id', empty }) {
    const t = useT();
    if (rows.length === 0) return <EmptyState title={t('admin.noResults')}>{empty ?? t('admin.noResultsBody')}</EmptyState>;

    const value = (column, row) => (column.render ? column.render(row) : row[column.key]);
    const [primary, ...rest] = columns;
    const details = rest.filter((column) => column.key !== 'actions');
    const actions = rest.find((column) => column.key === 'actions');

    return (
        <>
            <ul className="space-y-3 lg:hidden">
                {rows.map((row) => (
                    <li key={row[rowKey]} className="min-w-0 rounded-[var(--radius-panel)] border border-rule bg-paper px-4 py-3 text-sm">
                        <div className="min-w-0 break-words">{value(primary, row)}</div>
                        {details.length > 0 && (
                            <dl className="mt-2 grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 gap-y-1.5 border-t border-rule pt-2">
                                {details.map((column) => (
                                    <div key={column.key} className="contents">
                                        <dt className="text-muted">{column.label}</dt>
                                        <dd className="min-w-0 text-right break-words">{value(column, row)}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                        {actions && <div className="mt-3 flex flex-wrap justify-end gap-2 border-t border-rule pt-3 [&_.inline-flex]:flex-wrap">{value(actions, row)}</div>}
                    </li>
                ))}
            </ul>

            <div className="hidden overflow-x-auto rounded-[var(--radius-panel)] border border-rule bg-paper lg:block">
                <table className="w-full text-sm">
                    <thead className="border-b border-rule bg-concrete text-left text-muted">
                        <tr>
                            {columns.map((column) => (
                                <th key={column.key} scope="col" className={`px-4 py-2.5 font-semibold whitespace-nowrap ${column.align === 'right' ? 'text-right' : ''}`}>
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-rule">
                        {rows.map((row) => (
                            <tr key={row[rowKey]}>
                                {columns.map((column) => (
                                    <td key={column.key} className={`px-4 py-3 align-middle break-words ${column.align === 'right' ? 'text-right' : ''}`}>
                                        {value(column, row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
