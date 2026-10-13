import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import ProductPicker from '../../components/ProductPicker';
import { Button, ButtonLink, ConfirmDialog, EmptyState, FormError, Money, Notice, PageHeader, Panel, QueryBoundary, useToast } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatMoney } from '../../lib/format';

function useCatalogMutation(fn, success) {
    const queryClient = useQueryClient();
    const toast = useToast();
    return useMutation({
        mutationFn: fn,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['supplier-products'] });
            queryClient.invalidateQueries({ queryKey: ['summary'] });
            if (success) toast(success);
        },
    });
}

export default function CatalogPage() {
    const t = useT();
    const { user } = useAuth();
    const approved = user?.supplier?.status === 'approved';
    const products = useQuery({ queryKey: ['supplier-products'], queryFn: api.supplierProducts });
    const locations = useQuery({ queryKey: ['supplier-locations'], queryFn: api.supplierLocations });
    const [searchParams, setSearchParams] = useSearchParams();

    const add = useCatalogMutation((productId) => api.addSupplierProduct({ product_id: productId }), t('supplier.productAddedToast'));

    // "Add to my products" links arrive with ?add=<product id>.
    const handledAdd = useRef(false);
    useEffect(() => {
        const toAdd = searchParams.get('add');
        if (!toAdd || handledAdd.current || !approved || !products.data) return;
        handledAdd.current = true;
        const already = products.data.supplier_products.some((sp) => String(sp.product_id) === toAdd);
        if (!already) add.mutate(Number(toAdd));
        setSearchParams({}, { replace: true });
    }, [searchParams, approved, products.data]); // eslint-disable-line react-hooks/exhaustive-deps

    const activeLocations = (locations.data?.supplier_locations ?? []).filter((l) => l.status === 'active');

    return (
        <>
            <PageHeader title={t('nav.catalog')} description={t('supplier.catalogDescription')} />

            {!approved ? (
                <Notice tone="attention" title={t('supplier.afterApprovalTitle')}>
                    {t('supplier.catalogAfterApproval')}
                </Notice>
            ) : (
                <>
                    {locations.data && activeLocations.length === 0 && (
                        <Notice
                            tone="attention"
                            className="mb-4"
                            title={t('supplier.needShopTitle')}
                            action={
                                <ButtonLink size="sm" to="/supplier/locations">
                                    {t('supplier.addShop')}
                                </ButtonLink>
                            }
                        >
                            {t('supplier.needShopBody')}
                        </Notice>
                    )}
                    <Panel className="mb-6">
                        <ProductPicker
                            label={t('supplier.addProductYouSell')}
                            excludeIds={(products.data?.supplier_products ?? []).map((sp) => sp.product_id)}
                            onPick={(product) => add.mutate(product.id)}
                        />
                        <FormError error={add.error?.fieldErrors?.product_id ?? add.error?.message} />
                        <p className="mt-2 text-sm text-muted">{t('supplier.missingProduct')}</p>
                    </Panel>

                    <QueryBoundary query={products}>
                        {({ supplier_products: rows }) =>
                            rows.length === 0 ? (
                                <EmptyState title={t('supplier.catalogEmptyTitle')}>{t('supplier.catalogEmptyBody')}</EmptyState>
                            ) : (
                                <ul className="space-y-3">
                                    {rows.map((sp) => (
                                        <CatalogRow key={sp.id} item={sp} locations={activeLocations} />
                                    ))}
                                </ul>
                            )
                        }
                    </QueryBoundary>
                </>
            )}
        </>
    );
}

function CatalogRow({ item, locations }) {
    const t = useT();
    const currentPrice = item.prices.find((p) => p.is_active) ?? null;
    const [price, setPrice] = useState(currentPrice ? String(Math.round(Number(currentPrice.price))) : '');
    const [confirmRemove, setConfirmRemove] = useState(false);

    const savePrice = useCatalogMutation(
        () =>
            currentPrice
                ? api.updateSupplierPrice(currentPrice.id, { price: Number(price) })
                : api.addSupplierPrice({ supplier_product_id: item.id, price: Number(price), currency: 'TZS', unit: item.product.unit }),
        t('supplier.priceSavedToast'),
    );
    const toggle = useCatalogMutation(
        () => api.updateSupplierProduct(item.id, { is_active: !item.is_active }),
        item.is_active ? t('supplier.hiddenToast') : t('supplier.listedToast'),
    );
    const remove = useCatalogMutation(() => api.removeSupplierProduct(item.id), t('supplier.removedToast'));

    const stockedAt = new Set(item.inventories.map((inv) => inv.supplier_location_id));
    const unstocked = locations.filter((l) => !stockedAt.has(l.id));

    return (
        <li className="overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-rule px-4 py-3 sm:px-5">
                <div>
                    <Link to={`/materials/${item.product_id}`} className="text-[1.2rem] leading-tight font-extrabold hover:text-pal-700">
                        {item.product.name}
                    </Link>
                    <p className="text-sm text-muted">
                        {item.product.category?.name} · {t('common.perUnit', { unit: item.product.unit })}
                        {!item.is_active && <span className="ml-2 font-semibold text-warn">{t('supplier.hiddenFromBuyers')}</span>}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => toggle.mutate()} loading={toggle.isPending}>
                        {item.is_active ? t('common.hide') : t('supplier.listAgain')}
                    </Button>
                    <Button size="sm" variant="ghost" className="text-danger" onClick={() => setConfirmRemove(true)}>
                        {t('common.remove')}
                    </Button>
                </div>
            </div>

            <div className="grid gap-5 px-4 py-4 sm:px-5 md:grid-cols-[16rem_1fr]">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        savePrice.mutate();
                    }}
                >
                    <label className="block text-sm font-semibold" htmlFor={`price-${item.id}`}>
                        {t('supplier.pricePer', { unit: item.product.unit })}
                    </label>
                    <div className="mt-1.5 flex gap-2">
                        <input
                            id={`price-${item.id}`}
                            type="number"
                            min="1"
                            required
                            inputMode="decimal"
                            className="field-input tabular"
                            value={price}
                            onChange={(e) => setPrice(e.target.value)}
                            aria-invalid={savePrice.error ? 'true' : undefined}
                        />
                        <Button
                            type="submit"
                            size="md"
                            variant="secondary"
                            loading={savePrice.isPending}
                            disabled={!price || (currentPrice && Number(price) === Number(currentPrice.price))}
                        >
                            {t('common.save')}
                        </Button>
                    </div>
                    {savePrice.error ? (
                        <p className="mt-1 text-sm text-danger">{savePrice.error.fieldErrors?.price ?? savePrice.error.message}</p>
                    ) : (
                        <p className="mt-1 text-sm text-muted">{currentPrice ? t('supplier.listedAt', { price: formatMoney(currentPrice.price) }) : t('supplier.noPriceYet')}</p>
                    )}
                </form>

                <div>
                    <p className="text-sm font-semibold">{t('supplier.stockByShop')}</p>
                    {item.inventories.length === 0 && <p className="mt-1.5 text-sm text-muted">{t('supplier.noStock')}</p>}
                    <ul className="mt-1.5 divide-y divide-rule">
                        {item.inventories.map((inventory) => (
                            <StockRow key={inventory.id} inventory={inventory} unit={item.product.unit} />
                        ))}
                    </ul>
                    {unstocked.length > 0 && <AddStock item={item} locations={unstocked} />}
                </div>
            </div>

            <ConfirmDialog
                open={confirmRemove}
                title={t('supplier.removeTitle', { name: item.product.name })}
                confirmLabel={t('supplier.removeConfirm')}
                confirmVariant="danger"
                busy={remove.isPending}
                error={remove.error?.message}
                onCancel={() => setConfirmRemove(false)}
                onConfirm={() => remove.mutate()}
            >
                <p>{t('supplier.removeBody')}</p>
            </ConfirmDialog>
        </li>
    );
}

function StockRow({ inventory, unit }) {
    const t = useT();
    const [quantity, setQuantity] = useState(String(inventory.quantity));
    const save = useCatalogMutation(() => api.updateInventory(inventory.id, { quantity: Number(quantity) }), t('supplier.stockSavedToast'));
    const low = inventory.quantity <= inventory.low_stock_threshold;

    return (
        <li className="flex flex-wrap items-center justify-between gap-2 py-2">
            <span className="text-sm">
                {inventory.supplier_location?.name}
                {low && <span className="ml-2 rounded-full bg-safety-soft px-2 py-0.5 text-xs font-semibold text-warn">{t('supplier.lowStock')}</span>}
            </span>
            <form
                className="flex items-center gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <label className="flex items-center gap-2">
                    <span className="sr-only">{t('supplier.amountAt', { name: inventory.supplier_location?.name })}</span>
                    <input type="number" min="0" inputMode="numeric" className="field-input tabular w-28 py-1.5" value={quantity} onChange={(e) => setQuantity(e.target.value)} />
                    <span className="text-sm text-muted">{unit}</span>
                </label>
                {Number(quantity) !== inventory.quantity && (
                    <Button type="submit" size="sm" loading={save.isPending}>
                        {t('common.save')}
                    </Button>
                )}
            </form>
            {save.error && <p className="w-full text-sm text-danger">{save.error.message}</p>}
        </li>
    );
}

function AddStock({ item, locations }) {
    const t = useT();
    const [form, setForm] = useState({ location: String(locations[0].id), quantity: '', threshold: '10' });
    const add = useCatalogMutation(
        () =>
            api.addInventory({
                supplier_product_id: item.id,
                supplier_location_id: Number(form.location),
                quantity: Number(form.quantity),
                low_stock_threshold: Number(form.threshold || 0),
            }),
        t('supplier.stockSavedToast'),
    );

    return (
        <form
            className="mt-2 flex flex-wrap items-end gap-2 rounded-[var(--radius-control)] bg-concrete px-3 py-2.5"
            onSubmit={(e) => {
                e.preventDefault();
                add.mutate(undefined, { onSuccess: () => setForm({ ...form, quantity: '' }) });
            }}
        >
            <label className="text-sm">
                <span className="block text-xs text-muted">{t('supplier.shop')}</span>
                <select className="field-input py-1.5" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })}>
                    {locations.map((location) => (
                        <option key={location.id} value={location.id}>
                            {location.name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="text-sm">
                <span className="block text-xs text-muted">{t('buyer.colQuantity')}</span>
                <input type="number" min="0" required className="field-input tabular w-24 py-1.5" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} />
            </label>
            <label className="text-sm">
                <span className="block text-xs text-muted">{t('supplier.warnBelow')}</span>
                <input type="number" min="0" className="field-input tabular w-20 py-1.5" value={form.threshold} onChange={(e) => setForm({ ...form, threshold: e.target.value })} />
            </label>
            <Button type="submit" size="sm" loading={add.isPending}>
                {t('supplier.addStock')}
            </Button>
            {add.error && <p className="w-full text-sm text-danger">{Object.values(add.error.fieldErrors)[0] ?? add.error.message}</p>}
        </form>
    );
}
