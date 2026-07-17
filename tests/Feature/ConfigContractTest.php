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
        // No `excludeFromReverse` either: the provider's `contributesToAbout()` closure and
        // its Blueprint-macro registration both do real `config('translatable.…')` reads, so
        // excluding it would discard the only reader of several shipped keys. This is the
        // shape the testing README's own example gets wrong ("a render is not a read") — and
        // it is especially wrong here, because the provider is the ONLY reader of all four
        // `slug.*` leaves.
        //
        // Which is what `sectionVariables` is for. The provider reads the section whole
        // (`$slug = config('translatable.slug', [])`) and then reads its leaves by array
        // offset (`$slug['reserved']`, `$slug['separator']`, …). An offset read is a real
        // read, but it is not a `config()` call, so without this mapping all four leaves
        // scrape as unread and the reverse direction fails on live config. Mapping `$slug`
        // to `translatable.slug` teaches the scraper that `$slug['separator']` IS
        // `translatable.slug.separator`.
        //
        // Deliberately NOT `allowUnread`: these keys are read, and an allow-list entry would
        // assert the opposite — it would also go stale silently the day a leaf really did
        // die.
        'sectionVariables' => [
            'TranslatableServiceProvider.php' => ['$slug' => 'translatable.slug'],
        ],
    ]);
});
