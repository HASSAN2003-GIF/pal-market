import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import {
    Button,
    ConfirmDialog,
    DocNumber,
    FormError,
    Money,
    Notice,
    PageHeader,
    Panel,
    QueryBoundary,
    StatusBadge,
    TextAreaField,
    TextField,
    cx,
    useToast,
} from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, formatMoney, formatNumber, relativeTime } from '../../lib/format';
import { regionLabel } from '../../location/regions';

export default function MarketRequestPage() {
    const { requestId } = useParams();
    const detail = useQuery({ queryKey: ['market-request', requestId], queryFn: () => api.marketRequest(requestId) });

    return <QueryBoundary query={detail}>{(data) => <Body data={data} />}</QueryBoundary>;
}

function Body({ data }) {
    const { t, locale } = useI18n();
    const { user } = useAuth();
    const request = data.buyer_request;
    const notGiven = t('buyer.notGiven');

    return (
        <>
            <PageHeader back={{ to: '/supplier/market', label: t('nav.market') }} title={request.title} />
            <div className="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                <DocNumber className="text-lg">{request.request_number}</DocNumber>
                <span>
                    {t('nav.role.buyer')}: <span className="font-semibold text-ink">{request.buyer.business_name}</span>
                </span>
                {request.is_open_for_quotes ? (
                    <span>{t('supplier.closesOnRelative', { date: formatDate(request.expires_at), relative: relativeTime(request.expires_at) })}</span>
                ) : (
                    <span className="font-semibold">{t('supplier.closedForOffers')}</span>
                )}
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="min-w-0 space-y-6">
                    {data.my_quotation ? (
                        <MyQuotation quotation={data.my_quotation} request={request} />
                    ) : data.can_quote ? (
                        <QuoteForm request={request} />
                    ) : (
                        <>
                            {user?.supplier?.status !== 'approved' && (
                                <Notice tone="attention" title={t('supplier.waitApprovalTitle')}>
                                    {t('supplier.waitApprovalBody')}
                                </Notice>
                            )}
                            <RequestedItems request={request} />
                        </>
                    )}
                </div>
                <aside>
                    <Panel title={t('orders.delivery')}>
                        <dl className="space-y-2.5 text-sm">
                            <div>
                                <dt className="text-muted">{t('buyer.fieldRegion')}</dt>
                                <dd className="font-semibold">{request.delivery_region ? regionLabel(request.delivery_region, locale) : notGiven}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">{t('buyer.fieldAddress')}</dt>
                                <dd className="font-semibold">{request.delivery_address || notGiven}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">{t('buyer.fieldNeededBy')}</dt>
                                <dd className="font-semibold">{request.needed_by ? formatDate(request.needed_by) : notGiven}</dd>
                            </div>
                        </dl>
                        {request.description && <p className="mt-4 border-t border-rule pt-3 text-sm whitespace-pre-line text-ink-soft">{request.description}</p>}
                    </Panel>
                </aside>
            </div>
        </>
    );
}

function RequestedItems({ request }) {
    const { t } = useI18n();
    const offered = new Set(request.offered_product_ids);
    return (
        <Panel title={t('supplier.requestedMaterials')} bodyClassName="p-0 sm:p-0">
            <ul className="divide-y divide-rule">
                {request.items.map((item) => (
                    <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5">
                        <span>
                            <span className="block font-semibold">{item.product?.name}</span>
                            {item.notes && <span className="block text-sm text-muted">{item.notes}</span>}
                        </span>
                        <span className="text-right">
                            <span className="tabular font-semibold">
                                {formatNumber(item.quantity)} {item.unit}
                            </span>
                            {!offered.has(item.product_id) && (
                                <Link to={`/supplier/catalog?add=${item.product_id}`} className="block text-sm font-semibold text-pal-700 hover:underline">
                                    {t('supplier.addToMyProducts')}
                                </Link>
                            )}
                        </span>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

function defaultValidUntil() {
    const date = new Date();
    date.setDate(date.getDate() + 7);
    return date.toISOString().slice(0, 10);
}

function QuoteForm({ request }) {
    const { t } = useI18n();
    const toast = useToast();
    const queryClient = useQueryClient();
    const offered = useMemo(() => new Set(request.offered_product_ids), [request.offered_product_ids]);
    const [lines, setLines] = useState(() =>
        Object.fromEntries(request.items.map((item) => [item.id, { include: offered.has(item.product_id), price: '', quantity: String(item.quantity) }])),
    );
    const [terms, setTerms] = useState({ delivery_fee: '0', valid_until: defaultValidUntil(), notes: '' });

    const included = request.items.filter((item) => lines[item.id].include);
    const subtotal = included.reduce((sum, item) => sum + Number(lines[item.id].price || 0) * Number(lines[item.id].quantity || 0), 0);
    const total = subtotal + Number(terms.delivery_fee || 0);

    const submit = useMutation({
        mutationFn: () =>
            api.createQuotation(request.id, {
                submit: true,
                delivery_fee: Number(terms.delivery_fee || 0),
                valid_until: terms.valid_until ? `${terms.valid_until}T23:59:00` : null,
                notes: terms.notes || null,
                items: included.map((item) => ({
                    product_id: item.product_id,
                    unit_price: Number(lines[item.id].price),
                    quantity: Number(lines[item.id].quantity),
                    unit: item.unit,
                })),
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['market-request', String(request.id)] });
            queryClient.invalidateQueries({ queryKey: ['market'] });
            queryClient.invalidateQueries({ queryKey: ['quotations'] });
            queryClient.invalidateQueries({ queryKey: ['summary'] });
            toast(t('supplier.offerSentToast'));
        },
    });

    const errors = submit.error?.fieldErrors ?? {};
    const lineError = (item, field) => {
        const index = included.indexOf(item);
        return index >= 0 ? errors[`items.${index}.${field}`] : null;
    };
    const update = (id, changes) => setLines((current) => ({ ...current, [id]: { ...current[id], ...changes } }));

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit.mutate();
            }}
            className="space-y-6"
        >
            <Notice tone="info" title={t('supplier.quoteHowTitle')}>
                {t('supplier.quoteHowBody')}
            </Notice>
            <Panel title={t('supplier.yourPrices')} bodyClassName="p-0 sm:p-0">
                <ul className="divide-y divide-rule">
                    {request.items.map((item) => {
                        const line = lines[item.id];
                        const inCatalog = offered.has(item.product_id);
                        return (
                            <li key={item.id} className={cx('grid gap-3 px-4 py-3.5 sm:grid-cols-[1fr_7rem_9rem] sm:items-end sm:px-5', !line.include && 'bg-concrete/50')}>
                                <div>
                                    <label className="flex items-start gap-2.5">
                                        <input
                                            type="checkbox"
                                            className="mt-1 h-4 w-4 accent-[var(--color-action)]"
                                            checked={line.include}
                                            disabled={!inCatalog}
                                            onChange={(e) => update(item.id, { include: e.target.checked })}
                                        />
                                        <span>
                                            <span className="block font-semibold">{item.product?.name}</span>
                                            <span className="block text-sm text-muted">
                                                {t('supplier.requestedAmount', { n: formatNumber(item.quantity), unit: item.unit })}
                                                {item.notes && `. ${item.notes}`}
                                            </span>
                                        </span>
                                    </label>
                                    {!inCatalog && (
                                        <p className="mt-1 ml-6.5 text-sm">
                                            {t('supplier.notInProducts')}{' '}
                                            <Link to={`/supplier/catalog?add=${item.product_id}`} className="font-semibold text-pal-700 hover:underline">
                                                {t('supplier.addItToPrice')}
                                            </Link>
                                        </p>
                                    )}
                                    {(lineError(item, 'product_id') || lineError(item, 'unit_price')) && (
                                        <p className="mt-1 ml-6.5 text-sm font-medium text-danger">{lineError(item, 'product_id') || lineError(item, 'unit_price')}</p>
                                    )}
                                </div>
                                {line.include && (
                                    <>
                                        <label>
                                            <span className="block text-xs text-muted">{t('buyer.colQuantity')}</span>
                                            <input
                                                type="number"
                                                min="1"
                                                required
                                                inputMode="numeric"
                                                className="field-input tabular"
                                                value={line.quantity}
                                                onChange={(e) => update(item.id, { quantity: e.target.value })}
                                            />
                                        </label>
                                        <label>
                                            <span className="block text-xs text-muted">{t('supplier.priceEachTzs')}</span>
                                            <input
                                                type="number"
                                                min="0"
                                                step="1"
                                                required
                                                inputMode="decimal"
                                                className="field-input tabular"
                                                value={line.price}
                                                onChange={(e) => update(item.id, { price: e.target.value })}
                                            />
                                        </label>
                                    </>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </Panel>

            <Panel title={t('supplier.terms')}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        label={t('supplier.deliveryFeeTzs')}
                        type="number"
                        min="0"
                        inputMode="decimal"
                        hint={t('supplier.deliveryFeeHint')}
                        value={terms.delivery_fee}
                        onChange={(e) => setTerms({ ...terms, delivery_fee: e.target.value })}
                        error={errors.delivery_fee}
                    />
                    <TextField
                        label={t('supplier.validUntil')}
                        type="date"
                        min={new Date().toISOString().slice(0, 10)}
                        value={terms.valid_until}
                        onChange={(e) => setTerms({ ...terms, valid_until: e.target.value })}
                        error={errors.valid_until}
                    />
                    <TextAreaField
                        className="sm:col-span-2"
                        label={t('supplier.notesForBuyer')}
                        optional
                        placeholder={t('supplier.notesForBuyerPlaceholder')}
                        value={terms.notes}
                        onChange={(e) => setTerms({ ...terms, notes: e.target.value })}
                        error={errors.notes}
                    />
                </div>
            </Panel>

            <div className="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-paper/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-[var(--radius-panel)] sm:border">
                <div>
                    <p className="text-sm text-muted">{t('supplier.offerTotal', { included: included.length, total: request.items.length })}</p>
                    <Money amount={total} className="text-[1.6rem] leading-none font-extrabold" />
                </div>
                <div className="flex flex-col items-end gap-1">
                    <FormError error={submit.error && !Object.keys(errors).some((k) => k.startsWith('items.') || k in terms) ? submit.error.message : null} />
                    <Button type="submit" size="lg" disabled={included.length === 0} loading={submit.isPending} loadingText={t('auth.sending')}>
                        {t('supplier.sendOffer')}
                    </Button>
                </div>
            </div>
        </form>
    );
}

function MyQuotation({ quotation, request }) {
    const { t } = useI18n();
    const toast = useToast();
    const queryClient = useQueryClient();
    const [confirming, setConfirming] = useState(false);
    const withdraw = useMutation({
        mutationFn: () => api.withdrawQuotation(quotation.id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['market-request', String(request.id)] });
            queryClient.invalidateQueries({ queryKey: ['quotations'] });
            setConfirming(false);
            toast(t('supplier.withdrawnToast'));
        },
    });
    const canWithdraw = ['draft', 'submitted'].includes(quotation.status) && request.is_open_for_quotes;

    return (
        <Panel
            title={t('supplier.yourOffer')}
            number={quotation.quotation_number}
            status={<StatusBadge kind="quotation" status={quotation.status} />}
            bodyClassName="p-0 sm:p-0"
        >
            <ul className="divide-y divide-rule">
                {quotation.items.map((line) => (
                    <li key={line.id} className="flex flex-wrap justify-between gap-2 px-4 py-2.5 text-sm sm:px-5">
                        <span>
                            {line.product?.name}
                            <span className="block text-muted">
                                {t('supplier.lineAt', { n: formatNumber(line.quantity), unit: line.unit, price: formatMoney(line.unit_price) })}
                            </span>
                        </span>
                        <Money amount={line.total_price} className="font-semibold" />
                    </li>
                ))}
                <li className="flex justify-between px-4 py-2.5 text-sm sm:px-5">
                    <span className="text-muted">{t('orders.delivery')}</span>
                    <Money amount={quotation.delivery_fee} />
                </li>
                <li className="flex items-center justify-between bg-concrete/60 px-4 py-3 sm:px-5">
                    <span className="font-semibold">{t('common.total')}</span>
                    <Money amount={quotation.total_amount} className="text-[1.4rem] font-extrabold" />
                </li>
            </ul>
            {(quotation.valid_until || canWithdraw) && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rule px-4 py-3 sm:px-5">
                    <span className="text-sm text-muted">{quotation.valid_until && t('buyer.validUntil', { date: formatDate(quotation.valid_until) })}</span>
                    {canWithdraw && (
                        <Button size="sm" variant="danger" onClick={() => setConfirming(true)}>
                            {t('supplier.withdraw')}
                        </Button>
                    )}
                </div>
            )}
            <ConfirmDialog
                open={confirming}
                title={t('supplier.withdrawTitle')}
                confirmLabel={t('supplier.withdraw')}
                confirmVariant="danger"
                busy={withdraw.isPending}
                error={withdraw.error?.message}
                onCancel={() => setConfirming(false)}
                onConfirm={() => withdraw.mutate()}
            >
                <p>{t('supplier.withdrawBody')}</p>
            </ConfirmDialog>
        </Panel>
    );
}
