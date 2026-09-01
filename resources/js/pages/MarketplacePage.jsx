import React from 'react';
import { useProducts } from '../hooks/useProducts';
import ProductCard from '../components/ProductCard';
import { Link } from 'react-router-dom'; 

export default function MarketplacePage() {
    // We grab the full response data from TanStack Query
    const { data, isLoading, isError, error } = useProducts();

    if (isLoading) return <div className="p-8 text-center font-manrope">Loading marketplace materials...</div>;
    
    if (isError) return <div className="p-8 text-red-500 font-manrope">Failed to load products: {error.message}</div>;

    // Safely extract the products array depending on how Laravel paginates it
    const products = data?.data || data?.products || data || [];

    return (
        <div className="container mx-auto px-4 py-8 font-manrope">
            <h1 className="mb-6 text-3xl font-bold text-ink-950">PAL Market Materials</h1>
            
            {products.length === 0 ? (
                <div className="rounded-xl border border-border p-12 text-center text-ink-500">
                    No materials found in the database.
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {/* THIS is the loop that was missing! */}
                    {products.map((product) => (
                        <Link 
                            to={`/products/${product.id}`} 
                            key={product.id} 
                            className="block transition hover:opacity-90"
                        >
                            <ProductCard product={product} />
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}