import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import containerQueries from '@tailwindcss/container-queries';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],
    theme: {
        extend: {
            colors: {
                "inverse-primary": "#494bd6",
                "on-primary-container": "#0d0096",
                "tertiary-fixed-dim": "#ddb7ff",
                "on-background": "#d6e3ff",
                "outline-variant": "#464554",
                "tertiary": "#ddb7ff",
                "on-tertiary": "#490080",
                "surface-container-low": "#0d1c32",
                "on-secondary": "#00344d",
                "surface-dim": "#041329",
                "secondary-container": "#00a2e6",
                "surface-bright": "#2c3951",
                "on-tertiary-fixed": "#2c0051",
                "on-tertiary-fixed-variant": "#6900b3",
                "primary-fixed": "#e1e0ff",
                "primary": "#c0c1ff",
                "primary-container": "#8083ff",
                "on-error": "#690005",
                "tertiary-fixed": "#f0dbff",
                "surface-variant": "#27354c",
                "surface-container-highest": "#27354c",
                "on-tertiary-container": "#400071",
                "secondary-fixed-dim": "#89ceff",
                "on-error-container": "#ffdad6",
                "on-secondary-container": "#00344e",
                "surface-container": "#112036",
                "on-primary-fixed": "#07006c",
                "on-surface": "#d6e3ff",
                "inverse-on-surface": "#233148",
                "surface-tint": "#c0c1ff",
                "outline": "#908fa0",
                "tertiary-container": "#b76dff",
                "on-secondary-fixed-variant": "#004c6e",
                "on-primary-fixed-variant": "#2f2ebe",
                "on-secondary-fixed": "#001e2f",
                "surface": "#041329",
                "error": "#ffb4ab",
                "secondary": "#89ceff",
                "primary-fixed-dim": "#c0c1ff",
                "error-container": "#93000a",
                "inverse-surface": "#d6e3ff",
                "on-surface-variant": "#c7c4d7",
                "surface-container-lowest": "#010e24",
                "on-primary": "#1000a9",
                "background": "#041329",
                "surface-container-high": "#1c2a41",
                "secondary-fixed": "#c9e6ff"
            },
            borderRadius: {
                "DEFAULT": "0.25rem",
                "lg": "0.5rem",
                "xl": "0.75rem",
                "full": "9999px"
            },
            spacing: {
                "gutter": "24px",
                "container-padding-mobile": "20px",
                "unit": "8px",
                "container-padding-desktop": "40px",
                "panel-gap": "32px"
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                "display-lg-mobile": ["Inter"],
                "display-lg": ["Inter"],
                "headline-md": ["Inter"],
                "label-sm": ["Inter"],
                "body-md": ["Inter"],
                "body-lg": ["Inter"]
            },
            fontSize: {
                "display-lg-mobile": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                "display-lg": ["48px", { "lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                "headline-md": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
                "label-sm": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "600" }],
                "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                "body-lg": ["18px", { "lineHeight": "28px", "fontWeight": "400" }]
            }
        },
    },

    plugins: [forms, containerQueries],
};
