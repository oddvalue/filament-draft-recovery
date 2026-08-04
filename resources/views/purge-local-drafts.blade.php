{{-- Clears every localStorage draft the package has written, so drafts never
     outlive a logout on a shared machine. Rendered on the first panel page
     after a logout (flagged via the purge cookie) when purge_on_logout is
     enabled. --}}
<script>
    (() => {
        const prefix = @js($keyPrefix);

        try {
            const keys = [];

            for (let index = 0; index < window.localStorage.length; index++) {
                const key = window.localStorage.key(index);

                if (key && key.startsWith(prefix)) {
                    keys.push(key);
                }
            }

            for (const key of keys) {
                window.localStorage.removeItem(key);
            }
        } catch {
            // Storage unavailable — nothing to purge.
        }
    })();
</script>
