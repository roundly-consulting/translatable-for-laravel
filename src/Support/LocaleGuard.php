<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;

/**
 * The single source of truth for validating locale keys and SQL identifiers before they
 * reach a JSON-path column expression or a raw DDL statement. Locale keys and table/column
 * names are never parameter-bound in those positions, so they must be allowlisted here.
 */
final class LocaleGuard
{
    /**
     * A BCP-47-like locale key: a 2–3 letter primary subtag plus optional `_`/`-` subtags.
     * Matches `en`, `sk`, `en_US`, `pt-BR`, `zh_Hans_CN`; rejects anything with quotes,
     * spaces, SQL, or markup.
     */
    public const FORMAT = '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/';

    /**
     * A bare SQL identifier (table/column/index name): letters, digits and underscores only,
     * not starting with a digit.
     */
    public const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public static function isValid(string $locale): bool
    {
        return preg_match(self::FORMAT, $locale) === 1;
    }

    /**
     * Assert a locale key is well-formed, and — when strict mode is on — that it is supported.
     *
     * @param  list<string>|null  $supported  the supported set to enforce in strict mode
     */
    public static function ensure(string $locale, bool $strict = false, ?array $supported = null): string
    {
        if (! self::isValid($locale)) {
            throw InvalidLocaleException::forFormat($locale);
        }

        if ($strict && $supported !== null && ! in_array($locale, $supported, true)) {
            throw InvalidLocaleException::notSupported($locale);
        }

        return $locale;
    }

    public static function isValidIdentifier(string $identifier): bool
    {
        return preg_match(self::IDENTIFIER, $identifier) === 1;
    }

    public static function ensureIdentifier(string $identifier, string $kind = 'identifier'): string
    {
        if (! self::isValidIdentifier($identifier)) {
            throw new TranslatableException(
                "Invalid {$kind} [{$identifier}]; only letters, digits and underscores are allowed."
            );
        }

        return $identifier;
    }
}
