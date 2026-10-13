import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Button, FormError, PageHeader, Panel, StatusBadge, TextAreaField, TextField, useToast } from '../components/ui';
import { useAuth } from '../context/AuthContext';
import { api } from '../lib/api';

export default function AccountPage() {
    const { user } = useAuth();

    return (
        <>
            <PageHeader title="Account settings" description={user.email} />
            <div className="grid gap-6 lg:grid-cols-2">
                <ProfileForm />
                <PasswordForm />
            </div>
        </>
    );
}

function ProfileForm() {
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
            toast('Profile saved');
        },
    });
    const errors = save.error?.fieldErrors ?? {};
    const tinLocked = user.supplier?.status === 'approved';

    return (
        <Panel title="Profile" status={user.supplier && <StatusBadge kind="supplier" status={user.supplier.status} />}>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <FormError error={save.error && !Object.keys(errors).length ? save.error.message : null} />
                <TextField label="Your name" required value={form.name} onChange={set('name')} error={errors.name} />
                <TextField label="Phone number" optional type="tel" value={form.phone} onChange={set('phone')} error={errors.phone} hint="You can sign in with this number." />
                {business && (
                    <>
                        <TextField label="Business name" required value={form.business_name} onChange={set('business_name')} error={errors.business_name} />
                        {user.buyer_profile && (
                            <TextField label="Type of business" optional placeholder="Contractor, developer, homeowner" value={form.business_type} onChange={set('business_type')} />
                        )}
                        <TextField
                            label="TIN"
                            optional
                            value={form.tin_number}
                            onChange={set('tin_number')}
                            error={errors.tin_number}
                            disabled={tinLocked}
                            hint={tinLocked ? 'Verified during approval. Contact support to change it.' : undefined}
                        />
                        <TextAreaField label="About the business" optional value={form.description} onChange={set('description')} />
                    </>
                )}
                <Button type="submit" loading={save.isPending}>
                    Save profile
                </Button>
            </form>
        </Panel>
    );
}

function PasswordForm() {
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
        <Panel title="Password">
            {user.google_linked && (
                <p className="mb-4 text-sm text-muted">
                    You sign in with Google. If you have never set a password,{' '}
                    <Link to="/forgot-password" className="font-semibold text-pal-700 hover:underline">
                        create one by email
                    </Link>
                    .
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
                    label="Current password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.current_password}
                    onChange={(e) => setForm({ ...form, current_password: e.target.value })}
                    error={errors.current_password}
                />
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
                <Button type="submit" loading={save.isPending}>
                    Change password
                </Button>
                <p className="text-sm text-muted">Changing your password signs out your other devices.</p>
            </form>
        </Panel>
    );
}
