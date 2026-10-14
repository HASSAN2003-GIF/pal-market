import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import SearchInput from '../../components/SearchInput';
import { Button, PageHeader, Pagination, QueryBoundary, cx } from '../../components/ui';
import { useAuth } from '../../context/AuthContext';
import { useListParams } from '../../hooks/useListParams';
import { useT } from '../../i18n';
import { api } from '../../lib/api';
import { formatDate, formatDateTime } from '../../lib/format';
import AdminActionDialog from './AdminActionDialog';
import AdminTable from './AdminTable';
import { useAdminAction } from './useAdminAction';

const ROLES = ['buyer', 'supplier', 'admin'];

function SuspendReason({ onChange }) {
    const t = useT();
    const [reason, setReason] = useState('');
    return (
        <label className="mt-3 block">
            <span className="mb-1.5 block text-sm font-semibold text-ink">{t('admin.reasonLabel')}</span>
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
    const t = useT();
    const { user: me } = useAuth();
    const [params, update] = useListParams();
    const query = { role: params.role, status: params.status, search: params.search, sort: params.sort, page: params.page };
    const users = useQuery({ queryKey: ['admin', 'users', query], queryFn: () => api.admin.users(query), placeholderData: keepPreviousData });
    const action = useAdminAction();
    const roleLabel = (role) => t(`nav.role.${role}`);

    function changeRole(user, role) {
        action.ask({
            title: t('admin.roleTitle', { name: user.name, role: roleLabel(role) }),
            body: (
                <p>
                    {t(role === 'admin' ? 'admin.roleAdminBody' : role === 'supplier' ? 'admin.roleSellerBody' : 'admin.roleBuyerBody')} {t('admin.roleSignOut')}
                </p>
            ),
            label: t('admin.changeRole'),
            danger: user.role === 'admin',
            run: () => api.admin.setRole(user.id, role),
            success: t('admin.roleChangedToast'),
        });
    }

    const columns = [
        {
            key: 'name',
            label: t('admin.colAccount'),
            render: (u) => (
                <span>
                    <span className="block font-semibold">
                        {u.name} {u.id === me.id && <span className="font-normal text-muted">{t('admin.you')}</span>}
                    </span>
                    <span className="block text-muted">{u.email}</span>
                </span>
            ),
        },
        {
            key: 'role',
            label: t('admin.colRole'),
            render: (u) => (
                <label>
                    <span className="sr-only">{t('admin.roleFor', { name: u.name })}</span>
                    <select className="field-input min-h-10 py-1.5 text-sm lg:min-h-0 lg:py-1" value={u.role} disabled={u.id === me.id} onChange={(e) => changeRole(u, e.target.value)}>
                        {ROLES.map((value) => (
                            <option key={value} value={value}>
                                {roleLabel(value)}
                            </option>
                        ))}
                    </select>
                </label>
            ),
        },
        { key: 'joined', label: t('admin.colJoined'), render: (u) => formatDate(u.created_at) },
        { key: 'last_login', label: t('admin.colLastSignIn'), render: (u) => (u.last_login_at ? formatDateTime(u.last_login_at) : t('admin.never')) },
        {
            key: 'status',
            label: t('common.status'),
            render: (u) => (
                <span className={cx('font-semibold', u.suspended_at ? 'text-danger' : 'text-ok')}>
                    {u.suspended_at ? t('admin.suspendedOn', { date: formatDate(u.suspended_at) }) : t('status.buyer.active')}
                </span>
            ),
        },
        {
            key: 'actions',
            label: t('common.actions'),
            align: 'right',
            render: (u) =>
                u.id === me.id ? (
                    <span className="text-muted">{t('common.none')}</span>
                ) : (
                    <span className="inline-flex gap-2">
                        {u.suspended_at ? (
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    action.ask({
                                        title: t('admin.actionTitle', { action: t('admin.reactivate'), name: u.name }),
                                        body: <p>{t('admin.reactivateUserBody')}</p>,
                                        label: t('admin.reactivate'),
                                        run: () => api.admin.reactivateUser(u.id),
                                    })
                                }
                            >
                                {t('admin.reactivate')}
                            </Button>
                        ) : (
                            <Button
                                size="sm"
                                variant="danger"
                                onClick={() => {
                                    let reason = '';
                                    action.ask({
                                        title: t('admin.actionTitle', { action: t('admin.suspend'), name: u.name }),
                                        body: (
                                            <>
                                                <p>{t('admin.suspendUserBody')}</p>
                                                <SuspendReason onChange={(value) => (reason = value)} />
                                            </>
                                        ),
                                        label: t('admin.suspendAccount'),
                                        danger: true,
                                        run: () => api.admin.suspendUser(u.id, reason || null),
                                    });
                                }}
                            >
                                {t('admin.suspend')}
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-danger"
                            onClick={() =>
                                action.ask({
                                    title: t('admin.actionTitle', { action: t('admin.delete'), name: u.name }),
                                    body: <p>{t('admin.deleteUserBody')}</p>,
                                    label: t('admin.deleteAccount'),
                                    danger: true,
                                    run: () => api.admin.deleteUser(u.id),
                                })
                            }
                        >
                            {t('admin.delete')}
                        </Button>
                    </span>
                ),
        },
    ];

    return (
        <>
            <PageHeader title={t('nav.adminUsers')} description={t('admin.usersDescription')} />
            <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_12rem_12rem]">
                <SearchInput label={t('admin.searchUsers')} placeholder={t('admin.searchUsersPlaceholder')} value={params.search ?? ''} onChange={(search) => update({ search })} />
                <label>
                    <span className="sr-only">{t('admin.colRole')}</span>
                    <select className="field-input" value={params.role ?? ''} onChange={(e) => update({ role: e.target.value })}>
                        <option value="">{t('admin.allRoles')}</option>
                        {ROLES.map((value) => (
                            <option key={value} value={value}>
                                {roleLabel(value)}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="sr-only">{t('common.status')}</span>
                    <select className="field-input" value={params.status ?? ''} onChange={(e) => update({ status: e.target.value })}>
                        <option value="">{t('admin.activeAndSuspended')}</option>
                        <option value="active">{t('status.buyer.active')}</option>
                        <option value="suspended">{t('status.buyer.suspended')}</option>
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
