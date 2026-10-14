import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, FormError, TextField } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';
import { useT } from '../../i18n';
import AuthShell, { GoogleButton, OrDivider } from './AuthShell';

export default function LoginPage() {
    const t = useT();
    const { user, login, verifyTwoFactor } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams] = useSearchParams();
    const [form, setForm] = useState({ login: '', password: '' });
    // Errors handed over from the Google flow come as router state; a bare
    // ?error= (from the server redirect) only ever shows our own fixed text.
    const [error, setError] = useState(location.state?.error ?? (searchParams.get('error') ? t('auth.googleFailed') : null));
    const [fieldErrors, setFieldErrors] = useState({});
    const [busy, setBusy] = useState(false);
    // Second step: set when the password (or Google) was accepted and a code is needed.
    const [challenge, setChallenge] = useState(location.state?.twoFactorChallenge ?? null);
    const [code, setCode] = useState('');
    const [useBackup, setUseBackup] = useState(false);

    if (user && !busy) return <Navigate to={homePathFor(user)} replace />;

    const finish = (signedIn) => {
        const from = location.state?.from;
        navigate(from && from !== '/login' ? from : homePathFor(signedIn), { replace: true });
    };

    const fail = (err) => {
        setFieldErrors(err.fieldErrors ?? {});
        if (!Object.keys(err.fieldErrors ?? {}).length) setError(err.message);
        setBusy(false);
    };

    async function handleSubmit(event) {
        event.preventDefault();
        setBusy(true);
        setError(null);
        setFieldErrors({});
        try {
            const result = await login(form);
            if (result?.twoFactorChallenge) {
                setChallenge(result.twoFactorChallenge);
                setBusy(false);
                return;
            }
            finish(result);
        } catch (err) {
            fail(err);
        }
    }

    async function handleCode(event) {
        event.preventDefault();
        setBusy(true);
        setError(null);
        setFieldErrors({});
        try {
            finish(await verifyTwoFactor(challenge, useBackup ? { recovery_code: code } : { code }));
        } catch (err) {
            fail(err);
        }
    }

    if (challenge) {
        return (
            <AuthShell title={t('twoFactor.loginTitle')} intro={t('twoFactor.loginIntro')}>
                <form onSubmit={handleCode} className="space-y-4" noValidate>
                    <FormError error={error} />
                    <TextField
                        label={useBackup ? t('twoFactor.backupCode') : t('twoFactor.code')}
                        name="code"
                        inputMode={useBackup ? 'text' : 'numeric'}
                        autoComplete="one-time-code"
                        autoFocus
                        required
                        maxLength={useBackup ? 20 : 7}
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        error={fieldErrors.code ?? fieldErrors.recovery_code}
                    />
                    <Button type="submit" block size="lg" loading={busy} loadingText={t('auth.signingIn')}>
                        {t('twoFactor.verify')}
                    </Button>
                    <div className="flex flex-wrap justify-between gap-2 text-sm">
                        <button
                            type="button"
                            className="tap font-semibold text-pal-700 hover:underline"
                            onClick={() => {
                                setUseBackup(!useBackup);
                                setCode('');
                                setFieldErrors({});
                            }}
                        >
                            {useBackup ? t('twoFactor.useApp') : t('twoFactor.useBackup')}
                        </button>
                        <button
                            type="button"
                            className="tap font-semibold text-ink-soft hover:underline"
                            onClick={() => {
                                setChallenge(null);
                                setCode('');
                                setError(null);
                                setForm({ ...form, password: '' });
                            }}
                        >
                            {t('twoFactor.startOver')}
                        </button>
                    </div>
                </form>
            </AuthShell>
        );
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
