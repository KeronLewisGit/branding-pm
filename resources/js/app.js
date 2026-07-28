import './bootstrap';

/*
 * Livewire 3 bundles and boots Alpine itself. Do NOT import Alpine or call
 * Alpine.start() here — every component would initialise twice. Shared
 * components are registered on `alpine:init`, which Livewire's bundled
 * Alpine dispatches (with window.Alpine already set) before it starts.
 */
document.addEventListener('alpine:init', () => {
    /*
     * Kiosk inactivity watchdog (client half of `kiosk.idle`, contract §6).
     * After `seconds` without interaction it POSTs to `releaseUrl`
     * (route `kiosk.release`) as a real form submission so the server's
     * redirect back to the kiosk picker is followed. The server-side check
     * on session('kiosk.authenticated_at') remains the authority — this is
     * a convenience, never a security boundary.
     *
     * Usage: <body x-data="idleRelease(120, '{{ route('kiosk.release') }}')">
     */
    window.Alpine.data('idleRelease', (seconds = 120, releaseUrl = null) => ({
        remaining: seconds,
        timer: null,

        init() {
            const reset = () => {
                this.remaining = seconds;
            };

            ['pointerdown', 'touchstart', 'keydown', 'wheel', 'scroll'].forEach((event) =>
                window.addEventListener(event, reset, { passive: true })
            );

            this.timer = setInterval(() => {
                this.remaining -= 1;

                if (this.remaining <= 0) {
                    clearInterval(this.timer);
                    this.release();
                }
            }, 1000);
        },

        release() {
            if (!releaseUrl) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = releaseUrl;

            const meta = document.querySelector('meta[name="csrf-token"]');

            if (meta) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = meta.content;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        },

        destroy() {
            clearInterval(this.timer);
        },
    }));

    /*
     * Confirm-before-action guard for destructive controls.
     *
     * Usage (form):
     *   <form x-data="confirmAction('{{ __('app.machines.confirm_delete') }}')"
     *         x-on:submit="guard($event)" ...>
     *
     * Usage (wire:click) — put the x-* attributes BEFORE the wire:click
     * attribute so this listener registers first and can stop it:
     *   <button x-data="confirmAction('...')" x-on:click="guard($event)"
     *           wire:click="delete">
     */
    window.Alpine.data('confirmAction', (message = 'Are you sure?') => ({
        guard(event) {
            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },
    }));
});
