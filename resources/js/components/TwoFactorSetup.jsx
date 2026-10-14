import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '../context/AuthContext';
import { useT } from '../i18n';
import { api } from '../lib/api';
import { Button, FormError, Notice, Panel, TextField, useToast } from './ui';

/**
 * Two-step sign-in with an authenticator app: scan a QR code, confirm one
 * code, keep the backup codes. Also lets people replace backup codes or turn
 * it off (password + current code).
 */
export default function TwoFactorSetup({ id = 'two-factor' }) {
    const t = useT();
    const toast = useToast();
    const queryClient = useQueryClient();
    const { refreshUser } = useAuth();
    const status = useQuery({ queryKey: ['two-factor'], queryFn: api.twoFactor.status });
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [setup, setSetup] = useState(null); // { secret, qr_svg }
    const [codes, setCodes] = useState(null);
    const [mode, setMode] = useState(null); // 'disable' | 'regenerate'

    const reset = () => {
        setPassword('');
        setCode('');
        setMode(null);
    };
    const refresh = async () => {
        await queryClient.invalidateQueries({ queryKey: ['two-factor'] });
        await refreshUser();
    };

    const start = useMutation({
        mutationFn: () => api.twoFactor.start(password),
        onSuccess: (data) => {
            setSetup(data);
            setPassword('');
        },
    });
    const confirm = useMutation({
        mutationFn: () => api.twoFactor.confirm(code),
        onSuccess: async (data) => {
            setCodes(data.recovery_codes);
            setSetup(null);
            setCode('');
            toast(t('twoFactor.onToast'));
            await refresh();
        },
    });
    const regenerate = useMutation({
        mutationFn: () => api.twoFactor.regenerate(password),
        onSuccess: (data) => {
            setCodes(data.recovery_codes);
            reset();
        },
    });
    const disable = useMutation({
        mutationFn: () => api.twoFactor.disable(password, code),
        onSuccess: async () => {
            reset();
            toast(t('twoFactor.offToast'));
            await refresh();
        },
    });

    const data = status.data;
    const errorOf = (m) => (m.error ? (Object.values(m.error.fieldErrors)[0] ?? m.error.message) : null);

    function downloadCodes() {
        const blob = new Blob([`PAL Market backup codes\n\n${codes.join('\n')}\n`], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'pal-market-backup-codes.txt';
        link.click();
        URL.revokeObjectURL(url);
    }

    return (
        <Panel title={t('twoFactor.title')}>
            <div id={id} className="space-y-4">
                <p className="text-sm text-ink-soft">{t('twoFactor.intro')}</p>

                {data?.required && !data.enabled && <Notice tone="attention" title={t('twoFactor.requiredTitle')}>{t('twoFactor.requiredBody')}</Notice>}

                {codes && (
                    <div className="rounded-[var(--radius-control)] border border-pal-300 bg-pal-50 p-4">
                        <p className="font-semibold">{t('twoFactor.codesTitle')}</p>
                        <p className="mt-1 text-sm text-ink-soft">{t('twoFactor.codesBody')}</p>
                        <ul className="tabular mt-3 grid grid-cols-2 gap-1.5 font-mono text-sm">
                            {codes.map((c) => (
                                <li key={c}>{c}</li>
                            ))}
                        </ul>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Button size="sm" variant="secondary" onClick={downloadCodes}>
                                {t('twoFactor.download')}
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setCodes(null)}>
                                {t('twoFactor.savedThem')}
                            </Button>
                        </div>
                    </div>
                )}

                {data && !data.enabled && !setup && (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            start.mutate();
                        }}
                    >
                        <TextField
                            label={t('twoFactor.passwordToStart')}
                            type="password"
                            autoComplete="current-password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            error={errorOf(start)}
                        />
                        <Button type="submit" loading={start.isPending}>
                            {t('twoFactor.turnOn')}
                        </Button>
                    </form>
                )}

                {setup && (
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            confirm.mutate();
                        }}
                    >
                        <ol className="list-decimal space-y-1 pl-5 text-sm">
                            <li>{t('twoFactor.step1')}</li>
                            <li>{t('twoFactor.step2')}</li>
                            <li>{t('twoFactor.step3')}</li>
                        </ol>
                        <img
                            src={`data:image/svg+xml;base64,${btoa(setup.qr_svg)}`}
                            alt={t('twoFactor.qrAlt')}
                            width="220"
                            height="220"
                            className="rounded-[var(--radius-control)] border border-rule bg-white p-2"
                        />
                        <p className="text-sm">
                            {t('twoFactor.manualKey')} <code className="font-mono break-all select-all">{setup.secret}</code>
                        </p>
                        <TextField
                            label={t('twoFactor.code')}
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            pattern="[0-9 ]*"
                            maxLength={7}
                            required
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            error={errorOf(confirm)}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" loading={confirm.isPending}>
                                {t('twoFactor.confirm')}
                            </Button>
                            <Button variant="secondary" onClick={() => setSetup(null)}>
                                {t('common.cancel')}
                            </Button>
                        </div>
                    </form>
                )}

                {data?.enabled && (
                    <>
                        <p className="font-semibold text-ok">
                            {t('twoFactor.isOn')} <span className="font-normal text-muted">· {t('twoFactor.codesLeft', { count: data.recovery_codes_left })}</span>
                        </p>
                        {!mode && (
                            <div className="flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" onClick={() => setMode('regenerate')}>
                                    {t('twoFactor.newCodes')}
                                </Button>
                                <Button size="sm" variant="ghost" className="text-danger" onClick={() => setMode('disable')}>
                                    {t('twoFactor.turnOff')}
                                </Button>
                            </div>
                        )}
                        {mode && (
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    (mode === 'disable' ? disable : regenerate).mutate();
                                }}
                            >
                                {mode === 'disable' && data.required && <Notice tone="attention" title={t('twoFactor.offWarning')} />}
                                <FormError error={errorOf(mode === 'disable' ? disable : regenerate)} />
                                <TextField label={t('auth.password')} type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
                                {mode === 'disable' && (
                                    <TextField
                                        label={t('twoFactor.code')}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={7}
                                        required
                                        value={code}
                                        onChange={(e) => setCode(e.target.value)}
                                    />
                                )}
                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" variant={mode === 'disable' ? 'danger' : 'primary'} loading={disable.isPending || regenerate.isPending}>
                                        {mode === 'disable' ? t('twoFactor.turnOff') : t('twoFactor.newCodes')}
                                    </Button>
                                    <Button variant="secondary" onClick={reset}>
                                        {t('common.cancel')}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </>
                )}
            </div>
        </Panel>
    );
}
