import { Link } from 'react-router-dom';

const categories = [
    'Cement & concrete',
    'Plumbing',
    'Windows & doors',
    'Electrical',
];

export default function HomePage() {
    return (
        <div className="bg-surface">
            <section className="border-b border-border bg-surface">
                <div className="mx-auto max-w-7xl px-6 py-14 lg:py-20">
                    <div className="grid items-center gap-14 lg:grid-cols-[1.15fr_0.85fr]">
                        <div>
                            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-pal-200 bg-pal-50 px-3 py-1.5 text-xs font-bold text-pal-700">
                                <span className="h-1.5 w-1.5 rounded-full bg-pal-600" />
                                Tanzania's hardware marketplace
                            </div>

                            <h1 className="max-w-3xl text-5xl font-extrabold leading-[1.04] tracking-[-0.045em] text-ink-950 sm:text-6xl">
                                Find the materials your project needs.
                            </h1>

                            <p className="mt-6 max-w-2xl text-lg leading-8 text-ink-600">
                                Browse construction products from suppliers,
                                compare prices and availability, and make
                                purchasing decisions with the information in
                                one place.
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    to="/marketplace"
                                    className="rounded-lg bg-pal-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-pal-700"
                                >
                                    Browse materials
                                </Link>

                                <Link
                                    to="/marketplace"
                                    className="rounded-lg border border-border bg-white px-5 py-3 text-sm font-bold text-ink-800 transition hover:border-pal-300 hover:bg-pal-50"
                                >
                                    Compare suppliers
                                </Link>
                            </div>

                            <div className="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-ink-500">
                                <span>✓ Local suppliers</span>
                                <span>✓ Price comparison</span>
                                <span>✓ Request quotations</span>
                            </div>
                        </div>

                        <div className="relative">
                            <div className="rounded-3xl border border-border bg-white p-5 shadow-sm">
                                <div className="mb-5 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-wider text-ink-500">
                                            Explore materials
                                        </p>
                                        <p className="mt-1 text-lg font-bold text-ink-950">
                                            What are you looking for?
                                        </p>
                                    </div>

                                    <div className="rounded-xl bg-pal-50 px-3 py-2 text-xs font-bold text-pal-700">
                                        Browse
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {categories.map((category) => (
                                        <Link
                                            key={category}
                                            to="/marketplace"
                                            className="flex items-center justify-between rounded-xl border border-border bg-surface px-4 py-3.5 text-sm font-semibold text-ink-800 transition hover:border-pal-200 hover:bg-pal-50 hover:text-pal-700"
                                        >
                                            <span>{category}</span>
                                            <span className="text-ink-500 transition group-hover:text-pal-600">
                                                →
                                            </span>
                                        </Link>
                                    ))}
                                </div>

                                <Link
                                    to="/marketplace"
                                    className="mt-4 block rounded-xl bg-ink-950 px-4 py-3 text-center text-sm font-bold text-white transition hover:bg-ink-900"
                                >
                                    View all materials
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="border-b border-border bg-surface-soft">
                <div className="mx-auto max-w-7xl px-6 py-10">
                    <div className="grid gap-6 md:grid-cols-3">
                        <div>
                            <p className="text-sm font-bold text-ink-900">
                                Compare before you buy
                            </p>
                            <p className="mt-2 text-sm leading-6 text-ink-500">
                                See different supplier offerings for the same
                                product instead of checking one shop at a time.
                            </p>
                        </div>

                        <div>
                            <p className="text-sm font-bold text-ink-900">
                                Find suppliers
                            </p>
                            <p className="mt-2 text-sm leading-6 text-ink-500">
                                Discover businesses selling the materials you
                                need and see where they are located.
                            </p>
                        </div>

                        <div>
                            <p className="text-sm font-bold text-ink-900">
                                Request quotations
                            </p>
                            <p className="mt-2 text-sm leading-6 text-ink-500">
                                For larger purchases, send your requirements
                                and receive supplier quotations.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}