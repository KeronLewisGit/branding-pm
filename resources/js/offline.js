/*
 * Offline tolerance for an open run (milestone 8).
 *
 * SPEC §Non-Functional asks for a PWA where "a run already open must remain
 * completable if Wi-Fi drops mid-shift, queueing responses in IndexedDB and
 * syncing on reconnect, with a clear 'unsynced' badge".
 *
 * How this works, and — just as importantly — what it refuses to do:
 *
 *  1. Every Livewire request that fails for NETWORK reasons (not a 4xx/5xx —
 *     a server that answered is not offline) is stashed in IndexedDB with the
 *     exact payload that failed.
 *  2. The badge shows the queue depth, so an operator can see that the tablet
 *     is holding answers rather than assuming they are saved.
 *  3. On `online`, the queue is replayed in order, oldest first, then the
 *     component refreshes itself from the server.
 *
 *  4. Replay only happens IN THE SAME PAGE SESSION. A Livewire payload
 *     carries a snapshot of component state; after a reload that snapshot is
 *     stale and replaying it would write yesterday's state over today's. So
 *     if the page is reloaded while the queue is non-empty, nothing is
 *     replayed — instead `pendingFromEarlierSession` goes true and the run
 *     form shows a red banner telling the operator exactly which answers were
 *     never sent and to check them. Losing an answer loudly beats writing a
 *     wrong one silently.
 *
 *  5. Nothing here caches or replays a submission. Submitting a checklist
 *     takes a signature and a PIN, and a blind replay could double-submit or
 *     sign a run the operator had changed their mind about.
 *
 * Replay is safe for item answers precisely because RunForm's actions are
 * absolute-state and idempotent by design ("status = X", never "toggle") —
 * see the RunForm class docblock.
 */

const DB_NAME = 'branding-pm-offline';
const DB_VERSION = 1;
const STORE = 'queued-requests';

/** @returns {Promise<IDBDatabase>} */
function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function withStore(mode, callback) {
    return openDb().then(
        (db) =>
            new Promise((resolve, reject) => {
                const tx = db.transaction(STORE, mode);
                const result = callback(tx.objectStore(STORE));

                tx.oncomplete = () => resolve(result?.result ?? result);
                tx.onerror = () => reject(tx.error);
            })
    );
}

export const offlineStore = {
    add(entry) {
        return withStore('readwrite', (store) => store.add(entry));
    },

    all() {
        return withStore('readonly', (store) => store.getAll());
    },

    remove(id) {
        return withStore('readwrite', (store) => store.delete(id));
    },

    clear() {
        return withStore('readwrite', (store) => store.clear());
    },
};

/**
 * A request that never reached the server. A 422 or a 500 means the server
 * answered — that is not an offline condition and must not be queued.
 */
function isNetworkFailure(status) {
    return status === undefined || status === null || status === 0;
}

export function registerOffline() {
    if (!('indexedDB' in window)) {
        return;
    }

    // The page session id lets us tell "queued a moment ago in this tab" from
    // "queued before a reload", which is the difference between safe to
    // replay and not (point 4 above).
    const sessionId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    window.brandingOffline = {
        sessionId,
        queued: 0,
        strandedCount: 0,
        online: navigator.onLine,
    };

    const notify = () => window.dispatchEvent(new CustomEvent('offline-queue-changed'));

    const refreshCounts = async () => {
        const entries = await offlineStore.all();

        window.brandingOffline.queued = entries.filter((e) => e.sessionId === sessionId).length;
        window.brandingOffline.strandedCount = entries.filter((e) => e.sessionId !== sessionId).length;

        notify();
    };

    window.addEventListener('online', () => {
        window.brandingOffline.online = true;
        notify();
        replay();
    });

    window.addEventListener('offline', () => {
        window.brandingOffline.online = false;
        notify();
    });

    /** Replay this session's queue, oldest first, stopping at the first failure. */
    async function replay() {
        const entries = (await offlineStore.all())
            .filter((entry) => entry.sessionId === sessionId)
            .sort((a, b) => a.id - b.id);

        for (const entry of entries) {
            try {
                const response = await fetch(entry.url, {
                    method: 'POST',
                    headers: entry.headers,
                    body: entry.body,
                });

                if (!response.ok) {
                    // The server answered and refused. Replaying again will
                    // not help; drop it and let the refresh below show the
                    // operator the real state.
                    await offlineStore.remove(entry.id);

                    continue;
                }

                await offlineStore.remove(entry.id);
            } catch {
                return; // still offline — keep the rest of the queue
            }
        }

        await refreshCounts();

        // Pull authoritative state back from the server rather than trusting
        // the optimistic local view.
        if (window.Livewire) {
            window.Livewire.all().forEach((component) => component.$refresh());
        }
    }

    window.brandingOffline.replay = replay;

    window.brandingOffline.discardStranded = async () => {
        const entries = await offlineStore.all();

        await Promise.all(
            entries.filter((entry) => entry.sessionId !== sessionId).map((entry) => offlineStore.remove(entry.id))
        );

        await refreshCounts();
    };

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ uri, options, fail }) => {
            fail(({ status, preventDefault }) => {
                if (!isNetworkFailure(status)) {
                    return; // a real server response — let Livewire handle it
                }

                // Swallow Livewire's "page expired" dialog: the page has not
                // expired, the tablet is in a dead spot.
                preventDefault();

                offlineStore
                    .add({
                        sessionId,
                        url: uri,
                        headers: options.headers,
                        body: options.body,
                        queuedAt: Date.now(),
                    })
                    .then(refreshCounts);
            });
        });
    });

    refreshCounts();
}

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // A tablet with the worker blocked still runs the app online;
            // there is nothing useful to say to an operator here.
        });
    });
}
