import { ButtonLink } from '../components/ui';

export default function NotFoundPage() {
    return (
        <div className="mx-auto max-w-xl px-4 py-20 text-center">
            <p className="doc-number text-lg text-muted">404</p>
            <h1 className="mt-2 text-[2.6rem]">This page does not exist</h1>
            <p className="mt-3 text-ink-soft">The link may be out of date, or the page was moved.</p>
            <div className="mt-6 flex justify-center gap-3">
                <ButtonLink to="/">Go to the home page</ButtonLink>
                <ButtonLink to="/materials" variant="secondary">
                    Browse materials
                </ButtonLink>
            </div>
        </div>
    );
}
