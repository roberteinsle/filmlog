import { Passkeys, PasskeyExistsError, UnexpectedResponseError, UserCancelledError } from '@laravel/passkeys';

/**
 * Passkey-Login und -Verwaltung laufen über Alpine (x-data) in den Blade-Views.
 */
window.Passkeys = Passkeys;
window.PasskeyErrors = { PasskeyExistsError, UnexpectedResponseError, UserCancelledError };
