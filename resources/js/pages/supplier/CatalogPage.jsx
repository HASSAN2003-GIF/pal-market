import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import ProductPicker from '../../components/ProductPicker';
import { Button, ButtonLink, ConfirmDialog, EmptyState, FormError, Money, Notice, PageHeader, Panel, QueryBoundary, useToast } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../lib/api';
import { formatNumber } from '../../lib/format';

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
    const { user } = useAuth();
    const approved = user?.supplier?.status === 'approved';
    const products = useQuery({ queryKey: ['supplier-products'], queryFn: api.supplierProducts });
    const locations = useQuery({ queryKey: ['supplier-locations'], queryFn: api.supplierLocations });
    const [searchParams, setSearchParams] = useSearchParams();

    const add = useCatalogMutation((productId) => api.addSupplierProduct({ product_id: productId }), 'Added to your catalog');

    // "Add to my catalog" links arrive with ?add=<product id>.
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
            <PageHeader
                title="Catalog & stock"
                description="The products you sell, your current price, and stock at each location. Buyers see these on product pages; you can only quote on products listed here."
            />

            {!approved ? (
                <Notice tone="attention" title="Available after approval">
                    Your catalog opens once an administrator approves your supplier account.
                </Notice>
            ) : (
                <>
                    {locations.data && activeLocations.length === 0 && (
                        <Notice tone="attention" className="mb-4" title="Add a location to record stock" action={<ButtonLink size="sm" to="/supplier/locations">Add location</ButtonLink>}>
                            Stock is held per location, such as a shop or yard.
                        </Notice>
                    )}
                    <Panel className="mb-6">
                        <ProductPicker
                            label="Add a product you sell"
                            excludeIds={(products.data?.supplier_products ?? []).map((sp) => sp.product_id)}
                            onPick={(product) => add.mutate(product.id)}
                        />
                        <FormError error={add.error?.fieldErrors?.product_id ?? add.error?.message} />
                        <p className="mt-2 text-sm text-muted">
                            Missing a product? Ask PAL Market support to add it to the shared catalog.
                        </p>
                    </Panel>

                    <QueryBoundary query={products}>
                        {({ supplier_products: rows }) =>
                            rows.length === 0 ? (
                                <EmptyState title="Your catalog is empty">Search above to add the products you stock.</EmptyState>
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
    const currentPrice = item.prices.find((p) => p.is_active) ?? null;
    const [price, setPrice] = useState(currentPrice ? String(Math.round(Number(currentPrice.price))) : '');
    const [confirmRemove, setConfirmRemove] = useState(false);

    const savePrice = useCatalogMutation(
        () =>
            currentPrice
                ? api.updateSupplierPrice(currentPrice.id, { price: Number(price) })
                : api.addSupplierPrice({ supplier_product_id: item.id, price: Number(price), currency: 'TZS', unit: item.product.unit }),
        'Price saved',
    );
    const toggle = useCatalogMutation(() => api.updateSupplierProduct(item.id, { is_active: !item.is_active }), item.is_active ? 'Hidden from buyers' : 'Listed for buyers');
    const remove = useCatalogMutation(() => api.removeSupplierProduct(item.id), 'Removed from your catalog');

    const stockedAt = new Set(item.inventories.map((inv) => inv.supplier_location_id));
    const unstocked = locations.filter((l) => !stockedAt.has(l.id));

    return (
        <li className="overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-rule px-4 py-3 sm:px-5">
                <div>
                    <Link to={`/materials/${item.product_id}`} className="font-display text-[1.4rem] leading-tight font-semibold hover:text-pal-700">
                        {item.product.name}
                    </Link>
                    <p className="text-sm text-muted">
                        {item.product.category?.name}, per {item.product.unit}
                        {!item.is_active && <span className="ml-2 font-semibold text-warn">Hidden from buyers</span>}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => toggle.mutate()} loading={toggle.isPending}>
                        {item.is_active ? 'Hide' : 'List again'}
                    </Button>
                    <Button size="sm" variant="ghost" className="text-danger" onClick={() => setConfirmRemove(true)}>
                        Remove
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
                        Price per {item.product.unit} (TZS)
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
                        <Button type="submit" size="md" variant="secondary" loading={savePrice.isPending} disabled={!price || (currentPrice && Number(price) === Number(currentPrice.price))}>
                            Save
                        </Button>
                    </div>
                    {savePrice.error ? (
                        <p className="mt-1 text-sm text-danger">{savePrice.error.fieldErrors?.price ?? savePrice.error.message}</p>
                    ) : (
                        <p className="mt-1 text-sm text-muted">{currentPrice ? <>Listed at <Money amount={currentPrice.price} /></> : 'No price yet: buyers will not see an offer.'}</p>
                    )}
                </form>

                <div>
                    <p className="text-sm font-semibold">Stock by location</p>
                    {item.inventories.length === 0 && <p className="mt-1.5 text-sm text-muted">No stock recorded.</p>}
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
                title={`Remove ${item.product.name}?`}
                confirmLabel="Remove product"
                confirmVariant="danger"
                busy={remove.isPending}
                error={remove.error?.message}
                onCancel={() => setConfirmRemove(false)}
                onConfirm={() => remove.mutate()}
            >
                <p>Its price and stock records are deleted too. To stop selling it temporarily, use Hide instead.</p>
            </ConfirmDialog>
        </li>
    );
}

function StockRow({ inventory, unit }) {
    const [quantity, setQuantity] = useState(String(inventory.quantity));
    const save = useCatalogMutation(() => api.updateInventory(inventory.id, { quantity: Number(quantity) }), 'Stock updated');
    const low = inventory.quantity <= inventory.low_stock_threshold;

    return (
        <li className="flex flex-wrap items-center justify-between gap-2 py-2">
            <span className="text-sm">
                {inventory.supplier_location?.name}
                {low && <span className="ml-2 rounded-full bg-safety-soft px-2 py-0.5 text-xs font-semibold text-warn">Low stock</span>}
            </span>
            <form
                className="flex items-center gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <label className="flex items-center gap-2">
                    <span className="sr-only">Quantity at {inventory.supplier_location?.name}</span>
                    <input type="number" min="0" inputMode="numeric" className="field-input tabular w-28 py-1.5" value={quantity} onChange={(e) => setQuantity(e.target.value)} />
                    <span className="text-sm text-muted">{unit}</span>
                </label>
                {Number(quantity) !== inventory.quantity && (
                    <Button type="submit" size="sm" loading={save.isPending}>
                        Save
                    </Button>
                )}
            </form>
            {save.error && <p className="w-full text-sm text-danger">{save.error.message}</p>}
        </li>
    );
}

function AddStock({ item, locations }) {
    const [form, setForm] = useState({ location: String(locations[0].id), quantity: '', threshold: '10' });
    const add = useCatalogMutation(
        () =>
            api.addInventory({
                supplier_product_id: item.id,
                supplier_location_id: Number(form.location),
                quantity: Number(form.quantity),
                low_stock_threshold: Number(form.threshold || 0),
            }),
        'Stock recorded',
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
                <span className="block text-xs text-muted">Location</span>
                <select className="field-input py-1.5" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })}>
                    {locations.map((location) => (
                        <option key={location.id} value={location.id}>
                            {location.name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="text-sm">
                <span className="block text-xs text-muted">Quantity</span>
                <input type="number" min="0" required className="field-input tabular w-24 py-1.5" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} />
            </label>
            <label className="text-sm">
                <span className="block text-xs text-muted">Warn below</span>
                <input type="number" min="0" className="field-input tabular w-20 py-1.5" value={form.threshold} onChange={(e) => setForm({ ...form, threshold: e.target.value })} />
            </label>
            <Button type="submit" size="sm" loading={add.isPending}>
                Add stock
            </Button>
            {add.error && <p className="w-full text-sm text-danger">{Object.values(add.error.fieldErrors)[0] ?? add.error.message}</p>}
            <span className="sr-only" aria-live="polite">{add.isSuccess ? `Recorded ${formatNumber(form.quantity)}` : ''}</span>
        </form>
    );
}
