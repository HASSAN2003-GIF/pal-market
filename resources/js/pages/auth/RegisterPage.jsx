import { useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, FormError, TextField, cx } from '../../components/ui';
import { homePathFor, useAuth } from '../../context/AuthContext';
import { useT } from '../../i18n';
import AuthShell, { GoogleButton, OrDivider } from './AuthShell';

const ROLES = [
    { value: 'buyer', title: 'auth.roleBuyerTitle', body: 'auth.roleBuyerBody' },
    { value: 'supplier', title: 'auth.roleSellerTitle', body: 'auth.roleSellerBody' },
];

export default function RegisterPage() {
    const t = useT();
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
            title={t('auth.registerTitle')}
            intro={t('auth.registerIntro')}
            footer={
                <>
                    {t('auth.haveAccount')}{' '}
                    <Link to="/login" className="font-semibold text-pal-700 hover:underline">
                        {t('layout.signIn')}
                    </Link>
                </>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                <fieldset>
                    <legend className="mb-2 text-sm font-semibold">{t('auth.roleQuestion')}</legend>
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
                                <span className="block font-bold">{t(role.title)}</span>
                                <span className="block text-sm text-muted">{t(role.body)}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>

                <FormError error={error} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField label={t('auth.firstName')} autoComplete="given-name" required value={form.first_name} onChange={set('first_name')} error={errors.first_name} />
                    <TextField label={t('auth.lastName')} autoComplete="family-name" required value={form.last_name} onChange={set('last_name')} error={errors.last_name} />
                </div>
                <TextField
                    label={t('auth.businessName')}
                    optional={!isSupplier}
                    autoComplete="organization"
                    hint={isSupplier ? t('auth.businessNameSellerHint') : t('auth.businessNameBuyerHint')}
                    value={form.business_name}
                    onChange={set('business_name')}
                    error={errors.business_name}
                    required={isSupplier}
                />
                {isSupplier && (
                    <TextField
                        label={t('auth.tin')}
                        optional
                        inputMode="numeric"
                        hint={t('auth.tinHint')}
                        value={form.tin_number}
                        onChange={set('tin_number')}
                        error={errors.tin_number}
                    />
                )}
                <TextField label={t('auth.email')} type="email" autoComplete="email" required value={form.email} onChange={set('email')} error={errors.email} />
                <TextField
                    label={t('auth.phone')}
                    optional
                    type="tel"
                    autoComplete="tel"
                    placeholder="+255 7xx xxx xxx"
                    hint={t('auth.phoneHint')}
                    value={form.phone}
                    onChange={set('phone')}
                    error={errors.phone}
                />
                <TextField
                    label={t('auth.password')}
                    type="password"
                    autoComplete="new-password"
                    required
                    hint={t('auth.passwordHint')}
                    value={form.password}
                    onChange={set('password')}
                    error={errors.password}
                />
                <TextField
                    label={t('auth.confirmPassword')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.password_confirmation}
                    onChange={set('password_confirmation')}
                />
                <Button type="submit" block size="lg" loading={busy} loadingText={t('auth.creating')}>
                    {isSupplier ? t('auth.createSeller') : t('auth.createBuyer')}
                </Button>
                {isSupplier && <p className="text-sm text-muted">{t('auth.sellerReview')}</p>}
            </form>
            <OrDivider />
            <GoogleButton role={form.role} />
        </AuthShell>
    );
}
