import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useToast } from '../../components/ui';

/**
 * Confirm-then-run for administrative actions. `pending` holds what is being
 * confirmed: { title, body, label, danger, run: () => Promise, success }.
 */
export function useAdminAction(invalidateKeys = [['admin']]) {
    const queryClient = useQueryClient();
    const toast = useToast();
    const [pending, setPending] = useState(null);

    const mutation = useMutation({
        mutationFn: () => pending.run(),
        onSuccess: (data) => {
            invalidateKeys.forEach((queryKey) => queryClient.invalidateQueries({ queryKey }));
            toast(pending.success ?? data?.message ?? 'Done');
            setPending(null);
        },
    });

    return {
        pending,
        ask: (action) => {
            mutation.reset();
            setPending(action);
        },
        cancel: () => setPending(null),
        confirm: () => mutation.mutate(),
        busy: mutation.isPending,
        error: mutation.error?.message,
    };
}
