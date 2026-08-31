import { useQuery } from '@tanstack/react-query';
import { getProducts } from '../lib/api';

export const useProducts = () => {
    return useQuery({
        queryKey: ['products'],
        queryFn: getProducts, // We pass the function directly. api.js already handles the parsing.
    });
};