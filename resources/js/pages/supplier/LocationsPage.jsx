import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, ConfirmDialog, EmptyState, FormError, Notice, PageHeader, Panel, QueryBoundary, SelectField, TextField, useToast } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { REGION_NAMES, regionLabel } from '../../location/regions';

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
    const { t, locale } = useI18n();
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
            <TextField label={t('supplier.shopName')} placeholder={t('supplier.shopNamePlaceholder')} required value={form.name} onChange={set('name')} error={errors.name} />
            <TextField label={t('auth.phone')} optional type="tel" value={form.phone ?? ''} onChange={set('phone')} error={errors.phone} />
            <TextField className="sm:col-span-2" label={t('supplier.address')} required value={form.address} onChange={set('address')} error={errors.address} />
            <SelectField label={t('supplier.region')} required value={form.region} onChange={set('region')} error={errors.region}>
                <option value="">{t('buyer.chooseRegion')}</option>
                {REGION_NAMES.map((region) => (
                    <option key={region} value={region}>
                        {regionLabel(region, locale)}
                    </option>
                ))}
            </SelectField>
            <TextField label={t('supplier.district')} optional value={form.district ?? ''} onChange={set('district')} error={errors.district} />
            <label className="flex items-center gap-2.5 sm:col-span-2">
                <input
                    type="checkbox"
                    className="h-4 w-4 accent-[var(--color-action)]"
                    checked={Boolean(form.is_primary)}
                    onChange={(e) => setForm({ ...form, is_primary: e.target.checked })}
                />
                <span className="font-semibold">{t('supplier.mainShop')}</span>
            </label>
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit" loading={busy}>
                    {submitLabel}
                </Button>
                {onCancel && (
                    <Button variant="secondary" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                )}
            </div>
        </form>
    );
}

export default function LocationsPage() {
    const { t, locale } = useI18n();
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
    const create = useMutation({ mutationFn: api.addLocation, onSuccess: done(t('supplier.shopAddedToast')) });
    const update = useMutation({ mutationFn: ({ id, data }) => api.updateLocation(id, data), onSuccess: done(t('supplier.shopUpdatedToast')) });
    const remove = useMutation({ mutationFn: (id) => api.removeLocation(id), onSuccess: done(t('supplier.shopRemovedToast')) });

    return (
        <>
            <PageHeader
                title={t('nav.locations')}
                description={t('supplier.locationsDescription')}
                actions={approved && !adding && <Button onClick={() => setAdding(true)}>{t('supplier.addShop')}</Button>}
            />
            {!approved && (
                <Notice tone="attention" className="mb-6" title={t('supplier.afterApprovalTitle')}>
                    {t('supplier.locationsAfterApproval')}
                </Notice>
            )}
            {adding && (
                <Panel title={t('supplier.newShop')} className="mb-6">
                    <LocationForm
                        initial={EMPTY}
                        submitLabel={t('supplier.addShop')}
                        onSubmit={(data) => create.mutate(data)}
                        onCancel={() => setAdding(false)}
                        busy={create.isPending}
                        error={create.error}
                    />
                </Panel>
            )}
            <QueryBoundary query={locations}>
                {({ supplier_locations: rows }) =>
                    rows.length === 0 ? (
                        !adding && <EmptyState title={t('supplier.noShopsTitle')}>{t('supplier.noShopsBody')}</EmptyState>
                    ) : (
                        <ul className="space-y-3">
                            {rows.map((location) =>
                                editing === location.id ? (
                                    <li key={location.id}>
                                        <Panel title={t('supplier.editShop', { name: location.name })}>
                                            <LocationForm
                                                initial={location}
                                                submitLabel={t('common.saveChanges')}
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
                                                {location.is_primary && <span className="ml-2 text-sm font-normal text-muted">{t('supplier.mainShop')}</span>}
                                                {location.status === 'inactive' && <span className="ml-2 text-sm font-semibold text-warn">{t('supplier.inactive')}</span>}
                                            </p>
                                            <p className="text-sm text-muted">
                                                {[location.address, location.district, regionLabel(location.region, locale)].filter(Boolean).join(', ')}
                                            </p>
                                        </div>
                                        {approved && (
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    loading={update.isPending && update.variables?.id === location.id}
                                                    onClick={() =>
                                                        update.mutate({ id: location.id, data: { ...payload(location), status: location.status === 'active' ? 'inactive' : 'active' } })
                                                    }
                                                >
                                                    {location.status === 'active' ? t('supplier.pause') : t('supplier.resume')}
                                                </Button>
                                                <Button size="sm" variant="secondary" onClick={() => setEditing(location.id)}>
                                                    {t('common.edit')}
                                                </Button>
                                                <Button size="sm" variant="ghost" className="text-danger" onClick={() => setRemoving(location)}>
                                                    {t('common.remove')}
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
                title={t('supplier.removeShopTitle', { name: removing?.name ?? '' })}
                confirmLabel={t('supplier.removeShopConfirm')}
                confirmVariant="danger"
                busy={remove.isPending}
                error={remove.error?.message}
                onCancel={() => setRemoving(null)}
                onConfirm={() => remove.mutate(removing.id)}
            >
                <p>{t('supplier.removeShopBody')}</p>
            </ConfirmDialog>
        </>
    );
}
