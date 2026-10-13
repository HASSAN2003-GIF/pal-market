import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, FormError, TextField } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';
import { useT } from '../../i18n';
import AuthShell, { GoogleButton, OrDivider } from './AuthShell';

export default function LoginPage() {
    const t = useT();
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams] = useSearchParams();
    const [form, setForm] = useState({ login: '', password: '' });
    // Errors handed over from the Google flow come as router state; a bare
    // ?error= (from the server redirect) only ever shows our own fixed text.
    const [error, setError] = useState(location.state?.error ?? (searchParams.get('error') ? t('auth.googleFailed') : null));
    const [fieldErrors, setFieldErrors] = useState({});
    const [busy, setBusy] = useState(false);

    if (user && !busy) return <Navigate to={homePathFor(user)} replace />;

    async function handleSubmit(event) {
        event.preventDefault();
        setBusy(true);
        setError(null);
        setFieldErrors({});
        try {
            const signedIn = await login(form);
            const from = location.state?.from;
            navigate(from && from !== '/login' ? from : homePathFor(signedIn), { replace: true });
        } catch (err) {
            setFieldErrors(err.fieldErrors ?? {});
            if (!err.fieldErrors?.login && !err.fieldErrors?.password) setError(err.message);
            setBusy(false);
        }
    }

    return (
        <AuthShell
            title={t('auth.signInTitle')}
            intro={t('auth.signInIntro')}
            footer={
                <>
                    {t('auth.newHere')}{' '}
                    <Link to="/register" className="tap px-1 font-semibold text-pal-700 hover:underline">
                        {t('layout.createAccount')}
                    </Link>
                </>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                <FormError error={error} />
                <TextField
                    label={t('auth.emailOrPhone')}
                    name="login"
                    autoComplete="username"
                    required
                    value={form.login}
                    onChange={(e) => setForm({ ...form, login: e.target.value })}
                    error={fieldErrors.login}
                />
                <TextField
                    label={t('auth.password')}
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.password}
                    onChange={(e) => setForm({ ...form, password: e.target.value })}
                    error={fieldErrors.password}
                />
                <div className="flex justify-end">
                    <Link to="/forgot-password" className="tap text-sm font-semibold text-pal-700 hover:underline">
                        {t('auth.forgot')}
                    </Link>
                </div>
                <Button type="submit" block size="lg" loading={busy} loadingText={t('auth.signingIn')}>
                    {t('layout.signIn')}
                </Button>
            </form>
            <OrDivider />
            <GoogleButton />
        </AuthShell>
    );
}
