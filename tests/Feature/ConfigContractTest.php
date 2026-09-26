<?php

declare(strict_types=1);

/**
 * The config-key contract, pinned in BOTH directions.
 *
 * This replaces ~110 lines of hand-rolled machinery: a token scraper, a recursive
 * leaf-walker, a source-file iterator and a key-shape regex. That code was genuinely good —
 * it already scraped TOKENS rather than raw text, which is the thing media #27 proved
 * matters (a regex over file text is satisfied by a docblock mention and stayed green with
 * the fix reverted) — but it is exactly the code the testing package exists to own once,
 * and the expectation adds guards the local copy never had: an interpolated
 * `config("translatable.{$x}")` is FLAGGED rather than silently scraping as no read, and a
 * stale `allowUnread`/`allowUnshipped` entry that silences nothing is itself a failure.
 *
 *  - forward — every key the code reads is shipped, or a host that publishes the file can
 *    never reach the feature (shops #18: a whole store-credit feature read `shops.payments.*`
 *    while the file shipped `payment.*`, and 330 tests stayed green because the suite set the
 *    same wrong key the code read).
 *  - reverse — every shipped leaf is read, or it is a documented feature that silently does
 *    nothing (media #27's `max_file_size` cap that never applied; alerts #24's
 *    thrice-documented `escalation` key).
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/translatable.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // No `extraReadPrefixes`: translatable has no model seam and no injected-Repository
        // reads — every read is a literal `config('translatable.…')` token the scraper sees
        // natively. Adding the blanket prefix here would be actively harmful: the package
        // throws exception messages that OPEN with a key name ("translatable.strict_locales
        // is enabled…"), and a prefix rule counts any literal under it as a read, wherever
        // it appears — so prose would start satisfying the reverse direction. The local
        // scraper this replaces guarded that with a key-SHAPE regex; the expectation gets
        // there by only counting real `config()` call arguments.
        //
        // No `excludeFromReverse` either: the provider's `contributesToAbout()` closure does
        // real `config('translatable.…')` reads ("a render is not a read" is wrong here).
        //
        // No `sectionVariables` any more: the only section read whole and then by offset was
        // `translatable.slug`, which left with the slug feature — slug config lives in
        // sluggable-for-laravel now, so every remaining read is a literal `config()` token.
    ]);
});
