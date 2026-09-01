import { useQuery } from '@tanstack/react-query';
import { getProduct } from '../lib/api';

export const useProduct = (id) => {
    return useQuery({
        queryKey: ['product', id],
        queryFn: () => getProduct(id),
        enabled: !!id,
    });
};