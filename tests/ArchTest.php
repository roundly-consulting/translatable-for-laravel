<?php

declare(strict_types=1);

use RoundlyConsulting\Testing\Arch\ArchPresets;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Support\TranslationManager;

/**
 * Translatable shipped with NO architecture test at all, so every preset here is a new
 * guard rather than a replacement.
 */
ArchPresets::strictTypes('RoundlyConsulting\Translatable');

/**
 * Two exemptions, both real extension points:
 *
 *  - TranslationManager, the facade target — `Translatable::fake()`-style container swaps
 *    and the package's own manager binding rely on it being replaceable;
 *  - TranslatableException, the base every package exception extends so a host can catch
 *    them uniformly.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Translatable')
    ->ignoring([TranslationManager::class, TranslatableException::class]);

/**
 * Translatable does no cryptography; the ban is a standing guard against a slug hash or a
 * locale token being hand-rolled here rather than taken from crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Translatable');

/**
 * The Dependency Policy as a test. No `alsoAllow`: translatable's `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/**
 * `swappableModelsAreNotFinal` and `modelsResolveThroughSeam` are skipped with cause:
 * translatable ships no Eloquent model and no `*_model` config key — it is a trait
 * (`HasTranslations`) applied to the HOST's models, so there is nothing to swap and both
 * would be structurally inert. Pre-classified by `Swap? = 0`, per the settled rule.
 */
