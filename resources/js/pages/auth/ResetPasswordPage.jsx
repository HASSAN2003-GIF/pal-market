import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button, ButtonLink, FormError, Notice, TextField } from '../../components/ui';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import AuthShell from './AuthShell';

export default function ResetPasswordPage() {
    const t = useT();
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token');
    const [form, setForm] = useState({ email: searchParams.get('email') ?? '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [error, setError] = useState(null);
    const [done, setDone] = useState(false);
    const [busy, setBusy] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();
        setBusy(true);
        setErrors({});
        setError(null);
        try {
            await api.resetPassword({ ...form, token });
            setDone(true);
        } catch (err) {
            setErrors(err.fieldErrors ?? {});
            if (!Object.keys(err.fieldErrors ?? {}).length) setError(err.message);
        } finally {
            setBusy(false);
        }
    }

    if (!token) {
        return (
            <AuthShell title={t('auth.resetIncompleteTitle')}>
                <p className="text-ink-soft">{t('auth.resetIncompleteBody')}</p>
                <ButtonLink to="/forgot-password" className="mt-4" block>
                    {t('auth.requestNewLink')}
                </ButtonLink>
            </AuthShell>
        );
    }

    return (
        <AuthShell title={t('auth.resetTitle')}>
            {done ? (
                <>
                    <Notice tone="good" title={t('auth.passwordChanged')}>
                        {t('auth.allSignedOut')}
                    </Notice>
                    <ButtonLink to="/login" className="mt-4" block>
                        {t('layout.signIn')}
                    </ButtonLink>
                </>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormError error={error} />
                    <TextField
                        label={t('auth.email')}
                        type="email"
                        autoComplete="email"
                        required
                        value={form.email}
                        onChange={(e) => setForm({ ...form, email: e.target.value })}
                        error={errors.email}
                    />
                    <TextField
                        label={t('auth.newPassword')}
                        type="password"
                        autoComplete="new-password"
                        required
                        hint={t('auth.passwordHint')}
                        value={form.password}
                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                        error={errors.password}
                    />
                    <TextField
                        label={t('auth.confirmNewPassword')}
                        type="password"
                        autoComplete="new-password"
                        required
                        value={form.password_confirmation}
                        onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                    />
                    <Button type="submit" block loading={busy} loadingText={t('auth.saving')}>
                        {t('auth.saveNewPassword')}
                    </Button>
                    <p className="text-center text-sm">
                        <Link to="/forgot-password" className="font-semibold text-pal-700 hover:underline">
                            {t('auth.requestNewLink')}
                        </Link>
                    </p>
                </form>
            )}
        </AuthShell>
    );
}
