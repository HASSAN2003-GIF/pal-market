import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
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
import { api } from '../lib/api';
import { formatNumber } from '../lib/format';

export default function ProductDetailPage() {
    const { productId } = useParams();
    const product = useQuery({ queryKey: ['product', productId], queryFn: () => api.product(productId) });

    return (
        <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <Link to="/materials" className="text-sm font-semibold text-pal-700 hover:underline">
                ‹ All materials
            </Link>
            <QueryBoundary query={product}>
                {({ data }) => <ProductBody product={data} />}
            </QueryBoundary>
        </div>
    );
}

function ProductBody({ product }) {
    const offers = product.supplier_offerings ?? [];

    return (
        <div className="mt-4 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div className="min-w-0">
                <p className="text-muted">
                    {product.category?.name}
                    {product.brand && `, ${product.brand.name}`}
                </p>
                <h1 className="mt-1 text-[2.6rem] sm:text-[3.2rem]">{product.name}</h1>
                <p className="mt-2 text-ink-soft">Sold per {product.unit}</p>
                {product.description && <p className="mt-4 max-w-2xl text-ink-soft">{product.description}</p>}

                <h2 className="mt-10 text-[1.8rem]">Supplier offers</h2>
                {offers.length === 0 ? (
                    <div className="mt-3">
                        <EmptyState title="No listed prices yet">
                            No approved supplier has a current price and stock for this product. Add it to a request and suppliers
                            can still quote for it.
                        </EmptyState>
                    </div>
                ) : (
                    <div className="mt-3 overflow-x-auto rounded-[var(--radius-panel)] border border-rule bg-paper">
                        <table className="w-full min-w-[34rem] text-left text-sm">
                            <thead className="border-b border-rule text-muted">
                                <tr>
                                    <th scope="col" className="px-4 py-2.5 font-semibold">Supplier</th>
                                    <th scope="col" className="px-4 py-2.5 font-semibold">Location</th>
                                    <th scope="col" className="px-4 py-2.5 text-right font-semibold">In stock</th>
                                    <th scope="col" className="px-4 py-2.5 text-right font-semibold">Price</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-rule">
                                {offers.map((offer) => (
                                    <tr key={offer.supplier_product_id}>
                                        <td className="px-4 py-3 font-semibold">{offer.supplier.business_name}</td>
                                        <td className="px-4 py-3 text-ink-soft">
                                            {[offer.location.district, offer.location.region].filter(Boolean).join(', ')}
                                        </td>
                                        <td className="tabular px-4 py-3 text-right">{formatNumber(offer.quantity)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Money amount={offer.price} currency={offer.currency} className="font-semibold" />
                                            <span className="block text-xs text-muted">per {offer.unit}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <aside>
                <ProductActions product={product} />
            </aside>
        </div>
    );
}

function ProductActions({ product }) {
    const { user } = useAuth();

    if (!user) {
        return (
            <Panel title="Get quotes for this">
                <p className="text-ink-soft">Create a free buyer account to add materials to a request and receive quotations.</p>
                <div className="mt-4 flex flex-col gap-2">
                    <ButtonLink to="/register" block>
                        Create account
                    </ButtonLink>
                    <ButtonLink to="/login" state={{ from: `/materials/${product.id}` }} variant="secondary" block>
                        Sign in
                    </ButtonLink>
                </div>
            </Panel>
        );
    }

    if (user.role === 'supplier') {
        return (
            <Panel title="Sell this product">
                <p className="text-ink-soft">Add it to your catalog with a price and stock so it appears in offers and you can quote on it.</p>
                <ButtonLink to={`/supplier/catalog?add=${product.id}`} className="mt-4" block>
                    Add to my catalog
                </ButtonLink>
            </Panel>
        );
    }

    if (user.role !== 'buyer') return null;

    return <AddToRequest product={product} />;
}

function AddToRequest({ product }) {
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
            toast(`Added ${product.name} to your request`);
            navigate(`/buyer/requests/${form.request_id}`);
        },
    });

    const draftList = drafts.data?.buyer_requests ?? [];
    const errors = add.error?.fieldErrors ?? {};

    return (
        <Panel title="Add to a request">
            {draftList.length === 0 && !drafts.isPending ? (
                <>
                    <p className="text-ink-soft">You have no draft requests. Start one with this product.</p>
                    <ButtonLink to={`/buyer/requests/new?product=${product.id}`} className="mt-4" block>
                        Start a request
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
                        label="Draft request"
                        required
                        value={form.request_id}
                        onChange={(e) => setForm({ ...form, request_id: e.target.value })}
                        error={errors.product_id}
                    >
                        <option value="">Choose a draft</option>
                        {draftList.map((draft) => (
                            <option key={draft.id} value={draft.id}>
                                {draft.title}
                            </option>
                        ))}
                    </SelectField>
                    <TextField
                        label={`Quantity (${product.unit})`}
                        type="number"
                        inputMode="numeric"
                        min="1"
                        required
                        value={form.quantity}
                        onChange={(e) => setForm({ ...form, quantity: e.target.value })}
                        error={errors.quantity}
                    />
                    <TextAreaField
                        label="Notes for suppliers"
                        optional
                        rows={2}
                        value={form.notes}
                        onChange={(e) => setForm({ ...form, notes: e.target.value })}
                    />
                    <Button type="submit" block loading={add.isPending} loadingText="Adding">
                        Add to request
                    </Button>
                    <p className="text-center text-sm">
                        or{' '}
                        <Link className="font-semibold text-pal-700 hover:underline" to={`/buyer/requests/new?product=${product.id}`}>
                            start a new request
                        </Link>
                    </p>
                </form>
            )}
        </Panel>
    );
}
