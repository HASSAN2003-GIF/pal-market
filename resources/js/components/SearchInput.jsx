import { useEffect, useState } from 'react';
import { useDebounced } from '../hooks/useDebounced';
import { SearchIcon } from './icons';

/** Search box that reports its value after the user pauses typing. */
export default function SearchInput({ value = '', onChange, label, placeholder, className = '' }) {
    const [text, setText] = useState(value);
    const debounced = useDebounced(text);

    useEffect(() => setText(value), [value]);

    useEffect(() => {
        if (debounced !== value) onChange(debounced);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    return (
        <label className={`relative block ${className}`}>
            <span className="sr-only">{label}</span>
            <SearchIcon className="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-faint" />
            <input
                type="search"
                className="field-input pl-10"
                placeholder={placeholder}
                value={text}
                onChange={(event) => setText(event.target.value)}
            />
        </label>
    );
}
