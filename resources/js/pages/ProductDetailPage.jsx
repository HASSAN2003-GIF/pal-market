import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { PinIcon } from '../components/icons';
import ProductGallery from '../components/ProductGallery';
import ProductThumb from '../components/ProductThumb';
import {
    Button,
    ButtonLink,
    EmptyState,
    FormError,
    Money,
    Panel,
    QueryBoundary,
    SelectField,
    TextAreaField,
    TextField,
    useToast,
} from '../components/ui';
import { useAuth } from '../context/AuthContext';
import { useI18n } from '../i18n';
import { api } from '../lib/api';
import { formatNumber } from '../lib/format';
import { productName, secondaryName } from '../lib/products';
import { useArea } from '../location/LocationContext';
import { regionLabel } from '../location/regions';

export default function ProductDetailPage() {
    const { t } = useI18n();
    const { productId } = useParams();
    const { region } = useArea();
    const product = useQuery({
        queryKey: ['product', productId, region],
        queryFn: () => api.product(productId, { region: region ?? undefined }),
    });

    return (
        <div className="mx-auto max-w-6xl 2xl:max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
            <Link to="/materials" className="tap text-sm font-semibold text-pal-700 hover:underline">
                ‹ {t('materials.allMaterials')}
            </Link>
            <QueryBoundary query={product}>{({ data }) => <ProductBody product={data} />}</QueryBoundary>
        </div>
    );
}

function ProductBody({ product }) {
    const { t, locale } = useI18n();
    const { region } = useArea();
    const offers = product.supplier_offerings ?? [];
    const localCount = offers.filter((offer) => offer.in_region).length;

    return (
        <div className="mt-4 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div className="min-w-0">
                <div className="grid gap-6 sm:grid-cols-[minmax(0,16rem)_1fr] lg:grid-cols-[minmax(0,18rem)_1fr]">
                    <ProductGallery images={product.images ?? []} name={product.name} />
                    <div className="min-w-0">
                        <p className="text-muted">
                            {product.category?.name}
                            {product.brand && `, ${product.brand.name}`}
                        </p>
                        <h1 className="mt-1 text-[2.3rem] sm:text-[2.9rem]">{productName(product, locale)}</h1>
                        {secondaryName(product, locale) && <p className="text-ink-soft">{secondaryName(product, locale)}</p>}
                        <p className="mt-2 text-ink-soft">{t('product.soldPer', { unit: product.unit })}</p>
                        {product.description && <p className="mt-4 max-w-2xl text-ink-soft">{product.description}</p>}
                    </div>
                </div>

                <h2 className="mt-10 text-[1.7rem]">{t('product.offersTitle')}</h2>
                {region && offers.length > 0 && (
                    <p className="mt-1 text-sm text-muted">
                        {localCount > 0
                            ? t('location.stockedInArea', { count: localCount, n: localCount, region: regionLabel(region, locale) })
                            : t('location.noneInArea', { region: regionLabel(region, locale) })}
                    </p>
                )}
                {offers.length === 0 ? (
                    <div className="mt-3">
                        <EmptyState title={t('product.noOffersTitle')}>{t('product.noOffersBody')}</EmptyState>
                    </div>
                ) : (
                    <ul className="mt-3 divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                        {offers.map((offer) => (
                            <li key={offer.supplier_product_id} className="grid gap-2 px-4 py-3.5 sm:grid-cols-[auto_1fr_auto_auto] sm:items-center sm:gap-6">
                                <ProductThumb src={offer.images?.[0]?.thumb} alt={t('photos.photoFrom', { name: offer.supplier.business_name })} size="sm" className="hidden sm:inline-block" />
                                <div className="min-w-0">
                                    <p className="font-bold">{offer.supplier.business_name}</p>
                                    <p className="text-sm text-ink-soft">
                                        {[offer.location.district, regionLabel(offer.location.region, locale)].filter(Boolean).join(', ')}
                                    </p>
                                    {offer.in_region && (
                                        <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-bold text-ok">
                                            <PinIcon className="h-3.5 w-3.5" />
                                            {t('location.inYourArea')}
                                        </span>
                                    )}
                                </div>
                                <p className="text-sm sm:text-right">
                                    <span className="text-muted">{t('product.inStock')}</span>{' '}
                                    <span className="tabular font-semibold">{formatNumber(offer.quantity)}</span>
                                </p>
                                <p className="sm:text-right">
                                    <Money amount={offer.price} currency={offer.currency} className="text-lg font-bold" />
                                    <span className="block text-xs text-muted">{t('common.perUnit', { unit: offer.unit })}</span>
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="mt-3 text-sm text-muted">{t('product.priceNote')}</p>
            </div>

            <aside>
                <ProductActions product={product} />
            </aside>
        </div>
    );
}

function ProductActions({ product }) {
    const { t } = useI18n();
    const { user } = useAuth();

    if (!user) {
        return (
            <Panel title={t('product.getPricesTitle')}>
                <p className="text-ink-soft">{t('product.getPricesBody')}</p>
                <div className="mt-4 flex flex-col gap-2">
                    <ButtonLink to="/register?role=buyer" block>
                        {t('layout.createAccount')}
                    </ButtonLink>
                    <ButtonLink to="/login" state={{ from: `/materials/${product.id}` }} variant="secondary" block>
                        {t('layout.signIn')}
                    </ButtonLink>
                </div>
            </Panel>
        );
    }

    if (user.role === 'supplier') {
        return (
            <Panel title={t('product.sellTitle')}>
                <p className="text-ink-soft">{t('product.sellBody')}</p>
                <ButtonLink to={`/supplier/catalog?add=${product.id}`} className="mt-4" block>
                    {t('product.sellCta')}
                </ButtonLink>
            </Panel>
        );
    }

    if (user.role !== 'buyer') return null;

    return <AddToRequest product={product} />;
}

function AddToRequest({ product }) {
    const { t } = useI18n();
    const navigate = useNavigate();
    const toast = useToast();
    const queryClient = useQueryClient();
    const drafts = useQuery({
        queryKey: ['buyer-requests', { status: 'draft', per_page: 50 }],
        queryFn: () => api.buyerRequests({ status: 'draft', per_page: 50 }),
    });
    const [form, setForm] = useState({ request_id: '', quantity: '1', notes: '' });

    const add = useMutation({
        mutationFn: () =>
            api.addRequestItem(form.request_id, {
                product_id: product.id,
                quantity: Number(form.quantity),
                notes: form.notes || null,
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['buyer-requests'] });
            queryClient.invalidateQueries({ queryKey: ['buyer-request', String(form.request_id)] });
            toast(t('product.addedToast', { name: product.name }));
            navigate(`/buyer/requests/${form.request_id}`);
        },
    });

    const draftList = drafts.data?.buyer_requests ?? [];
    const errors = add.error?.fieldErrors ?? {};

    return (
        <Panel title={t('product.addTitle')}>
            {draftList.length === 0 && !drafts.isPending ? (
                <>
                    <p className="text-ink-soft">{t('product.noDrafts')}</p>
                    <ButtonLink to={`/buyer/requests/new?product=${product.id}`} className="mt-4" block>
                        {t('product.startRequest')}
                    </ButtonLink>
                </>
            ) : (
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        add.mutate();
                    }}
                >
                    <FormError error={add.error && !Object.keys(errors).length ? add.error.message : null} />
                    <SelectField
                        label={t('product.whichRequest')}
                        hint={t('product.whichRequestHint')}
                        required
                        value={form.request_id}
                        onChange={(e) => setForm({ ...form, request_id: e.target.value })}
                        error={errors.product_id}
                    >
                        <option value="">{t('product.chooseRequest')}</option>
                        {draftList.map((draft) => (
                            <option key={draft.id} value={draft.id}>
                                {draft.title}
                            </option>
                        ))}
                    </SelectField>
                    <TextField
                        label={t('product.howMany', { unit: product.unit })}
                        type="number"
                        inputMode="numeric"
                        min="1"
                        required
                        value={form.quantity}
                        onChange={(e) => setForm({ ...form, quantity: e.target.value })}
                        error={errors.quantity}
                    />
                    <TextAreaField
                        label={t('product.notesForSellers')}
                        optional
                        rows={2}
                        value={form.notes}
                        onChange={(e) => setForm({ ...form, notes: e.target.value })}
                    />
                    <Button type="submit" block loading={add.isPending} loadingText={t('product.adding')}>
                        {t('product.addCta')}
                    </Button>
                    <p className="text-center text-sm">
                        <Link className="tap font-semibold text-pal-700 hover:underline" to={`/buyer/requests/new?product=${product.id}`}>
                            {t('product.orStartNew')}
                        </Link>
                    </p>
                </form>
            )}
        </Panel>
    );
}
