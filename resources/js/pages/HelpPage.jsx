import { Link } from 'react-router-dom';
import { useT } from '../i18n';

/** Short answers to the questions people ask first. No chatbot; plain text that can be read or printed. */
const SECTIONS = [
    { title: 'help.buyingTitle', items: ['askPrices', 'howLong', 'choose', 'pay', 'cancel'] },
    { title: 'help.sellingTitle', items: ['startSelling', 'whatToAdd', 'sendOffer', 'orderSteps'] },
    { title: 'help.accountTitle', items: ['language', 'theme', 'area', 'password'] },
];

export default function HelpPage() {
    const t = useT();

    return (
        <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 xl:max-w-4xl">
            <h1 className="brand-dot text-[2.4rem] sm:text-[3rem]">{t('help.title')}</h1>
            <p className="mt-2 text-lg text-ink-soft">{t('help.lead')}</p>

            <nav aria-label={t('help.contents')} className="mt-6 flex flex-wrap gap-2">
                {SECTIONS.map((section) => (
                    <a key={section.title} href={`#${section.title.split('.')[1]}`} className="inline-flex min-h-10 items-center rounded-full border border-rule bg-paper px-3.5 py-2 text-sm font-semibold hover:border-pal-500">
                        {t(section.title)}
                    </a>
                ))}
            </nav>

            {SECTIONS.map((section) => (
                <section key={section.title} id={section.title.split('.')[1]} className="mt-10 scroll-mt-28">
                    <h2 className="text-[1.7rem]">{t(section.title)}</h2>
                    <div className="mt-3 divide-y divide-rule overflow-hidden rounded-[var(--radius-panel)] border border-rule bg-paper">
                        {section.items.map((item) => (
                            <details key={item} className="group px-4 py-3 open:bg-concrete/50">
                                <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-bold marker:hidden">
                                    {t(`help.${item}Q`)}
                                    <span aria-hidden="true" className="text-xl text-pal-600 transition-transform group-open:rotate-45">
                                        +
                                    </span>
                                </summary>
                                <p className="mt-2 text-ink-soft">{t(`help.${item}A`)}</p>
                            </details>
                        ))}
                    </div>
                </section>
            ))}

            <div className="mt-10 rounded-[var(--radius-panel)] border border-pal-200 bg-pal-50 px-5 py-4">
                <p className="font-bold">{t('help.readyTitle')}</p>
                <div className="mt-2 flex flex-wrap gap-x-5 gap-y-2 font-semibold">
                    <Link to="/materials" className="tap text-pal-700 hover:underline">
                        {t('home.ctaBrowse')}
                    </Link>
                    <Link to="/register?role=buyer" className="tap text-pal-700 hover:underline">
                        {t('home.ctaRequest')}
                    </Link>
                    <Link to="/register?role=supplier" className="tap text-pal-700 hover:underline">
                        {t('home.sellCta')}
                    </Link>
                </div>
            </div>
        </div>
    );
}
