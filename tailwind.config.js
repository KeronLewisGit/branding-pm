import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Run status tokens (contract §8). Never encode status by
                // colour alone — always pair with a text label or icon.
                status: {
                    pending: colors.slate[400],
                    in_progress: colors.amber[500],
                    submitted: colors.sky[500],
                    approved: colors.emerald[600],
                    rejected: colors.rose[600],
                    missed: colors.red[700],
                },
                // Per-item status inside a run (RunItemStatus).
                item: {
                    pending: colors.slate[400],
                    done: colors.emerald[600],
                    not_applicable: colors.slate[500],
                    failed: colors.rose[600],
                },
            },
        },
    },

    // RunStatus::color() and RunItemStatus::color() return these token names so
    // callers can build `bg-{$status->color()}`. Tailwind's JIT cannot see a
    // class assembled at runtime, so the full set is safelisted here. Prefer the
    // <x-status-dot> component, which maps to literal classes; this safelist is
    // the safety net for anywhere that interpolates instead.
    safelist: [
        { pattern: /^(bg|text|border|ring)-status-(pending|in_progress|submitted|approved|rejected|missed)$/ },
        { pattern: /^(bg|text|border|ring)-item-(pending|done|not_applicable|failed)$/ },
    ],

    plugins: [forms],
};
