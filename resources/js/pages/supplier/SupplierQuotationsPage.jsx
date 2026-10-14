import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { DocNumber, EmptyState, Money, PageHeader, Pagination, QueryBoundary, StatusBadge } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { StatusTabs } from '../buyer/BuyerRequestsPage';

export default function SupplierQuotationsPage() {
    const t = useT();
    const [params, update] = useListParams();
    const query = { status: params.status, page: params.page };
    const quotations = useQuery({ queryKey: ['quotations', query], queryFn: () => api.quotations(query), placeholderData: keepPreviousData });

    return (
        <>
            <PageHeader title={t('nav.myOffers')} description={t('supplier.offersDescription')} />
            <div className="mb-4">
                <StatusTabs
                    kind="quotation"
                    value={params.status}
                    onChange={(status) => update({ status })}
                    include={['submitted', 'accepted', 'rejected', 'expired', 'cancelled']}
                />
            </div>
            <QueryBoundary query={quotations}>
                {({ data: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title={t('supplier.offersEmptyTitle')}>{t('supplier.offersEmptyBody')}</EmptyState>
                    ) : (
                        <>
                            <ul className="divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                                {rows.map((quotation) => (
                                    <li key={quotation.id}>
                                        <Link
                                            to={quotation.purchase_order ? `/orders/${quotation.purchase_order.id}` : `/supplier/market/${quotation.buyer_request_id}`}
                                            className="grid gap-2 px-4 py-3.5 hover:bg-pal-50 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:gap-6 sm:px-5"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold">{quotation.buyer_request?.title}</span>
                                                <span className="flex flex-wrap gap-x-3 text-sm text-muted">
                                                    <DocNumber className="text-sm">{quotation.quotation_number}</DocNumber>
                                                    <span>{quotation.buyer_request?.buyer_profile?.business_name}</span>
                                                    <span>{formatDate(quotation.submitted_at ?? quotation.created_at)}</span>
                                                    {quotation.purchase_order && (
                                                        <span className="font-semibold text-ok">{t('supplier.orderNumber', { number: quotation.purchase_order.order_number })}</span>
                                                    )}
                                                </span>
                                            </span>
                                            <Money amount={quotation.total_amount} currency={quotation.currency} className="font-semibold sm:text-right" />
                                            <StatusBadge kind="quotation" status={quotation.status} />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <Pagination meta={meta} onPage={(page) => update({ page })} />
                        </>
                    )
                }
            </QueryBoundary>
        </>
    );
}
