export default function HomePage() {
    return (
        <section className="border-b border-slate-200 bg-white">
            <div className="mx-auto max-w-7xl px-6 py-24">
                <div className="max-w-3xl">
                    <p className="mb-4 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Tanzania's hardware marketplace
                    </p>

                    <h1 className="text-5xl font-semibold leading-tight tracking-tight text-slate-950">
                        Buy building materials from suppliers you can trust.
                    </h1>

                    <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                        Find construction products, compare supplier prices,
                        request quotations, and manage your orders in one
                        place.
                    </p>

                    <div className="mt-8 flex gap-3">
                        <button
                            type="button"
                            className="rounded-lg bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Browse products
                        </button>

                        <button
                            type="button"
                            className="rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Find suppliers
                        </button>
                    </div>
                </div>
            </div>
        </section>
    );
}