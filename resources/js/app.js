import './bootstrap';
import { registerOffline, registerServiceWorker } from './offline';

/*
 * PWA + offline queue for an open run (milestone 8). Both are no-ops where
 * the browser lacks the APIs, so the app degrades to "online only" rather
 * than breaking.
 */
registerServiceWorker();
registerOffline();

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
    /*
     * Connection badge (milestone 8). Reads the queue state maintained in
     * offline.js — see that file for what is and is not replayed.
     *
     * Usage: <div x-data="connectionStatus" x-show="! online || queued > 0">
     */
    window.Alpine.data('connectionStatus', () => ({
        online: navigator.onLine,
        queued: 0,
        stranded: 0,

        init() {
            const sync = () => {
                const state = window.brandingOffline;

                this.online = state ? state.online : navigator.onLine;
                this.queued = state ? state.queued : 0;
                this.stranded = state ? state.strandedCount : 0;
            };

            window.addEventListener('offline-queue-changed', sync);
            window.addEventListener('online', sync);
            window.addEventListener('offline', sync);

            sync();
        },

        discardStranded() {
            window.brandingOffline?.discardStranded();
        },
    }));

    window.Alpine.data('confirmAction', (message = 'Are you sure?') => ({
        guard(event) {
            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },
    }));

    /*
     * Signature pad (milestone 5) — finger/stylus capture on a canvas,
     * exported as a PNG data URL.
     *
     * Driven by <x-signature-pad>, which exposes `dataUrl` through
     * x-modelable so the parent scope owns the value:
     *
     *   <div x-data="{ signature: '' }">
     *       <x-signature-pad x-model="signature" />
     *
     * The value is deliberately NOT a Livewire property: a ~20 KB data URL
     * would then ride in the component snapshot on every request in both
     * directions. It is passed as an action argument at submit time instead,
     * and the pad's root carries wire:ignore so a Livewire re-render cannot
     * wipe the canvas mid-signature.
     *
     * Ink is dark on a white pad in BOTH layouts (the kiosk is otherwise
     * dark): the stored PNG has a transparent background and is printed onto
     * a white PDF sheet in milestone 7, so light ink would vanish there.
     */
    window.Alpine.data('signaturePad', () => ({
        dataUrl: '',
        hasInk: false,
        drawing: false,
        canvas: null,
        ctx: null,
        observer: null,

        init() {
            this.canvas = this.$refs.canvas;

            // The pad is usually inside a modal, so it has zero size until it
            // is shown. A ResizeObserver sizes the backing store whenever the
            // element actually gets a box — no guessing when that happens.
            this.observer = new ResizeObserver(() => this.resize());
            this.observer.observe(this.canvas);

            // Server-side reset (e.g. after a successful submit) clears the pad.
            this.$watch('dataUrl', (value) => {
                if (!value && this.hasInk) {
                    this.clear();
                }
            });
        },

        destroy() {
            this.observer?.disconnect();
        },

        /*
         * Match the backing store to the CSS box × devicePixelRatio, or the
         * signature is a blurry, jagged mess on a tablet. Setting width/height
         * resets the 2D context, so every stroke setting is re-applied and any
         * existing ink is redrawn from a snapshot.
         */
        resize() {
            const rect = this.canvas.getBoundingClientRect();

            if (!rect.width || !rect.height) {
                return;
            }

            const ratio = window.devicePixelRatio || 1;
            const width = Math.round(rect.width * ratio);
            const height = Math.round(rect.height * ratio);

            if (this.canvas.width === width && this.canvas.height === height) {
                return;
            }

            const snapshot = this.hasInk ? this.canvas.toDataURL('image/png') : null;

            this.canvas.width = width;
            this.canvas.height = height;

            this.ctx = this.canvas.getContext('2d');
            this.ctx.scale(ratio, ratio);
            this.ctx.lineWidth = 2.5;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.strokeStyle = '#0f172a'; // slate-900

            if (snapshot) {
                const image = new Image();
                image.onload = () => this.ctx.drawImage(image, 0, 0, rect.width, rect.height);
                image.src = snapshot;
            }
        },

        point(event) {
            const rect = this.canvas.getBoundingClientRect();

            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        },

        start(event) {
            if (!this.ctx) {
                this.resize();
            }

            if (!this.ctx) {
                return;
            }

            this.drawing = true;
            this.canvas.setPointerCapture?.(event.pointerId);

            const { x, y } = this.point(event);

            this.ctx.beginPath();
            this.ctx.moveTo(x, y);

            // A single tap is a legitimate (if tiny) mark — dot it, so the
            // pad is never "empty" when the operator clearly touched it.
            this.ctx.lineTo(x, y);
            this.ctx.stroke();

            this.hasInk = true;
        },

        move(event) {
            if (!this.drawing) {
                return;
            }

            const { x, y } = this.point(event);

            this.ctx.lineTo(x, y);
            this.ctx.stroke();
        },

        end(event) {
            if (!this.drawing) {
                return;
            }

            this.drawing = false;
            this.canvas.releasePointerCapture?.(event.pointerId);
            this.commit();
        },

        commit() {
            this.dataUrl = this.hasInk ? this.canvas.toDataURL('image/png') : '';
        },

        clear() {
            this.drawing = false;
            this.hasInk = false;
            this.dataUrl = '';

            if (this.ctx) {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            }
        },
    }));
});
