import { Passkeys, PasskeyExistsError, UnexpectedResponseError, UserCancelledError } from '@laravel/passkeys';

/**
 * Passkey-Login und -Verwaltung laufen über Alpine (x-data) in den Blade-Views.
 */
window.Passkeys = Passkeys;
window.PasskeyErrors = {
    PasskeyExistsError,
    UnexpectedResponseError,
    UserCancelledError,

    /**
     * Kurzer Grund für die Fehlermeldung, z. B. „ (HTTP 422: Invalid credential format.)“.
     */
    describe(error) {
        const reason = [error?.status ? `HTTP ${error.status}` : null, error?.message].filter(Boolean).join(': ');

        return reason ? ` (${reason})` : '';
    },
};
