import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ProductPicker from '../../components/ProductPicker';
import { Button, ButtonLink, FormError, PageHeader, Panel, useToast } from '../../components/ui';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { useArea } from '../../location/LocationContext';
import RequestDetailsFields, { detailsPayload } from './RequestDetailsFields';

export default function NewRequestPage() {
    const t = useT();
    const navigate = useNavigate();
    const toast = useToast();
    const queryClient = useQueryClient();
    const [searchParams] = useSearchParams();
    const { region } = useArea();
    // The person's area is a sensible default delivery region; they can change it.
    const [form, setForm] = useState({ title: '', description: '', delivery_region: region ?? '', delivery_address: '', needed_by: '' });
    const [items, setItems] = useState([]);

    // Pre-fill from "Start a request" on a product page.
    const preselect = searchParams.get('product');
    const preProduct = useQuery({ queryKey: ['product', preselect], queryFn: () => api.product(preselect), enabled: Boolean(preselect) });
    useEffect(() => {
        const product = preProduct.data?.data;
        if (product) setItems((current) => (current.some((i) => i.product.id === product.id) ? current : [{ product, quantity: '1', notes: '' }, ...current]));
    }, [preProduct.data]);

    const create = useMutation({
        mutationFn: () =>
            api.createBuyerRequest({
                ...detailsPayload(form),
                items: items.map((item) => ({ product_id: item.product.id, quantity: Number(item.quantity), notes: item.notes || null })),
            }),
        onSuccess: ({ buyer_request: created }) => {
            queryClient.invalidateQueries({ queryKey: ['buyer-requests'] });
            queryClient.invalidateQueries({ queryKey: ['summary'] });
            toast(t('buyer.draftSavedToast'));
            navigate(`/buyer/requests/${created.id}`);
        },
    });

    const errors = create.error?.fieldErrors ?? {};
    const updateItem = (index, changes) => setItems((current) => current.map((item, i) => (i === index ? { ...item, ...changes } : item)));

    return (
        <>
            <PageHeader title={t('buyer.newTitle')} description={t('buyer.newDescription')} back={{ to: '/buyer/requests', label: t('nav.myRequests') }} />
            <ol className="mb-6 grid grid-cols-3 gap-2 text-[0.8rem] leading-tight sm:text-sm" aria-label={t('buyer.stepsLabel')}>
                {['buyer.step1', 'buyer.step2', 'buyer.step3'].map((key, index) => (
                    <li
                        key={key}
                        className="flex flex-col items-start gap-1.5 rounded-[var(--radius-control)] border border-rule bg-paper px-2.5 py-2 sm:flex-row sm:items-center sm:gap-2 sm:px-3"
                    >
                        <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-action text-xs font-bold text-on-action">{index + 1}</span>
                        {t(key)}
                    </li>
                ))}
            </ol>
            <form
                className="grid gap-6 lg:grid-cols-[1fr_20rem]"
                onSubmit={(event) => {
                    event.preventDefault();
                    create.mutate();
                }}
            >
                <div className="space-y-6">
                    <Panel title={t('buyer.panelDetails')}>
                        <RequestDetailsFields form={form} setForm={setForm} errors={errors} />
                    </Panel>

                    <Panel title={t('buyer.panelMaterials')} status={<span className="text-sm text-muted">{t('buyer.addedCount', { count: items.length })}</span>}>
                        <ProductPicker
                            excludeIds={items.map((item) => item.product.id)}
                            onPick={(product) => setItems((current) => [...current, { product, quantity: '1', notes: '' }])}
                        />
                        {errors.items && <p className="mt-2 text-sm font-medium text-danger">{errors.items}</p>}
                        {items.length === 0 ? (
                            <p className="mt-4 rounded-[var(--radius-control)] bg-concrete px-3 py-3 text-sm text-muted">{t('buyer.noItemsYet')}</p>
                        ) : (
                            <ul className="mt-4 divide-y divide-rule border-y border-rule">
                                {items.map((item, index) => (
                                    <li key={item.product.id} className="grid gap-3 py-3 sm:grid-cols-[1fr_8rem_auto] sm:items-start">
                                        <div>
                                            <p className="font-semibold">{item.product.name}</p>
                                            <label className="mt-1.5 block">
                                                <span className="sr-only">{t('buyer.itemNotesFor', { name: item.product.name })}</span>
                                                <input
                                                    className="field-input py-1.5 text-sm"
                                                    placeholder={t('buyer.itemNotesPlaceholder')}
                                                    value={item.notes}
                                                    onChange={(e) => updateItem(index, { notes: e.target.value })}
                                                />
                                            </label>
                                            {errors[`items.${index}.quantity`] && <p className="mt-1 text-sm text-danger">{errors[`items.${index}.quantity`]}</p>}
                                        </div>
                                        <label>
                                            <span className="block text-xs text-muted">{t('product.howMany', { unit: item.product.unit })}</span>
                                            <input
                                                type="number"
                                                min="1"
                                                inputMode="numeric"
                                                required
                                                className="field-input tabular"
                                                value={item.quantity}
                                                onChange={(e) => updateItem(index, { quantity: e.target.value })}
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            className="self-center text-sm font-semibold text-danger hover:underline"
                                            onClick={() => setItems((current) => current.filter((_, i) => i !== index))}
                                        >
                                            {t('common.remove')}
                                            <span className="sr-only"> {item.product.name}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                <aside className="space-y-3 lg:sticky lg:top-20 lg:self-start">
                    <Panel title={t('buyer.saveTitle')}>
                        <p className="text-sm text-ink-soft">{t('buyer.saveBody')}</p>
                        <div className="mt-4 space-y-2">
                            <FormError error={create.error && !Object.keys(errors).length ? create.error.message : null} />
                            <Button type="submit" block loading={create.isPending} loadingText={t('auth.saving')}>
                                {t('buyer.saveDraft')}
                            </Button>
                            <ButtonLink to="/buyer/requests" variant="ghost" block>
                                {t('common.cancel')}
                            </ButtonLink>
                        </div>
                    </Panel>
                </aside>
            </form>
        </>
    );
}
