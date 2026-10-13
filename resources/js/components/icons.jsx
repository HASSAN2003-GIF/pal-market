/* Small stroke icons. Decorative by default; pass a label to make one meaningful. */
function Icon({ label, className = 'h-5 w-5', children }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden={label ? undefined : 'true'}
            role={label ? 'img' : undefined}
            aria-label={label}
            focusable="false"
        >
            {children}
        </svg>
    );
}

export const PinIcon = (props) => (
    <Icon {...props}>
        <path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" />
        <circle cx="12" cy="9.5" r="2.5" />
    </Icon>
);

export const SearchIcon = (props) => (
    <Icon {...props}>
        <circle cx="11" cy="11" r="6.5" />
        <path d="m20 20-4.2-4.2" />
    </Icon>
);

export const SunIcon = (props) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </Icon>
);

export const MoonIcon = (props) => (
    <Icon {...props}>
        <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" />
    </Icon>
);

export const DeviceIcon = (props) => (
    <Icon {...props}>
        <rect x="3" y="4" width="18" height="12" rx="2" />
        <path d="M8 20h8M12 16v4" />
    </Icon>
);

export const MenuIcon = (props) => (
    <Icon {...props}>
        <path d="M4 7h16M4 12h16M4 17h16" />
    </Icon>
);

export const CloseIcon = (props) => (
    <Icon {...props}>
        <path d="M6 6l12 12M18 6 6 18" />
    </Icon>
);

export const ChevronDownIcon = (props) => (
    <Icon {...props}>
        <path d="m6 9 6 6 6-6" />
    </Icon>
);

export const LocateIcon = (props) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="3.5" />
        <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
        <circle cx="12" cy="12" r="8" />
    </Icon>
);

export const CheckIcon = (props) => (
    <Icon {...props}>
        <path d="m5 12.5 4.5 4.5L19 7.5" />
    </Icon>
);

export const HomeIcon = (props) => (
    <Icon {...props}>
        <path d="M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1z" />
    </Icon>
);

export const PlusIcon = (props) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 8v8M8 12h8" />
    </Icon>
);

export const ListIcon = (props) => (
    <Icon {...props}>
        <rect x="5" y="4" width="14" height="17" rx="2" />
        <path d="M9 4.5V3h6v1.5M9 10h6M9 14h6M9 18h3" />
    </Icon>
);

export const BoxIcon = (props) => (
    <Icon {...props}>
        <path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z" />
        <path d="M3.5 7.5 12 12l8.5-4.5M12 12v9" />
    </Icon>
);

export const TagIcon = (props) => (
    <Icon {...props}>
        <path d="M3 12V4h8l9 9-8 8z" />
        <circle cx="7.5" cy="8.5" r="1.5" />
    </Icon>
);

export const StoreIcon = (props) => (
    <Icon {...props}>
        <path d="M4 9.5 5.5 4h13L20 9.5M4 9.5V20h16V9.5M4 9.5h16M9.5 20v-6h5v6" />
    </Icon>
);

export const UsersIcon = (props) => (
    <Icon {...props}>
        <circle cx="9" cy="8" r="3.5" />
        <path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5" />
    </Icon>
);

export const MoreIcon = (props) => (
    <Icon {...props}>
        <circle cx="5" cy="12" r="1.5" />
        <circle cx="12" cy="12" r="1.5" />
        <circle cx="19" cy="12" r="1.5" />
    </Icon>
);

export const HelpIcon = (props) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="9" />
        <path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01" />
    </Icon>
);
