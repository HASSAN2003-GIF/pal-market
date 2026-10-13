import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, FormError, Notice, TextField } from '../../components/ui';
import { api } from '../../lib/api';
import AuthShell from './AuthShell';

export default function ForgotPasswordPage() {
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
            title="Reset your password"
            intro="Enter your account email and we will send you a link to choose a new password."
            footer={
                <Link to="/login" className="font-semibold text-pal-700 hover:underline">
                    Back to sign in
                </Link>
            }
        >
            {sent ? (
                <Notice tone="good" title="Check your email">
                    {sent} The link expires in 60 minutes.
                </Notice>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormError error={error} />
                    <TextField label="Email" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
                    <Button type="submit" block loading={busy} loadingText="Sending link">
                        Send reset link
                    </Button>
                </form>
            )}
        </AuthShell>
    );
}
