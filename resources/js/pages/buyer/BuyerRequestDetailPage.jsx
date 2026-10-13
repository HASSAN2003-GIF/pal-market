import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useParams } from 'react-router-dom';
import ProductPicker from '../../components/ProductPicker';
import { Button, ConfirmDialog, DocNumber, FormError, Notice, PageHeader, Panel, QueryBoundary, StatusBadge, useToast } from '../../components/ui';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, formatNumber, pluralize, relativeTime } from '../../lib/format';
import { statusInfo } from '../../lib/statuses';
import { regionLabel } from '../../location/regions';
import QuoteComparison from './QuoteComparison';
import RequestDetailsFields, { detailsPayload } from './RequestDetailsFields';

export default function BuyerRequestDetailPage() {
    const { requestId } = useParams();
    const request = useQuery({ queryKey: ['buyer-request', requestId], queryFn: () => api.buyerRequest(requestId) });

    return <QueryBoundary query={request}>{({ buyer_request: data }) => <RequestBody request={data} />}</QueryBoundary>;
}

function useRequestMutation(request, fn, { success, onSuccess } = {}) {
    const queryClient = useQueryClient();
    const toast = useToast();
    return useMutation({
        mutationFn: fn,
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['buyer-request', String(request.id)] });
            queryClient.invalidateQueries({ queryKey: ['buyer-requests'] });
            queryClient.invalidateQueries({ queryKey: ['summary'] });
            if (success) toast(success);
            onSuccess?.(data);
        },
    });
}

function RequestBody({ request }) {
    const { t } = useI18n();
    const navigate = useNavigate();
    const [dialog, setDialog] = useState(null);
    const [openForDays, setOpenForDays] = useState('14');
    const isDraft = request.status === 'draft';

    const publish = useRequestMutation(request, () => api.publishBuyerRequest(request.id, Number(openForDays)), {
        success: t('buyer.sentToast'),
        onSuccess: () => setDialog(null),
    });
    const cancel = useRequestMutation(request, () => api.cancelBuyerRequest(request.id), {
        success: t('buyer.cancelledToast'),
        onSuccess: () => setDialog(null),
    });
    const remove = useRequestMutation(request, () => api.deleteBuyerRequest(request.id), {
        success: t('buyer.deletedToast'),
        onSuccess: () => navigate('/buyer/requests', { replace: true }),
    });

    const info = statusInfo('request', request.status);

    return (
        <>
            <PageHeader
                back={{ to: '/buyer/requests', label: t('nav.myRequests') }}
                title={request.title}
                actions={
                    isDraft ? (
                        <>
                            <Button variant="danger" onClick={() => setDialog('delete')}>
                                {t('buyer.deleteDraft')}
                            </Button>
                            <Button onClick={() => setDialog('publish')} disabled={request.items.length === 0}>
                                {t('buyer.sendToSellers')}
                            </Button>
                        </>
                    ) : request.status === 'open' ? (
                        <Button variant="danger" onClick={() => setDialog('cancel')}>
                            {t('buyer.cancelRequest')}
                        </Button>
                    ) : null
                }
            />

            <div className="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                <DocNumber className="text-lg">{request.request_number}</DocNumber>
                <StatusBadge kind="request" status={request.status} />
                <span className="text-sm text-muted">{info.hint}</span>
            </div>

            {isDraft && (
                <Notice tone={request.items.length === 0 ? 'attention' : 'info'} title={request.items.length === 0 ? t('buyer.needItemTitle') : t('buyer.readyTitle')} className="mb-6">
                    {request.items.length === 0 ? t('buyer.needItemBody') : t('buyer.readyBody')}
                </Notice>
            )}
            {request.status === 'open' && (
                <Notice tone="info" className="mb-6" title={t('buyer.openUntil', { date: formatDate(request.expires_at), relative: relativeTime(request.expires_at) })}>
                    {t('buyer.openBody')}
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="min-w-0 space-y-6">
                    <ItemsPanel request={request} editable={isDraft} />
                    {!isDraft && (
                        <section aria-labelledby="quotes-heading">
                            <h2 id="quotes-heading" className="mb-3 text-[1.7rem]">
                                {t('buyer.offersTitle')}
                            </h2>
                            <QuoteComparison request={request} />
                        </section>
                    )}
                </div>
                <aside className="space-y-6">
                    <DetailsPanel request={request} editable={isDraft} />
                </aside>
            </div>

            <ConfirmDialog
                open={dialog === 'publish'}
                title={t('buyer.sendDialogTitle')}
                confirmLabel={t('buyer.sendToSellers')}
                busy={publish.isPending}
                error={publish.error?.message}
                onCancel={() => setDialog(null)}
                onConfirm={() => publish.mutate()}
            >
                <p>{t('buyer.sendDialogBody')}</p>
                <label className="mt-4 block">
                    <span className="mb-1.5 block text-sm font-semibold text-ink">{t('buyer.acceptOffersFor')}</span>
                    <select className="field-input" value={openForDays} onChange={(e) => setOpenForDays(e.target.value)}>
                        {[3, 7, 14, 21, 30].map((days) => (
                            <option key={days} value={days}>
                                {pluralize(days, 'common.days')}
                            </option>
                        ))}
                    </select>
                </label>
            </ConfirmDialog>
            <ConfirmDialog
                open={dialog === 'cancel'}
                title={t('buyer.cancelDialogTitle')}
                confirmLabel={t('buyer.cancelRequest')}
                confirmVariant="danger"
                busy={cancel.isPending}
                error={cancel.error?.message}
                onCancel={() => setDialog(null)}
                onConfirm={() => cancel.mutate()}
            >
                <p>{t('buyer.cancelDialogBody')}</p>
            </ConfirmDialog>
            <ConfirmDialog
                open={dialog === 'delete'}
                title={t('buyer.deleteDialogTitle')}
                confirmLabel={t('buyer.deleteDraft')}
                confirmVariant="danger"
                busy={remove.isPending}
                error={remove.error?.message}
                onCancel={() => setDialog(null)}
                onConfirm={() => remove.mutate()}
            >
                <p>{t('buyer.deleteDialogBody')}</p>
            </ConfirmDialog>
        </>
    );
}

function ItemsPanel({ request, editable }) {
    const { t } = useI18n();
    const add = useRequestMutation(request, (product) => api.addRequestItem(request.id, { product_id: product.id, quantity: 1 }), {
        success: t('buyer.itemAddedToast'),
    });

    return (
        <Panel title={t('buyer.panelMaterials')} status={<span className="text-sm text-muted">{pluralize(request.items.length, 'common.items')}</span>}>
            {editable && (
                <div className="mb-4">
                    <ProductPicker excludeIds={request.items.map((item) => item.product_id)} onPick={(product) => add.mutate(product)} />
                    <FormError error={add.error?.fieldErrors?.product_id ?? add.error?.message} />
                </div>
            )}
            {request.items.length === 0 ? (
                <p className="text-sm text-muted">{t('buyer.noItems')}</p>
            ) : (
                <ul className="divide-y divide-rule border-y border-rule">
                    {request.items.map((item) => (
                        <ItemRow key={item.id} request={request} item={item} editable={editable} />
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function ItemRow({ request, item, editable }) {
    const { t } = useI18n();
    const [quantity, setQuantity] = useState(String(item.quantity));
    const save = useRequestMutation(request, () => api.updateRequestItem(request.id, item.id, { quantity: Number(quantity), notes: item.notes }), {
        success: t('buyer.quantityToast'),
    });
    const remove = useRequestMutation(request, () => api.removeRequestItem(request.id, item.id), { success: t('buyer.itemRemovedToast') });
    const dirty = String(item.quantity) !== quantity;

    return (
        <li className="grid gap-2 py-3 sm:grid-cols-[1fr_auto] sm:items-center">
            <div>
                <p className="font-semibold">{item.product?.name}</p>
                {item.notes && <p className="text-sm text-muted">{item.notes}</p>}
                {(save.error || remove.error) && <p className="text-sm text-danger">{(save.error || remove.error).message}</p>}
            </div>
            {editable ? (
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        save.mutate();
                    }}
                >
                    <label className="flex items-center gap-2">
                        <span className="sr-only">{t('buyer.quantityOf', { name: item.product?.name })}</span>
                        <input
                            type="number"
                            min="1"
                            inputMode="numeric"
                            className="field-input tabular w-24 py-1.5"
                            value={quantity}
                            onChange={(e) => setQuantity(e.target.value)}
                        />
                        <span className="text-sm text-muted">{item.unit}</span>
                    </label>
                    {dirty && (
                        <Button type="submit" size="sm" loading={save.isPending}>
                            {t('common.save')}
                        </Button>
                    )}
                    <Button size="sm" variant="ghost" className="text-danger" onClick={() => remove.mutate()} loading={remove.isPending}>
                        {t('common.remove')}
                    </Button>
                </form>
            ) : (
                <p className="tabular font-semibold sm:text-right">
                    {formatNumber(item.quantity)} {item.unit}
                </p>
            )}
        </li>
    );
}

function DetailsPanel({ request, editable }) {
    const { t, locale } = useI18n();
    const [editing, setEditing] = useState(false);
    const [form, setForm] = useState(request);
    const save = useRequestMutation(request, () => api.updateBuyerRequest(request.id, detailsPayload(form)), {
        success: t('buyer.detailsSavedToast'),
        onSuccess: () => setEditing(false),
    });

    if (editing) {
        return (
            <Panel title={t('buyer.panelDetails')}>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        save.mutate();
                    }}
                >
                    <RequestDetailsFields form={form} setForm={setForm} errors={save.error?.fieldErrors} />
                    <div className="flex gap-2">
                        <Button type="submit" loading={save.isPending}>
                            {t('common.saveChanges')}
                        </Button>
                        <Button variant="secondary" onClick={() => setEditing(false)}>
                            {t('common.cancel')}
                        </Button>
                    </div>
                </form>
            </Panel>
        );
    }

    const rows = [
        ['buyer.fieldRegion', request.delivery_region && regionLabel(request.delivery_region, locale)],
        ['buyer.fieldAddress', request.delivery_address],
        ['buyer.fieldNeededBy', request.needed_by && formatDate(request.needed_by)],
        ['buyer.sentOn', request.published_at && formatDate(request.published_at)],
        ['buyer.closes', request.expires_at && formatDate(request.expires_at)],
    ];

    return (
        <Panel
            title={t('buyer.panelDetails')}
            actions={
                editable && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setForm(request);
                            setEditing(true);
                        }}
                    >
                        {t('common.edit')}
                    </Button>
                )
            }
        >
            <dl className="space-y-2.5 text-sm">
                {rows.map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-muted">{t(label)}</dt>
                        <dd className="font-semibold">{value || <span className="font-normal text-faint">{t('buyer.notGiven')}</span>}</dd>
                    </div>
                ))}
            </dl>
            {request.description && <p className="mt-4 border-t border-rule pt-3 text-sm whitespace-pre-line text-ink-soft">{request.description}</p>}
        </Panel>
    );
}
