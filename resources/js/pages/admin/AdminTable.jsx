import { EmptyState } from '../../components/ui';

/**
 * Responsive data table: a real <table> from md up, stacked rows below.
 * columns: [{ key, label, render(row), align }]
 */
export default function AdminTable({ columns, rows, rowKey = 'id', empty = 'Nothing matches these filters.' }) {
    if (rows.length === 0) return <EmptyState title="No results">{empty}</EmptyState>;

    return (
        <div className="overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
            <table className="w-full text-sm">
                <thead className="hidden border-b border-rule bg-concrete text-left text-muted md:table-header-group">
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} scope="col" className={`px-4 py-2.5 font-semibold ${column.align === 'right' ? 'text-right' : ''}`}>
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-rule">
                    {rows.map((row) => (
                        <tr key={row[rowKey]} className="block px-4 py-3 md:table-row md:p-0">
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={`flex justify-between gap-4 py-0.5 md:table-cell md:px-4 md:py-3 md:align-middle ${column.align === 'right' ? 'md:text-right' : ''}`}
                                >
                                    <span className="text-muted md:hidden">{column.label}</span>
                                    <span className="text-right md:text-[inherit]">{column.render ? column.render(row) : row[column.key]}</span>
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
