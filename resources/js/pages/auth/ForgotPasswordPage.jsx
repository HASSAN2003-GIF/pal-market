import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, FormError, Notice, TextField } from '../../components/ui';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import AuthShell from './AuthShell';

export default function ForgotPasswordPage() {
    const t = useT();
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState(null);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const { message } = await api.forgotPassword(email);
            setSent(message);
        } catch (err) {
            setError(err.fieldErrors?.email ?? err.message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <AuthShell
            title={t('auth.forgotTitle')}
            intro={t('auth.forgotIntro')}
            footer={
                <Link to="/login" className="tap px-1 font-semibold text-pal-700 hover:underline">
                    {t('auth.backToSignIn')}
                </Link>
            }
        >
            {sent ? (
                <Notice tone="good" title={t('auth.checkEmail')}>
                    {sent} {t('auth.linkExpires')}
                </Notice>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormError error={error} />
                    <TextField label={t('auth.email')} type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
                    <Button type="submit" block loading={busy} loadingText={t('auth.sending')}>
                        {t('auth.sendLink')}
                    </Button>
                </form>
            )}
        </AuthShell>
    );
}
