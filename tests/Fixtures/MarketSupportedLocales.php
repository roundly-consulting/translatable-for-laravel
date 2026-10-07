<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use RoundlyConsulting\Translatable\Contracts\SupportedLocales;

/**
 * A host's own locale source (the binding the config file recommends), returning one more
 * locale than `translatable.locales` lists — including an unannounced market.
 */
final class MarketSupportedLocales implements SupportedLocales
{
    public function supported(): array
    {
        return ['en', 'sk', 'zz-internal-market'];
    }
}
