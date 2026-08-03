const SAVE_DEBOUNCE_MILLISECONDS = 2000
const FALLBACK_SAVE_INTERVAL_MILLISECONDS = 30000

/**
 * Draft recovery Alpine component. Two modes, chosen by the server-side store:
 *
 * - "client": drafts live in the browser's localStorage; this component owns
 *   saving, the recovery prompt, restoring, and clearing.
 * - "server": this component only captures the form state (debounced) and
 *   ships it to the page via $wire.storeRecoverableDraft(); the prompt,
 *   restore, and discard are handled server-side by the RecoversDrafts trait.
 */
export default function draftRecovery(config) {
    return {
        initialState: null,
        lastSavedState: null,
        saveTimeoutId: null,
        saveIntervalId: null,
        isRecoveryPending: false,

        init() {
            this.initialState = this.serializeFormData()
            this.lastSavedState = null

            if (config.mode === 'client') {
                this.pruneExpiredDrafts()

                // The recovery prompt is a Filament notification, which needs
                // the notifications Livewire component to be mounted before
                // the `notificationSent` event is dispatched — wait for
                // Livewire to finish booting (with a timeout fallback in case
                // it already has).
                let hasOfferedRecovery = false

                const offerRecoveryOnce = () => {
                    if (hasOfferedRecovery) {
                        return
                    }

                    hasOfferedRecovery = true
                    this.offerRecovery()
                }

                document.addEventListener('livewire:initialized', offerRecoveryOnce, { once: true })
                setTimeout(offerRecoveryOnce, 2000)
            }

            const scheduleSave = () => {
                clearTimeout(this.saveTimeoutId)
                this.saveTimeoutId = setTimeout(() => this.saveDraft(), SAVE_DEBOUNCE_MILLISECONDS)
            }

            document.addEventListener('input', scheduleSave)
            document.addEventListener('change', scheduleSave)
            this.saveIntervalId = setInterval(() => this.saveDraft(), FALLBACK_SAVE_INTERVAL_MILLISECONDS)

            window.addEventListener('draft-recovery-clear', (event) => {
                const key = event.detail?.key ?? config.key

                clearTimeout(this.saveTimeoutId)
                clearInterval(this.saveIntervalId)

                if (config.mode === 'client') {
                    this.removeDraft(key)
                }

                this.lastSavedState = this.serializeFormData()
                this.initialState = this.lastSavedState
            })

            if (config.mode === 'client') {
                window.addEventListener('draft-recovery-restore', (event) => {
                    if ((event.detail?.key ?? null) !== config.key) {
                        return
                    }

                    this.isRecoveryPending = false
                    this.restoreDraft()
                })

                window.addEventListener('draft-recovery-discard', (event) => {
                    if ((event.detail?.key ?? null) !== config.key) {
                        return
                    }

                    this.isRecoveryPending = false
                    this.removeDraft(config.key)
                })
            }
        },

        destroy() {
            clearTimeout(this.saveTimeoutId)
            clearInterval(this.saveIntervalId)
        },

        getFormData() {
            const data = JSON.parse(JSON.stringify(this.$wire.data ?? {}))

            for (const excludedField of config.excludedFields ?? []) {
                delete data[excludedField]
            }

            // Pending upload markers survive only in server mode, where the
            // server can verify the temporary file still exists before a
            // draft is restored; the browser cannot, so a client-mode draft
            // holding a dead marker would break the upload field.
            return config.mode === 'server' ? data : this.stripTemporaryUploads(data)
        },

        stripTemporaryUploads(value) {
            if (typeof value === 'string') {
                return value.startsWith('livewire-file:') || value.startsWith('livewire-files:') ? null : value
            }

            if (Array.isArray(value)) {
                return value.map((item) => this.stripTemporaryUploads(item))
            }

            if (value !== null && typeof value === 'object') {
                return Object.fromEntries(
                    Object.entries(value).map(([key, item]) => [key, this.stripTemporaryUploads(item)]),
                )
            }

            return value
        },

        serializeFormData() {
            return JSON.stringify(this.getFormData())
        },

        saveDraft() {
            if (this.isRecoveryPending) {
                // Never overwrite or delete a recoverable draft while the user
                // hasn't answered the recovery prompt yet.
                return
            }

            const serializedState = this.serializeFormData()

            if (serializedState === this.lastSavedState) {
                return
            }

            if (serializedState === this.initialState) {
                // The form is back to (or still in) its page-load state — a
                // draft would only offer to "recover" what is already there.
                // Only clean up drafts written during this page view; a stored
                // draft from a previous session is the recovery prompt's job.
                if (config.mode === 'client' && this.lastSavedState !== null) {
                    this.removeDraft(config.key)
                }

                this.lastSavedState = serializedState

                return
            }

            if (config.mode === 'server') {
                this.$wire.storeRecoverableDraft(JSON.parse(serializedState))
                this.lastSavedState = serializedState

                return
            }

            try {
                window.localStorage.setItem(config.key, JSON.stringify({
                    savedAt: Date.now(),
                    data: JSON.parse(serializedState),
                }))

                this.lastSavedState = serializedState
            } catch {
                // Storage full or unavailable — drafts are best-effort only.
            }
        },

        readDraft() {
            try {
                const draft = JSON.parse(window.localStorage.getItem(config.key))

                if (! draft || typeof draft !== 'object' || ! draft.data || ! draft.savedAt) {
                    return null
                }

                if (this.isExpired(draft.savedAt)) {
                    this.removeDraft(config.key)

                    return null
                }

                return draft
            } catch {
                return null
            }
        },

        removeDraft(key) {
            try {
                window.localStorage.removeItem(key)
            } catch {
                // Storage unavailable — nothing to clear.
            }
        },

        isExpired(savedAt) {
            return Date.now() - savedAt > config.expiryDays * 24 * 60 * 60 * 1000
        },

        pruneExpiredDrafts() {
            try {
                const keys = []

                for (let index = 0; index < window.localStorage.length; index++) {
                    const key = window.localStorage.key(index)

                    if (key?.startsWith(config.prefix)) {
                        keys.push(key)
                    }
                }

                for (const key of keys) {
                    try {
                        const draft = JSON.parse(window.localStorage.getItem(key))

                        if (! draft?.savedAt || this.isExpired(draft.savedAt)) {
                            window.localStorage.removeItem(key)
                        }
                    } catch {
                        window.localStorage.removeItem(key)
                    }
                }
            } catch {
                // Storage unavailable — nothing to prune.
            }
        },

        offerRecovery() {
            const draft = this.readDraft()

            if (! draft) {
                return
            }

            if (JSON.stringify({ ...JSON.parse(this.initialState), ...draft.data }) === this.initialState) {
                // The draft matches what is already on the page (e.g. it was
                // saved elsewhere in the meantime) — recover nothing.
                this.removeDraft(config.key)

                return
            }

            this.isRecoveryPending = true

            new window.FilamentNotification()
                .title(config.lang.title)
                .body(config.lang.body.replace(':saved_at', new Date(draft.savedAt).toLocaleString()))
                .icon('heroicon-o-document-arrow-up')
                .persistent()
                .actions([
                    new window.FilamentNotificationAction('restore')
                        .label(config.lang.restore)
                        .button()
                        .close()
                        .dispatch('draft-recovery-restore', { key: config.key }),
                    new window.FilamentNotificationAction('discard')
                        .label(config.lang.discard)
                        .color('gray')
                        .close()
                        .dispatch('draft-recovery-discard', { key: config.key }),
                ])
                .send()
        },

        restoreDraft() {
            const draft = this.readDraft()

            if (! draft) {
                return
            }

            const currentData = JSON.parse(JSON.stringify(this.$wire.data ?? {}))

            this.$wire.set('data', { ...currentData, ...draft.data })
        },
    }
}
