import React from 'react';
import { useProducts } from '../hooks/useProducts';
import ProductCard from '../components/ProductCard'; 

export default function MarketplacePage() {
    const { data: products, isLoading, isError, error } = useProducts();

    if (isLoading) return <div className="p-8 text-center font-manrope">Loading marketplace materials...</div>;
    
    if (isError) return <div className="p-8 text-red-500 font-manrope">Failed to load products: {error.message}</div>;

    return (
        <div className="container mx-auto px-4 py-8 font-manrope">
            <h1 className="text-3xl font-bold mb-6">PAL Market Materials</h1>
            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
    {products?.data?.map((product) => (
        <ProductCard key={product.id} product={product} />
    ))}
</div>
        </div>
    );
}