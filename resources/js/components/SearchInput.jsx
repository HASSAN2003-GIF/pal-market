import { useEffect, useState } from 'react';
import { useDebounced } from '../hooks/useDebounced';

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
        <label className={`block ${className}`}>
            <span className="sr-only">{label}</span>
            <input
                type="search"
                className="field-input"
                placeholder={placeholder}
                value={text}
                onChange={(event) => setText(event.target.value)}
            />
        </label>
    );
}
