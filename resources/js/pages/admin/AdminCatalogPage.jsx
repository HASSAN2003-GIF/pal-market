import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { Button, FormError, PageHeader, Pagination, Panel, QueryBoundary, SelectField, TextAreaField, TextField, useToast } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import AdminTable from './AdminTable';

function useCatalogSave(fn, message, after) {
    const queryClient = useQueryClient();
    const toast = useToast();
    return useMutation({
        mutationFn: fn,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin', 'catalog'] });
            queryClient.invalidateQueries({ queryKey: ['catalog-filters'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
            toast(message);
            after?.();
        },
    });
}

function ProductForm({ initial, taxonomy, onDone, submitLabel }) {
    const [form, setForm] = useState({
        name: initial?.name ?? '',
        category_id: initial?.category_id ? String(initial.category_id) : '',
        brand_id: initial?.brand_id ? String(initial.brand_id) : '',
        unit: initial?.unit ?? '',
        description: initial?.description ?? '',
    });
    const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });
    const save = useCatalogSave(
        () => {
            const data = {
                ...form,
                category_id: Number(form.category_id),
                brand_id: form.brand_id ? Number(form.brand_id) : null,
                description: form.description || null,
            };
            return initial ? api.admin.updateProduct(initial.id, data) : api.admin.createProduct(data);
        },
        initial ? 'Product updated' : 'Product added',
        onDone,
    );
    const errors = save.error?.fieldErrors ?? {};

    return (
        <form
            className="grid gap-4 sm:grid-cols-2"
            onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
            }}
        >
            <div className="sm:col-span-2">
                <FormError error={save.error && !Object.keys(errors).length ? save.error.message : null} />
            </div>
            <TextField label="Product name" required value={form.name} onChange={set('name')} error={errors.name} placeholder="Simba Cement 42.5R" />
            <TextField label="Selling unit" required value={form.unit} onChange={set('unit')} error={errors.unit} placeholder="Bag (50kg)" />
            <SelectField label="Category" required value={form.category_id} onChange={set('category_id')} error={errors.category_id}>
                <option value="">Choose a category</option>
                {taxonomy.categories.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </SelectField>
            <SelectField label="Brand" optional value={form.brand_id} onChange={set('brand_id')} error={errors.brand_id}>
                <option value="">No brand</option>
                {taxonomy.brands.map((b) => (
                    <option key={b.id} value={b.id}>
                        {b.name}
                    </option>
                ))}
            </SelectField>
            <TextAreaField className="sm:col-span-2" label="Description" optional value={form.description} onChange={set('description')} />
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit" loading={save.isPending}>
                    {submitLabel}
                </Button>
                <Button variant="secondary" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

function TaxonomyList({ title, singular, items, create, updateItem }) {
    const [name, setName] = useState('');
    const add = useCatalogSave(() => create({ name }), `${singular} added`, () => setName(''));
    const toggle = useCatalogSave(({ id, is_active }) => updateItem(id, { is_active }), `${singular} updated`);

    return (
        <Panel title={title}>
            <form
                className="flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    add.mutate();
                }}
            >
                <label className="flex-1">
                    <span className="sr-only">New {singular.toLowerCase()} name</span>
                    <input className="field-input" placeholder={`New ${singular.toLowerCase()}`} required value={name} onChange={(e) => setName(e.target.value)} />
                </label>
                <Button type="submit" variant="secondary" loading={add.isPending}>
                    Add
                </Button>
            </form>
            {add.error && <p className="mt-1 text-sm text-danger">{add.error.fieldErrors?.name ?? add.error.message}</p>}
            <ul className="mt-3 divide-y divide-rule">
                {items.map((item) => (
                    <li key={item.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                        <span className={item.is_active ? '' : 'text-muted'}>
                            {item.name} <span className="text-muted">({item.products_count})</span>
                            {!item.is_active && <span className="ml-1 font-semibold text-warn">hidden</span>}
                        </span>
                        <button
                            type="button"
                            className="font-semibold text-pal-700 hover:underline"
                            onClick={() => toggle.mutate({ id: item.id, is_active: !item.is_active })}
                        >
                            {item.is_active ? 'Hide' : 'Show'}
                        </button>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

export default function AdminCatalogPage() {
    const [params, update] = useListParams();
    const [editing, setEditing] = useState(null); // 'new' | product
    const taxonomy = useQuery({ queryKey: ['admin', 'catalog', 'taxonomy'], queryFn: api.admin.taxonomy });
    const query = { search: params.search, category_id: params.category_id, status: params.status, page: params.page };
    const products = useQuery({
        queryKey: ['admin', 'catalog', 'products', query],
        queryFn: () => api.admin.products(query),
        placeholderData: keepPreviousData,
    });
    const toggle = useCatalogSave((product) => api.admin.updateProduct(product.id, { is_active: !product.is_active }), 'Product updated');

    const columns = [
        {
            key: 'name',
            label: 'Product',
            render: (p) => (
                <span>
                    <span className="block font-semibold">{p.name}</span>
                    <span className="block text-muted">
                        {p.category?.name}
                        {p.brand && `, ${p.brand.name}`}. Per {p.unit}
                    </span>
                </span>
            ),
        },
        { key: 'offers', label: 'Suppliers listing', render: (p) => p.supplier_products_count },
        {
            key: 'status',
            label: 'Status',
            render: (p) => <span className={p.is_active ? 'font-semibold text-ok' : 'font-semibold text-muted'}>{p.is_active ? 'Active' : 'Hidden'}</span>,
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (p) => (
                <span className="inline-flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => setEditing(p)}>
                        Edit
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => toggle.mutate(p)}>
                        {p.is_active ? 'Hide' : 'Show'}
                    </Button>
                </span>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Product catalog"
                description="The shared list buyers request from and suppliers price against. Hidden products stay on past requests and orders."
                actions={!editing && taxonomy.data && <Button onClick={() => setEditing('new')}>Add product</Button>}
            />
            {editing && taxonomy.data && (
                <Panel title={editing === 'new' ? 'New product' : `Edit ${editing.name}`} className="mb-6">
                    <ProductForm
                        key={editing === 'new' ? 'new' : editing.id}
                        initial={editing === 'new' ? null : editing}
                        taxonomy={taxonomy.data}
                        submitLabel={editing === 'new' ? 'Add product' : 'Save product'}
                        onDone={() => setEditing(null)}
                    />
                </Panel>
            )}
            <div className="grid gap-6 xl:grid-cols-[1fr_18rem]">
                <div className="min-w-0">
                    <div className="mb-4 grid gap-3 sm:grid-cols-[1fr_12rem_10rem]">
                        <SearchInput label="Search products" placeholder="Product name" value={params.search ?? ''} onChange={(search) => update({ search })} />
                        <label>
                            <span className="sr-only">Category</span>
                            <select className="field-input" value={params.category_id ?? ''} onChange={(e) => update({ category_id: e.target.value })}>
                                <option value="">All categories</option>
                                {(taxonomy.data?.categories ?? []).map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span className="sr-only">Status</span>
                            <select className="field-input" value={params.status ?? ''} onChange={(e) => update({ status: e.target.value })}>
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Hidden</option>
                            </select>
                        </label>
                    </div>
                    <QueryBoundary query={products}>
                        {({ data: rows, meta }) => (
                            <>
                                <AdminTable columns={columns} rows={rows} />
                                <Pagination meta={meta} onPage={(page) => update({ page })} />
                            </>
                        )}
                    </QueryBoundary>
                </div>
                <QueryBoundary query={taxonomy}>
                    {(data) => (
                        <div className="space-y-6">
                            <TaxonomyList title="Categories" singular="Category" items={data.categories} create={api.admin.createCategory} updateItem={api.admin.updateCategory} />
                            <TaxonomyList title="Brands" singular="Brand" items={data.brands} create={api.admin.createBrand} updateItem={api.admin.updateBrand} />
                        </div>
                    )}
                </QueryBoundary>
            </div>
        </>
    );
}
