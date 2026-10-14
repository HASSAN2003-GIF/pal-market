import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import ProductPicker from '../../components/ProductPicker';
import { Button, EmptyState, FormError, PageHeader, Pagination, Panel, QueryBoundary, SelectField, TextAreaField, TextField, cx, useToast } from '../../components/ui';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';

const TABS = ['pending', 'approved', 'rejected'];

export default function AdminProposalsPage() {
    const t = useT();
    const [params, update] = useListParams();
    const status = TABS.includes(params.status) ? params.status : 'pending';
    const query = { status, page: params.page };
    const proposals = useQuery({
        queryKey: ['admin', 'proposals', query],
        queryFn: () => api.admin.proposals(query),
        placeholderData: keepPreviousData,
    });
    const taxonomy = useQuery({ queryKey: ['admin', 'catalog', 'taxonomy'], queryFn: api.admin.taxonomy });

    return (
        <>
            <PageHeader title={t('proposals.adminTitle')} description={t('proposals.adminDescription')} />
            <div className="mb-4 flex flex-wrap gap-2" role="tablist">
                {TABS.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        role="tab"
                        aria-selected={status === tab}
                        onClick={() => update({ status: tab === 'pending' ? '' : tab })}
                        className={cx(
                            'tap min-h-10 rounded-full border px-4 text-sm font-semibold',
                            status === tab ? 'border-ink bg-inverse text-on-inverse' : 'border-rule bg-paper text-ink-soft hover:border-ink',
                        )}
                    >
                        {t(`proposals.tab_${tab}`)}
                        {tab === 'pending' && proposals.data?.pending_count > 0 && <span className="ml-1.5">({proposals.data.pending_count})</span>}
                    </button>
                ))}
            </div>
            <QueryBoundary query={proposals}>
                {({ data: rows, meta }) =>
                    rows.length === 0 ? (
                        <EmptyState title={t('proposals.emptyTitle')}>{t('proposals.emptyBody')}</EmptyState>
                    ) : (
                        <>
                            <ul className="space-y-4">
                                {rows.map((proposal) => (
                                    <ProposalCard key={proposal.id} proposal={proposal} taxonomy={taxonomy.data} />
                                ))}
                            </ul>
                            <Pagination meta={meta} onPage={(page) => update({ page })} />
                        </>
                    )
                }
            </QueryBoundary>
        </>
    );
}

function ProposalCard({ proposal, taxonomy }) {
    const t = useT();
    const toast = useToast();
    const queryClient = useQueryClient();
    const [mode, setMode] = useState(null); // 'new' | 'link' | 'decline'
    const [form, setForm] = useState({
        name: proposal.name,
        name_sw: proposal.name_sw ?? '',
        category_id: proposal.category_id ? String(proposal.category_id) : '',
        unit: proposal.unit,
        description: proposal.description ?? '',
    });
    const [linked, setLinked] = useState(null);
    const [note, setNote] = useState('');
    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });

    const done = (message) => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'proposals'] });
        queryClient.invalidateQueries({ queryKey: ['admin', 'catalog'] });
        queryClient.invalidateQueries({ queryKey: ['products'] });
        toast(message);
    };
    const approve = useMutation({
        mutationFn: () =>
            mode === 'link'
                ? api.admin.approveProposal(proposal.id, { product_id: linked.id })
                : api.admin.approveProposal(proposal.id, {
                      ...form,
                      name_sw: form.name_sw || null,
                      category_id: Number(form.category_id),
                      description: form.description || null,
                  }),
        onSuccess: () => done(t('proposals.approvedToast')),
    });
    const decline = useMutation({
        mutationFn: () => api.admin.rejectProposal(proposal.id, note),
        onSuccess: () => done(t('proposals.declinedToast')),
    });
    const errors = { ...(approve.error?.fieldErrors ?? {}), ...(decline.error?.fieldErrors ?? {}) };
    const pending = proposal.status === 'pending';

    return (
        <li>
            <Panel title={proposal.name}>
                <div className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    <p className="font-semibold">{t('proposals.from', { name: proposal.supplier?.business_name ?? '' })}</p>
                    <p className="text-muted">{t('proposals.asked', { date: formatDate(proposal.created_at) })}</p>
                    {proposal.name_sw && (
                        <div>
                            <span className="inline text-muted">{t('proposals.nameSw')}: </span>
                            <span className="inline">{proposal.name_sw}</span>
                        </div>
                    )}
                    <div>
                        <span className="inline text-muted">{t('proposals.unit')}: </span>
                        <span className="inline">{proposal.unit}</span>
                    </div>
                    {proposal.category && (
                        <div>
                            <span className="inline text-muted">{t('proposals.category')}: </span>
                            <span className="inline">{proposal.category.name}</span>
                        </div>
                    )}
                    {proposal.description && <p className="text-ink-soft sm:col-span-2">{proposal.description}</p>}
                    {proposal.status === 'approved' && proposal.product && (
                        <p className="font-semibold text-ok sm:col-span-2">{t('proposals.linkedTo', { name: proposal.product.name })}</p>
                    )}
                    {proposal.status === 'rejected' && <p className="text-danger sm:col-span-2">{t('proposals.reason', { note: proposal.review_note })}</p>}
                </div>

                {pending && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button size="sm" variant={mode === 'new' ? 'primary' : 'secondary'} onClick={() => setMode('new')}>
                            {t('proposals.approveNew')}
                        </Button>
                        <Button size="sm" variant={mode === 'link' ? 'primary' : 'secondary'} onClick={() => setMode('link')}>
                            {t('proposals.linkExisting')}
                        </Button>
                        <Button size="sm" variant="ghost" className="text-danger" onClick={() => setMode('decline')}>
                            {t('proposals.decline')}
                        </Button>
                    </div>
                )}

                {pending && mode === 'new' && taxonomy && (
                    <form
                        className="mt-4 grid gap-4 border-t border-rule pt-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            approve.mutate();
                        }}
                    >
                        <div className="sm:col-span-2">
                            <FormError error={approve.error && !Object.keys(errors).length ? approve.error.message : null} />
                        </div>
                        <TextField label={t('admin.productName')} required value={form.name} onChange={set('name')} error={errors.name} />
                        <TextField label={t('admin.nameSw')} optional value={form.name_sw} onChange={set('name_sw')} error={errors.name_sw} />
                        <SelectField label={t('search.category')} required value={form.category_id} onChange={set('category_id')} error={errors.category_id}>
                            <option value="">{t('admin.chooseCategory')}</option>
                            {taxonomy.categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectField>
                        <TextField label={t('admin.sellingUnit')} required value={form.unit} onChange={set('unit')} error={errors.unit} />
                        <TextAreaField className="sm:col-span-2" label={t('admin.description')} optional value={form.description} onChange={set('description')} />
                        <div className="sm:col-span-2">
                            <Button type="submit" loading={approve.isPending}>
                                {t('proposals.approve')}
                            </Button>
                        </div>
                    </form>
                )}

                {pending && mode === 'link' && (
                    <div className="mt-4 space-y-3 border-t border-rule pt-4">
                        <ProductPicker label={t('proposals.pickExisting')} onPick={setLinked} />
                        {linked && <p className="text-sm font-semibold">{linked.name}</p>}
                        <FormError error={approve.error?.message} />
                        <Button disabled={!linked} loading={approve.isPending} onClick={() => approve.mutate()}>
                            {t('proposals.approve')}
                        </Button>
                    </div>
                )}

                {pending && mode === 'decline' && (
                    <form
                        className="mt-4 space-y-3 border-t border-rule pt-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            decline.mutate();
                        }}
                    >
                        <TextAreaField label={t('proposals.declineReason')} required minLength={3} maxLength={500} rows={2} value={note} onChange={(e) => setNote(e.target.value)} error={errors.review_note} />
                        <FormError error={decline.error && !errors.review_note ? decline.error.message : null} />
                        <Button type="submit" variant="danger" loading={decline.isPending}>
                            {t('proposals.decline')}
                        </Button>
                    </form>
                )}
            </Panel>
        </li>
    );
}
