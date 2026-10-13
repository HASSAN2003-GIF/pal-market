import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, ConfirmDialog, EmptyState, FormError, Notice, PageHeader, Panel, QueryBoundary, SelectField, TextField, useToast } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../lib/api';
import { TANZANIA_REGIONS } from '../../lib/format';

const EMPTY = { name: '', address: '', region: '', district: '', ward: '', phone: '', is_primary: false };

function payload(form) {
    return {
        name: form.name,
        address: form.address,
        region: form.region,
        district: form.district || null,
        ward: form.ward || null,
        phone: form.phone || null,
        is_primary: Boolean(form.is_primary),
        ...(form.status ? { status: form.status } : {}),
    };
}

function LocationForm({ initial, submitLabel, onSubmit, onCancel, busy, error }) {
    const [form, setForm] = useState(initial);
    const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });
    const errors = error?.fieldErrors ?? {};

    return (
        <form
            className="grid gap-4 sm:grid-cols-2"
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit(payload(form));
            }}
        >
            <div className="sm:col-span-2">
                <FormError error={error && !Object.keys(errors).length ? error.message : null} />
            </div>
            <TextField label="Location name" placeholder="Main yard" required value={form.name} onChange={set('name')} error={errors.name} />
            <TextField label="Phone" optional type="tel" value={form.phone ?? ''} onChange={set('phone')} error={errors.phone} />
            <TextField className="sm:col-span-2" label="Address" required value={form.address} onChange={set('address')} error={errors.address} />
            <SelectField label="Region" required value={form.region} onChange={set('region')} error={errors.region}>
                <option value="">Choose a region</option>
                {TANZANIA_REGIONS.map((region) => (
                    <option key={region}>{region}</option>
                ))}
            </SelectField>
            <TextField label="District" optional value={form.district ?? ''} onChange={set('district')} error={errors.district} />
            <label className="flex items-center gap-2.5 sm:col-span-2">
                <input type="checkbox" className="h-4 w-4 accent-pal-600" checked={Boolean(form.is_primary)} onChange={(e) => setForm({ ...form, is_primary: e.target.checked })} />
                <span className="font-semibold">Main location</span>
            </label>
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit" loading={busy}>
                    {submitLabel}
                </Button>
                {onCancel && (
                    <Button variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );
}

export default function LocationsPage() {
    const { user } = useAuth();
    const approved = user?.supplier?.status === 'approved';
    const queryClient = useQueryClient();
    const toast = useToast();
    const locations = useQuery({ queryKey: ['supplier-locations'], queryFn: api.supplierLocations });
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState(null);
    const [removing, setRemoving] = useState(null);

    const done = (message) => () => {
        queryClient.invalidateQueries({ queryKey: ['supplier-locations'] });
        queryClient.invalidateQueries({ queryKey: ['summary'] });
        toast(message);
        setAdding(false);
        setEditing(null);
        setRemoving(null);
    };
    const create = useMutation({ mutationFn: api.addLocation, onSuccess: done('Location added') });
    const update = useMutation({ mutationFn: ({ id, data }) => api.updateLocation(id, data), onSuccess: done('Location updated') });
    const remove = useMutation({ mutationFn: (id) => api.removeLocation(id), onSuccess: done('Location removed') });

    return (
        <>
            <PageHeader
                title="Locations"
                description="Shops, yards and warehouses you sell from. Buyers see the district and region on your offers."
                actions={approved && !adding && <Button onClick={() => setAdding(true)}>Add location</Button>}
            />
            {!approved && (
                <Notice tone="attention" className="mb-6" title="Available after approval">
                    You can add locations once an administrator approves your supplier account.
                </Notice>
            )}
            {adding && (
                <Panel title="New location" className="mb-6">
                    <LocationForm initial={EMPTY} submitLabel="Add location" onSubmit={(data) => create.mutate(data)} onCancel={() => setAdding(false)} busy={create.isPending} error={create.error} />
                </Panel>
            )}
            <QueryBoundary query={locations}>
                {({ supplier_locations: rows }) =>
                    rows.length === 0 ? (
                        !adding && <EmptyState title="No locations yet">Add the place you hold stock so you can record quantities.</EmptyState>
                    ) : (
                        <ul className="space-y-3">
                            {rows.map((location) =>
                                editing === location.id ? (
                                    <li key={location.id}>
                                        <Panel title={`Edit ${location.name}`}>
                                            <LocationForm
                                                initial={location}
                                                submitLabel="Save location"
                                                onSubmit={(data) => update.mutate({ id: location.id, data: { ...data, status: location.status } })}
                                                onCancel={() => setEditing(null)}
                                                busy={update.isPending}
                                                error={update.error}
                                            />
                                        </Panel>
                                    </li>
                                ) : (
                                    <li key={location.id} className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-panel)] border border-rule bg-paper px-4 py-3 sm:px-5">
                                        <div>
                                            <p className="font-semibold">
                                                {location.name}
                                                {location.is_primary && <span className="ml-2 text-sm font-normal text-muted">Main location</span>}
                                                {location.status === 'inactive' && <span className="ml-2 text-sm font-semibold text-warn">Inactive</span>}
                                            </p>
                                            <p className="text-sm text-muted">{[location.address, location.district, location.region].filter(Boolean).join(', ')}</p>
                                        </div>
                                        {approved && (
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    loading={update.isPending && update.variables?.id === location.id}
                                                    onClick={() =>
                                                        update.mutate({ id: location.id, data: { ...payload(location), status: location.status === 'active' ? 'inactive' : 'active' } })
                                                    }
                                                >
                                                    {location.status === 'active' ? 'Deactivate' : 'Activate'}
                                                </Button>
                                                <Button size="sm" variant="secondary" onClick={() => setEditing(location.id)}>
                                                    Edit
                                                </Button>
                                                <Button size="sm" variant="ghost" className="text-danger" onClick={() => setRemoving(location)}>
                                                    Remove
                                                </Button>
                                            </div>
                                        )}
                                    </li>
                                ),
                            )}
                        </ul>
                    )
                }
            </QueryBoundary>
            <ConfirmDialog
                open={Boolean(removing)}
                title={`Remove ${removing?.name ?? 'location'}?`}
                confirmLabel="Remove location"
                confirmVariant="danger"
                busy={remove.isPending}
                error={remove.error?.message}
                onCancel={() => setRemoving(null)}
                onConfirm={() => remove.mutate(removing.id)}
            >
                <p>Stock recorded at this location is deleted as well. To pause it instead, use Deactivate.</p>
            </ConfirmDialog>
        </>
    );
}
