<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Contracts;

interface SupportedLocales
{
    /**
     * The locales the host application supports, as a plain list.
     *
     * @return list<string>
     */
    public function supported(): array;
}
