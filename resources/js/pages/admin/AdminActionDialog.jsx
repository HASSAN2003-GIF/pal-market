import { ConfirmDialog } from '../../components/ui';

export default function AdminActionDialog({ action }) {
    const { pending } = action;
    return (
        <ConfirmDialog
            open={Boolean(pending)}
            title={pending?.title ?? ''}
            confirmLabel={pending?.label ?? 'Confirm'}
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
