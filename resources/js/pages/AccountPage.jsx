import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { AreaButton, LanguageSwitch } from '../components/PreferenceControls';
import { Button, FormError, PageHeader, Panel, StatusBadge, TextAreaField, TextField, cx, useToast } from '../components/ui';
import { useAuth } from '../context/AuthContext';
import { useT } from '../i18n';
import { api } from '../lib/api';
import { THEMES, useTheme } from '../theme/ThemeContext';

export default function AccountPage() {
    const t = useT();
    const { user } = useAuth();

    return (
        <>
            <PageHeader title={t('account.title')} description={user.email} />
            <div className="grid gap-6 lg:grid-cols-2">
                <ProfileForm />
                <div className="space-y-6">
                    <PreferencesPanel />
                    <PasswordForm />
                </div>
            </div>
        </>
    );
}

function PreferencesPanel() {
    const t = useT();
    const { choice, setChoice } = useTheme();
    const themeLabel = { light: 'layout.themeLight', dark: 'layout.themeDark', system: 'layout.themeSystem' };

    return (
        <Panel title={t('account.preferences')}>
            <dl className="space-y-5">
                <div>
                    <dt className="mb-1.5 text-sm font-semibold">{t('layout.language')}</dt>
                    <dd>
                        <LanguageSwitch />
                        <p className="mt-1.5 text-sm text-muted">{t('account.languageHint')}</p>
                    </dd>
                </div>
                <div>
                    <dt className="mb-1.5 text-sm font-semibold">{t('layout.theme')}</dt>
                    <dd role="radiogroup" aria-label={t('layout.theme')} className="flex flex-wrap gap-2">
                        {THEMES.map((value) => (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={choice === value}
                                onClick={() => setChoice(value)}
                                className={cx(
                                    'min-h-11 rounded-[var(--radius-control)] border px-3 py-2 text-sm font-semibold lg:min-h-10',
                                    choice === value ? 'border-pal-600 bg-pal-50 text-pal-700' : 'border-rule hover:border-ink-soft',
                                )}
                            >
                                {t(themeLabel[value])}
                            </button>
                        ))}
                    </dd>
                    <p className="mt-1.5 text-sm text-muted">{t('account.themeHint')}</p>
                </div>
                <div>
                    <dt className="mb-1.5 text-sm font-semibold">{t('location.yourArea')}</dt>
                    <dd>
                        <AreaButton className="border border-rule" />
                        <p className="mt-1.5 text-sm text-muted">{t('account.areaHint')}</p>
                    </dd>
                </div>
            </dl>
        </Panel>
    );
}

function ProfileForm() {
    const t = useT();
    const { user, setUser } = useAuth();
    const toast = useToast();
    const business = user.supplier ?? user.buyer_profile;
    const [form, setForm] = useState({
        name: user.name,
        phone: user.phone ?? '',
        business_name: business?.business_name ?? '',
        business_type: user.buyer_profile?.business_type ?? '',
        tin_number: business?.tin_number ?? '',
        description: business?.description ?? '',
    });
    const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });

    const save = useMutation({
        mutationFn: () => {
            const data = { name: form.name, phone: form.phone || null };
            if (business) {
                Object.assign(data, { business_name: form.business_name, description: form.description || null, tin_number: form.tin_number || null });
                if (user.buyer_profile) data.business_type = form.business_type || null;
            }
            return api.updateProfile(data);
        },
        onSuccess: ({ user: updated }) => {
            setUser(updated);
            toast(t('account.profileSaved'));
        },
    });
    const errors = save.error?.fieldErrors ?? {};
    const tinLocked = user.supplier?.status === 'approved';

    return (
        <Panel title={t('account.profile')} status={user.supplier && <StatusBadge kind="supplier" status={user.supplier.status} />}>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <FormError error={save.error && !Object.keys(errors).length ? save.error.message : null} />
                <TextField label={t('account.yourName')} required value={form.name} onChange={set('name')} error={errors.name} />
                <TextField
                    label={t('auth.phone')}
                    optional
                    type="tel"
                    value={form.phone}
                    onChange={set('phone')}
                    error={errors.phone}
                    hint={t('auth.phoneHint')}
                />
                {business && (
                    <>
                        <TextField label={t('auth.businessName')} required value={form.business_name} onChange={set('business_name')} error={errors.business_name} />
                        {user.buyer_profile && (
                            <TextField
                                label={t('account.businessType')}
                                optional
                                placeholder={t('account.businessTypePlaceholder')}
                                value={form.business_type}
                                onChange={set('business_type')}
                            />
                        )}
                        <TextField
                            label={t('auth.tin')}
                            optional
                            value={form.tin_number}
                            onChange={set('tin_number')}
                            error={errors.tin_number}
                            disabled={tinLocked}
                            hint={tinLocked ? t('account.tinLocked') : undefined}
                        />
                        <TextAreaField label={t('account.aboutBusiness')} optional value={form.description} onChange={set('description')} />
                    </>
                )}
                <Button type="submit" loading={save.isPending}>
                    {t('account.saveProfile')}
                </Button>
            </form>
        </Panel>
    );
}

function PasswordForm() {
    const t = useT();
    const { user } = useAuth();
    const toast = useToast();
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const save = useMutation({
        mutationFn: () => api.updatePassword(form),
        onSuccess: ({ message }) => {
            setForm({ current_password: '', password: '', password_confirmation: '' });
            toast(message);
        },
    });
    const errors = save.error?.fieldErrors ?? {};

    return (
        <Panel title={t('auth.password')}>
            {user.google_linked && (
                <p className="mb-4 text-sm text-muted">
                    {t('account.googleNoPassword')}{' '}
                    <Link to="/forgot-password" className="font-semibold text-pal-700 hover:underline">
                        {t('account.createPasswordByEmail')}
                    </Link>
                </p>
            )}
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <FormError error={save.error && !Object.keys(errors).length ? save.error.message : null} />
                <TextField
                    label={t('account.currentPassword')}
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.current_password}
                    onChange={(e) => setForm({ ...form, current_password: e.target.value })}
                    error={errors.current_password}
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
                <Button type="submit" loading={save.isPending}>
                    {t('account.changePassword')}
                </Button>
                <p className="text-sm text-muted">{t('account.changePasswordNote')}</p>
            </form>
        </Panel>
    );
}
