import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';

/**
 * List filters stored in the URL so they survive reloads, can be shared,
 * and work with the back button. Changing a filter resets to page 1.
 */
export function useListParams(defaults = {}) {
    const [searchParams, setSearchParams] = useSearchParams();

    const params = useMemo(() => {
        const values = { ...defaults };
        searchParams.forEach((value, key) => {
            values[key] = value;
        });
        values.page = Number(values.page ?? 1) || 1;
        return values;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchParams]);

    const update = useCallback(
        (changes) => {
            setSearchParams(
                (current) => {
                    const next = new URLSearchParams(current);
                    Object.entries(changes).forEach(([key, value]) => {
                        if (value === '' || value === null || value === undefined) next.delete(key);
                        else next.set(key, value);
                    });
                    if (!('page' in changes)) next.delete('page');
                    return next;
                },
                { replace: true },
            );
        },
        [setSearchParams],
    );

    return [params, update];
}
