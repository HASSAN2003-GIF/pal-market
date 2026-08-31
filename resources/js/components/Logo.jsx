import React from 'react';

export default function Logo({ className = "h-8" }) {
    return (
        <svg viewBox="0 0 90 32" className={className} xmlns="http://www.w3.org/2000/svg">
            <text
                x="0"
                y="26"
                fontFamily="Manrope, system-ui, sans-serif"
                fontWeight="900"
                fontSize="32"
                letterSpacing="-0.03em"
                fill="#0f172a" /* Tailwind slate-900 */
            >
                PAL<tspan fill="#0284c7">.</tspan> {/* Tailwind light blue-600 */}
            </text>
        </svg>
    );
}