<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * How far the fallback chain reaches when reading a translated value.
 */
enum FallbackMode: string
{
    use Helpers;

    // Exact requested locale only — no fallback.
    case None = 'none';

    // Exact requested locale, then the configured fallback locale.
    case Fallback = 'fallback';

    // Exact requested locale, then the fallback locale, then the first available
    // value in the map (content never renders blank — R4).
    case Any = 'any';
}
