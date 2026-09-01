import { useQuery } from '@tanstack/react-query';
import { getBuyerRequest } from '../lib/api';

export const useBuyerRequest = (id) => {
    return useQuery({
        queryKey: ['buyer-request', id],
        queryFn: () => getBuyerRequest(id),
        enabled: !!id, // Only run the query if an ID is provided
    });
};