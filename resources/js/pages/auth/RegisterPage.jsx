import { useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, FormError, TextField, cx } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';
import AuthShell, { GoogleButton, OrDivider } from './AuthShell';

const ROLES = [
    { value: 'buyer', title: 'I buy materials', body: 'Contractors, builders and project owners requesting quotes.' },
    { value: 'supplier', title: 'I supply materials', body: 'Hardware stores and distributors quoting on requests.' },
];

export default function RegisterPage() {
    const { user, register } = useAuth();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const [form, setForm] = useState({
        role: searchParams.get('role') === 'supplier' ? 'supplier' : 'buyer',
        first_name: '',
        last_name: '',
        business_name: '',
        tin_number: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });
    const [errors, setErrors] = useState({});
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    if (user && !busy) return <Navigate to={homePathFor(user)} replace />;

    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });

    async function handleSubmit(event) {
        event.preventDefault();
        setBusy(true);
        setErrors({});
        setError(null);
        try {
            const created = await register({ ...form, phone: form.phone || null, tin_number: form.tin_number || null });
            navigate(homePathFor(created), { replace: true });
        } catch (err) {
            setErrors(err.fieldErrors ?? {});
            if (!Object.keys(err.fieldErrors ?? {}).length) setError(err.message);
            setBusy(false);
        }
    }

    const isSupplier = form.role === 'supplier';

    return (
        <AuthShell
            title="Create your account"
            intro="Free for buyers and suppliers."
            footer={
                <>
                    Already registered?{' '}
                    <Link to="/login" className="font-semibold text-pal-700 hover:underline">
                        Sign in
                    </Link>
                </>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                <fieldset>
                    <legend className="mb-2 text-sm font-semibold">How will you use PAL Market?</legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {ROLES.map((role) => (
                            <label
                                key={role.value}
                                className={cx(
                                    'cursor-pointer rounded-[var(--radius-control)] border px-3 py-2.5 has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-pal-500',
                                    form.role === role.value ? 'border-pal-600 bg-pal-50 ring-1 ring-pal-600' : 'border-rule hover:border-ink-soft',
                                )}
                            >
                                <input type="radio" name="role" value={role.value} checked={form.role === role.value} onChange={set('role')} className="sr-only" />
                                <span className="block font-semibold">{role.title}</span>
                                <span className="block text-sm text-muted">{role.body}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>

                <FormError error={error} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField label="First name" autoComplete="given-name" required value={form.first_name} onChange={set('first_name')} error={errors.first_name} />
                    <TextField label="Last name" autoComplete="family-name" required value={form.last_name} onChange={set('last_name')} error={errors.last_name} />
                </div>
                <TextField
                    label="Business name"
                    optional={!isSupplier}
                    autoComplete="organization"
                    hint={isSupplier ? 'As registered. Buyers see this on your quotations.' : 'Leave blank to use your own name.'}
                    value={form.business_name}
                    onChange={set('business_name')}
                    error={errors.business_name}
                    required={isSupplier}
                />
                {isSupplier && (
                    <TextField
                        label="TIN"
                        optional
                        inputMode="numeric"
                        hint="Your Tax Identification Number speeds up approval. You can add it later."
                        value={form.tin_number}
                        onChange={set('tin_number')}
                        error={errors.tin_number}
                    />
                )}
                <TextField label="Email" type="email" autoComplete="email" required value={form.email} onChange={set('email')} error={errors.email} />
                <TextField
                    label="Phone number"
                    optional
                    type="tel"
                    autoComplete="tel"
                    placeholder="+255 7xx xxx xxx"
                    hint="You can also sign in with this number."
                    value={form.phone}
                    onChange={set('phone')}
                    error={errors.phone}
                />
                <TextField
                    label="Password"
                    type="password"
                    autoComplete="new-password"
                    required
                    hint="At least 8 characters, with letters and numbers."
                    value={form.password}
                    onChange={set('password')}
                    error={errors.password}
                />
                <TextField
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.password_confirmation}
                    onChange={set('password_confirmation')}
                />
                <Button type="submit" block size="lg" loading={busy} loadingText="Creating account">
                    {isSupplier ? 'Create supplier account' : 'Create buyer account'}
                </Button>
                {isSupplier && <p className="text-sm text-muted">Supplier accounts are reviewed by our team before you can send quotations.</p>}
            </form>
            <OrDivider />
            <GoogleButton role={form.role} />
        </AuthShell>
    );
}
