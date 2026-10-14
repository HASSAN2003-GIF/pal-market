import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, DocNumber, FormError, Notice, Panel, TextField, useToast } from '../../components/ui';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate } from '../../lib/format';

/**
 * Download or print the purchase order (EN/SW). The PDF is made on the
 * server from the order as it was confirmed; it needs the sign-in token, so
 * it is fetched here and handed to the browser as a file.
 */
export default function OrderDocumentPanel({ order }) {
    const t = useT();
    const doc = useQuery({
        queryKey: ['order-document', String(order.id), order.status],
        queryFn: () => api.orderDocument(order.id),
    });
    const [busy, setBusy] = useState(null);
    const [error, setError] = useState(null);

    async function fetchPdf(lang, mode) {
        setBusy(`${lang}-${mode}`);
        setError(null);
        // Open the tab during the click so pop-up blockers allow it.
        const tab = mode === 'open' ? window.open('', '_blank') : null;
        try {
            const blob = await api.orderDocumentPdf(order.id, { lang, disposition: mode === 'open' ? 'inline' : 'attachment' });
            const url = URL.createObjectURL(blob);
            if (mode === 'open') {
                if (tab) tab.location.href = url;
                else setError(t('orderDoc.popupBlocked'));
            } else {
                const link = document.createElement('a');
                link.href = url;
                link.download = `${order.order_number}-${lang}.pdf`;
                document.body.appendChild(link);
                link.click();
                link.remove();
            }
            setTimeout(() => URL.revokeObjectURL(url), 60_000);
            doc.refetch();
        } catch (e) {
            tab?.close();
            setError(e.message);
        } finally {
            setBusy(null);
        }
    }

    const data = doc.data;

    return (
        <Panel title={t('orderDoc.title')}>
            <p className="text-sm text-ink-soft">{t('orderDoc.intro')}</p>

            {data && !data.available && <Notice tone="info" className="mt-3" title={t('orderDoc.notYet')} />}

            {data?.available && (
                <>
                    {data.current && (
                        <p className="mt-3 text-sm">
                            <span className="text-muted">{t('orderDoc.reference')}: </span>
                            <DocNumber className="text-sm">{data.current.reference}</DocNumber>
                            <span className="text-muted"> · {t('orderDoc.issued', { date: formatDate(data.current.issued_at) })}</span>
                        </p>
                    )}
                    {order.status === 'cancelled' && <p className="mt-2 text-sm font-semibold text-danger">{t('orderDoc.cancelledNote')}</p>}
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Button size="sm" loading={busy === 'en-download'} loadingText={t('orderDoc.preparing')} onClick={() => fetchPdf('en', 'download')}>
                            {t('orderDoc.downloadEn')}
                        </Button>
                        <Button size="sm" loading={busy === 'sw-download'} loadingText={t('orderDoc.preparing')} onClick={() => fetchPdf('sw', 'download')}>
                            {t('orderDoc.downloadSw')}
                        </Button>
                        <Button size="sm" variant="secondary" loading={busy?.endsWith('-open')} onClick={() => fetchPdf(document.documentElement.lang === 'sw' ? 'sw' : 'en', 'open')}>
                            {t('orderDoc.open')}
                        </Button>
                    </div>
                    {error && <p className="mt-2 text-sm text-danger" role="alert">{error}</p>}
                    <p className="mt-3 text-xs text-muted">{t('orderDoc.notPayment')}</p>

                    {data.can_edit_signatories && <SignatoryNames order={order} current={data.current?.signatories} />}

                    {data.versions?.length > 1 && (
                        <details className="mt-4 border-t border-rule pt-3 text-sm">
                            <summary className="tap cursor-pointer font-semibold">{t('orderDoc.versions')}</summary>
                            <ul className="mt-2 space-y-1">
                                {data.versions.map((v) => (
                                    <li key={v.version} className="text-ink-soft">
                                        {t('orderDoc.version', { version: v.version })} · {formatDate(v.issued_at)} · {t(`orderDoc.reason_${v.reason}`)}
                                    </li>
                                ))}
                            </ul>
                        </details>
                    )}
                </>
            )}
        </Panel>
    );
}

function SignatoryNames({ order, current }) {
    const t = useT();
    const toast = useToast();
    const queryClient = useQueryClient();
    const [form, setForm] = useState({
        engineer_name: current?.engineer_name ?? '',
        engineer_designation: current?.engineer_designation ?? '',
        supervisor_name: current?.supervisor_name ?? '',
        supervisor_designation: current?.supervisor_designation ?? '',
    });
    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });
    const save = useMutation({
        mutationFn: () => api.updateOrderSignatories(order.id, form),
        onSuccess: ({ version }) => {
            queryClient.invalidateQueries({ queryKey: ['order-document', String(order.id)] });
            toast(t('orderDoc.savedToast', { version }));
        },
    });
    const errors = save.error?.fieldErrors ?? {};

    return (
        <details className="mt-4 border-t border-rule pt-3">
            <summary className="tap cursor-pointer text-sm font-semibold">{t('orderDoc.namesTitle')}</summary>
            <form
                className="mt-3 space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    save.mutate();
                }}
            >
                <p className="text-xs text-muted">{t('orderDoc.namesHint')}</p>
                <FormError error={save.error && !Object.keys(errors).length ? save.error.message : null} />
                <TextField label={t('orderDoc.engineerName')} optional maxLength={120} value={form.engineer_name} onChange={set('engineer_name')} error={errors.engineer_name} />
                <TextField label={t('orderDoc.engineerDesignation')} optional maxLength={120} value={form.engineer_designation} onChange={set('engineer_designation')} error={errors.engineer_designation} />
                <TextField label={t('orderDoc.supervisorName')} optional maxLength={120} value={form.supervisor_name} onChange={set('supervisor_name')} error={errors.supervisor_name} />
                <TextField label={t('orderDoc.supervisorDesignation')} optional maxLength={120} value={form.supervisor_designation} onChange={set('supervisor_designation')} error={errors.supervisor_designation} />
                <Button type="submit" size="sm" variant="secondary" loading={save.isPending}>
                    {t('orderDoc.saveNames')}
                </Button>
            </form>
        </details>
    );
}
