import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            fill="currentColor"
        >
            {/* Wallet body */}
            <rect x="2" y="10" width="36" height="24" rx="4" />
            {/* Wallet flap */}
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6 10V8a4 4 0 0 1 4-4h22a4 4 0 0 1 4 4v2H6Z"
                opacity="0.6"
            />
            {/* Coin slot / card pocket */}
            <rect
                x="24"
                y="19"
                width="10"
                height="6"
                rx="3"
                fill="white"
                opacity="0.35"
            />
            {/* Dot inside pocket */}
            <circle cx="30" cy="22" r="1.5" fill="white" opacity="0.7" />
        </svg>
    );
}
