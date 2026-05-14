import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                "on-background": "#1a1c1f",
                "secondary-fixed": "#dbe4ed",
                "tertiary-container": "#592300",
                "surface-container-high": "#e8e8ed",
                "on-primary-fixed": "#001b3c",
                "surface-variant": "#e2e2e7",
                "inverse-primary": "#a7c8ff",
                "surface-dim": "#dad9de",
                "error": "#ba1a1a",
                "primary-fixed": "#d5e3ff",
                "on-secondary": "#ffffff",
                "on-secondary-fixed-variant": "#3f484f",
                "on-primary-container": "#799dd6",
                "on-error": "#ffffff",
                "on-error-container": "#93000a",
                "inverse-on-surface": "#f1f0f5",
                "surface-container-low": "#f4f3f8",
                "background": "#f9f9fe",
                "on-surface-variant": "#43474f",
                "primary": "#001e40",
                "tertiary": "#381300",
                "on-tertiary-fixed-variant": "#723610",
                "surface-container-lowest": "#ffffff",
                "surface-bright": "#f9f9fe",
                "surface-tint": "#3a5f94",
                "secondary": "#575f67",
                "on-secondary-fixed": "#141d23",
                "on-tertiary-container": "#d8885c",
                "on-primary-fixed-variant": "#1f477b",
                "on-primary": "#ffffff",
                "surface-container-highest": "#e2e2e7",
                "secondary-container": "#d8e1ea",
                "tertiary-fixed": "#ffdbca",
                "inverse-surface": "#2f3034",
                "tertiary-fixed-dim": "#ffb690",
                "outline": "#737780",
                "primary-container": "#003366",
                "outline-variant": "#c3c6d1",
                "surface": "#f9f9fe",
                "surface-container": "#eeedf2",
                "on-tertiary-fixed": "#341100",
                "on-surface": "#1a1c1f",
                "secondary-fixed-dim": "#bfc8d0",
                "on-secondary-container": "#5b646b",
                "primary-fixed-dim": "#a7c8ff",
                "on-tertiary": "#ffffff",
                "error-container": "#ffdad6"
            },
            spacing: {
                "form-gap": "12px",
                "gutter": "16px",
                "unit": "4px",
                "container-padding": "24px",
                "sidebar-width": "260px",
                "table-cell-padding": "8px 12px"
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                "body-lg": ["Inter"],
                "table-data": ["Inter"],
                "body-md": ["Inter"],
                "badge": ["Inter"],
                "h1": ["Inter"],
                "h2": ["Inter"],
                "label-sm": ["Inter"]
            },
            borderRadius: {
                "DEFAULT": "0.125rem",
                "lg": "0.25rem",
                "xl": "0.5rem",
                "full": "0.75rem"
            },
            fontSize: {
                "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                "table-data": ["13px", {"lineHeight": "18px", "fontWeight": "400"}],
                "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                "badge": ["11px", {"lineHeight": "12px", "fontWeight": "700"}],
                "h1": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.02em", "fontWeight": "600"}],
                "h2": ["20px", {"lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}]
            },
        },
    },

    plugins: [forms],
};
