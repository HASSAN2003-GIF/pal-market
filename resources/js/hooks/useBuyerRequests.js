import { useQuery } from '@tanstack/react-query';
import { getBuyerRequests } from '../lib/api';

export const useBuyerRequests = () => {
    return useQuery({
        queryKey: ['buyer-requests'],
        queryFn: getBuyerRequests,
    });
};