import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button, ButtonLink, FormError, Notice, TextField } from '../../components/ui';
import { api } from '../../lib/api';
import AuthShell from './AuthShell';

export default function ResetPasswordPage() {
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
            <AuthShell title="Reset link incomplete">
                <p className="text-ink-soft">This page needs the full link from your reset email. Request a new link if it no longer works.</p>
                <ButtonLink to="/forgot-password" className="mt-4" block>
                    Request a new link
                </ButtonLink>
            </AuthShell>
        );
    }

    return (
        <AuthShell title="Choose a new password">
            {done ? (
                <>
                    <Notice tone="good" title="Password changed">
                        For your security, every device was signed out.
                    </Notice>
                    <ButtonLink to="/login" className="mt-4" block>
                        Sign in
                    </ButtonLink>
                </>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormError error={error} />
                    <TextField label="Email" type="email" autoComplete="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email} />
                    <TextField
                        label="New password"
                        type="password"
                        autoComplete="new-password"
                        required
                        hint="At least 8 characters, with letters and numbers."
                        value={form.password}
                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                        error={errors.password}
                    />
                    <TextField
                        label="Confirm new password"
                        type="password"
                        autoComplete="new-password"
                        required
                        value={form.password_confirmation}
                        onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                    />
                    <Button type="submit" block loading={busy} loadingText="Saving">
                        Save new password
                    </Button>
                    <p className="text-center text-sm">
                        <Link to="/forgot-password" className="font-semibold text-pal-700 hover:underline">
                            Request a new link
                        </Link>
                    </p>
                </form>
            )}
        </AuthShell>
    );
}
