import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { Button, PageHeader, Pagination, QueryBoundary, cx } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useListParams } from '../../hooks/useListParams';
import { api } from '../../lib/api';
import { formatDate, formatDateTime } from '../../lib/format';
import AdminActionDialog from './AdminActionDialog';
import AdminTable from './AdminTable';
import { useAdminAction } from './useAdminAction';

const ROLE_LABELS = { buyer: 'Buyer', supplier: 'Supplier', admin: 'Administrator' };

function SuspendReason({ onChange }) {
    const [reason, setReason] = useState('');
    return (
        <label className="mt-3 block">
            <span className="mb-1.5 block text-sm font-semibold text-ink">Reason (kept in the audit log)</span>
            <textarea
                className="field-input"
                rows={2}
                maxLength={500}
                value={reason}
                onChange={(e) => {
                    setReason(e.target.value);
                    onChange(e.target.value);
                }}
            />
        </label>
    );
}

export default function AdminUsersPage() {
    const { user: me } = useAuth();
    const [params, update] = useListParams();
    const query = { role: params.role, status: params.status, search: params.search, sort: params.sort, page: params.page };
    const users = useQuery({ queryKey: ['admin', 'users', query], queryFn: () => api.admin.users(query), placeholderData: keepPreviousData });
    const action = useAdminAction();

    function changeRole(user, role) {
        action.ask({
            title: `Make ${user.name} ${role === 'admin' ? 'an administrator' : `a ${ROLE_LABELS[role].toLowerCase()}`}?`,
            body: (
                <p>
                    {role === 'admin'
                        ? 'Administrators can approve suppliers, suspend accounts and change the catalog.'
                        : role === 'supplier'
                          ? 'A pending supplier profile is created if they do not have one. They must be approved before trading.'
                          : 'A buyer profile is created if they do not have one.'}{' '}
                    They will be signed out so the change takes effect.
                </p>
            ),
            label: 'Change role',
            danger: user.role === 'admin',
            run: () => api.admin.setRole(user.id, role),
            success: 'Role changed',
        });
    }

    const columns = [
        {
            key: 'name',
            label: 'Account',
            render: (u) => (
                <span>
                    <span className="block font-semibold">
                        {u.name} {u.id === me.id && <span className="font-normal text-muted">(you)</span>}
                    </span>
                    <span className="block text-muted">{u.email}</span>
                </span>
            ),
        },
        {
            key: 'role',
            label: 'Role',
            render: (u) => (
                <label>
                    <span className="sr-only">Role for {u.name}</span>
                    <select className="field-input py-1 text-sm" value={u.role} disabled={u.id === me.id} onChange={(e) => changeRole(u, e.target.value)}>
                        {Object.entries(ROLE_LABELS).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
            ),
        },
        { key: 'joined', label: 'Joined', render: (u) => formatDate(u.created_at) },
        { key: 'last_login', label: 'Last sign-in', render: (u) => (u.last_login_at ? formatDateTime(u.last_login_at) : 'Never') },
        {
            key: 'status',
            label: 'Status',
            render: (u) => (
                <span className={cx('font-semibold', u.suspended_at ? 'text-danger' : 'text-ok')}>{u.suspended_at ? `Suspended ${formatDate(u.suspended_at)}` : 'Active'}</span>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (u) =>
                u.id === me.id ? (
                    <span className="text-muted">None</span>
                ) : (
                    <span className="inline-flex gap-2">
                        {u.suspended_at ? (
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    action.ask({
                                        title: `Reactivate ${u.name}?`,
                                        body: <p>They can sign in again straight away.</p>,
                                        label: 'Reactivate',
                                        run: () => api.admin.reactivateUser(u.id),
                                    })
                                }
                            >
                                Reactivate
                            </Button>
                        ) : (
                            <Button
                                size="sm"
                                variant="danger"
                                onClick={() => {
                                    let reason = '';
                                    action.ask({
                                        title: `Suspend ${u.name}?`,
                                        body: (
                                            <>
                                                <p>They are signed out on every device and cannot sign in until reactivated.</p>
                                                <SuspendReason onChange={(value) => (reason = value)} />
                                            </>
                                        ),
                                        label: 'Suspend account',
                                        danger: true,
                                        run: () => api.admin.suspendUser(u.id, reason || null),
                                    });
                                }}
                            >
                                Suspend
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-danger"
                            onClick={() =>
                                action.ask({
                                    title: `Delete ${u.name}?`,
                                    body: (
                                        <p>
                                            This permanently deletes the account and its drafts. Accounts with quotations or orders cannot be deleted; suspend them
                                            instead.
                                        </p>
                                    ),
                                    label: 'Delete account',
                                    danger: true,
                                    run: () => api.admin.deleteUser(u.id),
                                })
                            }
                        >
                            Delete
                        </Button>
                    </span>
                ),
        },
    ];

    return (
        <>
            <PageHeader title="User accounts" description="Sign-in accounts for buyers, suppliers and administrators." />
            <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_12rem_12rem]">
                <SearchInput label="Search accounts" placeholder="Name, email or phone" value={params.search ?? ''} onChange={(search) => update({ search })} />
                <label>
                    <span className="sr-only">Role</span>
                    <select className="field-input" value={params.role ?? ''} onChange={(e) => update({ role: e.target.value })}>
                        <option value="">All roles</option>
                        {Object.entries(ROLE_LABELS).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="sr-only">Status</span>
                    <select className="field-input" value={params.status ?? ''} onChange={(e) => update({ status: e.target.value })}>
                        <option value="">Active and suspended</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </label>
            </div>
            <QueryBoundary query={users}>
                {({ users: rows, meta }) => (
                    <>
                        <AdminTable columns={columns} rows={rows} />
                        <Pagination meta={meta} onPage={(page) => update({ page })} />
                    </>
                )}
            </QueryBoundary>
            <AdminActionDialog action={action} />
        </>
    );
}
