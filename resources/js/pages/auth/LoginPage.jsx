import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, FormError, TextField } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';
import AuthShell, { GoogleButton, OrDivider } from './AuthShell';

export default function LoginPage() {
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams] = useSearchParams();
    const [form, setForm] = useState({ login: '', password: '' });
    const [error, setError] = useState(searchParams.get('error'));
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
            title="Sign in"
            intro="Use the email address or phone number on your PAL Market account."
            footer={
                <>
                    New to PAL Market?{' '}
                    <Link to="/register" className="font-semibold text-pal-700 hover:underline">
                        Create an account
                    </Link>
                </>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                <FormError error={error} />
                <TextField
                    label="Email or phone number"
                    name="login"
                    autoComplete="username"
                    required
                    value={form.login}
                    onChange={(e) => setForm({ ...form, login: e.target.value })}
                    error={fieldErrors.login}
                />
                <TextField
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.password}
                    onChange={(e) => setForm({ ...form, password: e.target.value })}
                    error={fieldErrors.password}
                />
                <div className="flex justify-end">
                    <Link to="/forgot-password" className="text-sm font-semibold text-pal-700 hover:underline">
                        Forgot your password?
                    </Link>
                </div>
                <Button type="submit" block size="lg" loading={busy} loadingText="Signing in">
                    Sign in
                </Button>
            </form>
            <OrDivider />
            <GoogleButton />
        </AuthShell>
    );
}
