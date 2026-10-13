/**
 * PAL Market brand mark, redrawn as vectors from the supplied "PAL." logo:
 * heavy geometric capitals (Manrope ExtraBold outlines) followed by a round blue dot.
 *
 * Colours come from the --logo-ink / --logo-dot tokens so the mark follows the
 * light/dark theme without CSS filters. `tone="on-dark"` forces the dark-surface
 * colours for places that are always dark. Source assets live in public/brand/.
 */
const LETTERS =
    'M140 0V-1440H748Q769 -1440 804.5 -1438.5Q840 -1437 868 -1432Q997 -1412 1079.5 -1347.0Q1162 -1282 1201.0 -1183.5Q1240 -1085 1240 -964Q1240 -843 1200.5 -744.5Q1161 -646 1078.5 -581.0Q996 -516 868 -496Q840 -492 804.0 -490.0Q768 -488 748 -488H412V0ZM412 -742H736Q757 -742 782.0 -744.0Q807 -746 828 -752Q883 -767 913.0 -802.5Q943 -838 954.5 -881.5Q966 -925 966 -964Q966 -1003 954.5 -1046.5Q943 -1090 913.0 -1125.5Q883 -1161 828 -1176Q807 -1182 782.0 -1184.0Q757 -1186 736 -1186H412ZM1320.0 0 1760.0 -1440H2166.0L2606.0 0H2326.0L1934.0 -1270H1986.0L1600.0 0ZM1584.0 -300V-554H2344.0V-300ZM2786.0 0V-1440H3058.0V-254H3678.0V0Z';
const P_ONLY =
    'M140 0V-1440H748Q769 -1440 804.5 -1438.5Q840 -1437 868 -1432Q997 -1412 1079.5 -1347.0Q1162 -1282 1201.0 -1183.5Q1240 -1085 1240 -964Q1240 -843 1200.5 -744.5Q1161 -646 1078.5 -581.0Q996 -516 868 -496Q840 -492 804.0 -490.0Q768 -488 748 -488H412V0ZM412 -742H736Q757 -742 782.0 -744.0Q807 -746 828 -752Q883 -767 913.0 -802.5Q943 -838 954.5 -881.5Q966 -925 966 -964Q966 -1003 954.5 -1046.5Q943 -1090 913.0 -1125.5Q883 -1161 828 -1176Q807 -1182 782.0 -1184.0Q757 -1186 736 -1186H412Z';
const DOT_R = 205;

const SHAPES = {
    wordmark: { d: LETTERS, viewBox: '140 -1440 4043 1440', cx: 3978 },
    mark: { d: P_ONLY, viewBox: '140 -1440 1605 1440', cx: 1540 },
};

export default function Logo({ variant = 'wordmark', tone = 'auto', label = 'PAL Market', className = '' }) {
    const shape = SHAPES[variant] ?? SHAPES.wordmark;
    const ink = tone === 'on-dark' ? '#FFFFFF' : 'var(--logo-ink, #1C2228)';
    const dot = tone === 'on-dark' ? '#4D9BE0' : 'var(--logo-dot, #1C73C1)';

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox={shape.viewBox}
            role="img"
            aria-label={label}
            focusable="false"
            className={`block w-auto ${variant === 'mark' ? 'h-7' : 'h-6'} ${className}`}
        >
            <path fill={ink} d={shape.d} />
            <circle fill={dot} cx={shape.cx} cy={-DOT_R} r={DOT_R} />
        </svg>
    );
}
