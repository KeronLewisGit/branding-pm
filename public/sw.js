/*
 * Service worker (milestone 8).
 *
 * Scope, deliberately narrow — SPEC §Non-Functional: "make it a PWA … A run
 * already open must remain completable if Wi-Fi drops mid-shift … Do not
 * attempt full offline-first sync of master data — scope it to the open run."
 *
 * So this worker:
 *   - precaches the app shell (built CSS/JS, the offline page, the manifest)
 *     so a tablet that loses Wi-Fi still renders the page it is on;
 *   - serves built assets cache-first (they are content-hashed by Vite, so a
 *     cached asset is never stale — a new build has a new filename);
 *   - serves navigations network-first, falling back to the cached page and
 *     then to /offline.html;
 *   - NEVER caches Livewire's /livewire/update POSTs or any other write.
 *     Queuing those is the job of the page (see `offlineQueue` in
 *     resources/js/app.js), because only the page knows which actions are
 *     safe to replay — the run form's actions are absolute-state and
 *     idempotent by design (see the RunForm docblock), which is what makes
 *     replay safe at all.
 *
 * What this does NOT do: sync master data, cache other runs, or let an
 * operator start a run they had not already opened. That is the scope the
 * spec asked for, and pretending otherwise would be worse than being offline.
 */

const VERSION = 'v1';
const SHELL_CACHE = `branding-pm-shell-${VERSION}`;
const PAGE_CACHE = `branding-pm-pages-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [OFFLINE_URL, '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== SHELL_CACHE && key !== PAGE_CACHE)
                        .map((key) => caches.delete(key))
                )
            )
            .then(() => self.clients.claim())
    );
});

const isAsset = (url) =>
    url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/');

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Writes are never cached and never replayed by the worker. A queued
    // POST replayed blind could double-submit a checklist.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Vite hashes asset filenames, so cache-first is safe: a changed asset is
    // a different URL.
    if (isAsset(url)) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));

                        return response;
                    })
            )
        );

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy));

                    return response;
                })
                .catch(() =>
                    caches
                        .match(request)
                        .then((cached) => cached || caches.match(OFFLINE_URL))
                )
        );
    }
});
