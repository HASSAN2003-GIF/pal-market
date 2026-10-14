import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, FormError, Panel, SelectField, TextAreaField, TextField, cx, useToast } from '../../components/ui';
import { useI18n } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';

/**
 * Guided form for a product that is not in the catalog. Nothing reaches
 * buyers until an administrator approves it.
 */
export function ProposeProductForm({ initialName = '', onDone }) {
    const { t } = useI18n();
    const toast = useToast();
    const queryClient = useQueryClient();
    const filters = useQuery({ queryKey: ['catalog-filters'], queryFn: api.catalogFilters, staleTime: 300_000 });
    const [form, setForm] = useState({ name: initialName, name_sw: '', category_id: '', unit: '', description: '' });
    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });

    const send = useMutation({
        mutationFn: () =>
            api.proposeProduct({
                ...form,
                name_sw: form.name_sw || null,
                category_id: form.category_id ? Number(form.category_id) : null,
                description: form.description || null,
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['product-proposals'] });
            toast(t('proposals.sentToast'));
            onDone();
        },
    });
    const errors = send.error?.fieldErrors ?? {};

    return (
        <Panel title={t('proposals.dialogTitle')} className="mb-6">
            <p className="mb-4 text-sm text-ink-soft">{t('proposals.dialogIntro')}</p>
            <form
                className="grid gap-4 sm:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    send.mutate();
                }}
            >
                <div className="sm:col-span-2">
                    <FormError error={send.error && !Object.keys(errors).length ? send.error.message : null} />
                </div>
                <TextField label={t('proposals.name')} hint={t('proposals.nameHint')} required minLength={3} maxLength={120} value={form.name} onChange={set('name')} error={errors.name} />
                <TextField label={t('proposals.nameSw')} optional maxLength={120} value={form.name_sw} onChange={set('name_sw')} error={errors.name_sw} />
                <SelectField label={t('proposals.category')} optional value={form.category_id} onChange={set('category_id')} error={errors.category_id}>
                    <option value="">{t('proposals.chooseCategory')}</option>
                    {(filters.data?.categories ?? []).map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </SelectField>
                <TextField label={t('proposals.unit')} hint={t('proposals.unitHint')} required maxLength={50} value={form.unit} onChange={set('unit')} error={errors.unit} />
                <TextAreaField
                    className="sm:col-span-2"
                    label={t('proposals.details')}
                    hint={t('proposals.detailsHint')}
                    optional
                    rows={2}
                    maxLength={1000}
                    value={form.description}
                    onChange={set('description')}
                    error={errors.description}
                />
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    <Button type="submit" loading={send.isPending}>
                        {t('proposals.send')}
                    </Button>
                    <Button variant="secondary" onClick={onDone}>
                        {t('common.cancel')}
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

const TONES = { pending: 'text-warn', approved: 'text-ok', rejected: 'text-danger' };

export function MyProposals() {
    const { t } = useI18n();
    const proposals = useQuery({ queryKey: ['product-proposals'], queryFn: api.productProposals });
    const rows = proposals.data?.proposals ?? [];

    if (rows.length === 0) return null;

    return (
        <Panel title={t('proposals.yourTitle')} className="mb-6">
            <ul className="divide-y divide-rule">
                {rows.map((proposal) => (
                    <li key={proposal.id} className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2.5 text-sm">
                        <span className="min-w-0">
                            <span className="font-semibold">{proposal.name}</span>
                            <span className="ml-2 text-muted">{formatDate(proposal.created_at)}</span>
                            {proposal.status === 'rejected' && proposal.review_note && (
                                <span className="block text-ink-soft">{t('proposals.reason', { note: proposal.review_note })}</span>
                            )}
                            {proposal.status === 'approved' && <span className="block text-ink-soft">{t('proposals.approvedHint')}</span>}
                        </span>
                        <span className={cx('font-semibold', TONES[proposal.status])}>{t(`proposals.status_${proposal.status}`)}</span>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}
