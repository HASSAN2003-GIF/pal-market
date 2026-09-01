import { useQuery } from '@tanstack/react-query';
import { getMarketRequests } from '../lib/api';

export const useMarketRequests = () => {
    return useQuery({
        queryKey: ['market-requests'],
        queryFn: getMarketRequests,
    });
};