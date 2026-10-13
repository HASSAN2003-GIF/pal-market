import { ConfirmDialog } from '../../components/ui';
import { useT } from '../../i18n';

export default function AdminActionDialog({ action }) {
    const t = useT();
    const { pending } = action;
    return (
        <ConfirmDialog
            open={Boolean(pending)}
            title={pending?.title ?? ''}
            confirmLabel={pending?.label ?? t('admin.confirm')}
            confirmVariant={pending?.danger ? 'danger' : 'primary'}
            busy={action.busy}
            error={action.error}
            onCancel={action.cancel}
            onConfirm={action.confirm}
        >
            {pending?.body}
        </ConfirmDialog>
    );
}
