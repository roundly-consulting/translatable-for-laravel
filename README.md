<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/translatable-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/translatable-for-laravel/main/art/hero.png" alt="Translatable for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/translatable-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/translatable-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/translatable-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/translatable-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/translatable-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/translatable-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Translatable for Laravel

Translatable Eloquent attributes stored as a plain `{ "en": "…", "sk": "…" }` locale map in one
`json`/`jsonb` column. Reads return the current locale through a configurable fallback chain, so
content never renders blank, and per-locale slugs come built in.

## Installation

Requires PHP 8.4 and Laravel 12 or 13. Supported databases: PostgreSQL 12+ and MySQL 8.0.23+
(SQLite for tests). SQL Server is not supported.

```bash
composer require roundly-consulting/translatable-for-laravel
```

## Usage

Give each translatable attribute one locale-map column and list it on the model:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

Schema::create('topics', function (Blueprint $table): void {
    $table->id();
    $table->translatable('name');   // a jsonb column
    $table->timestamps();
});

final class Topic extends Model implements Translatable
{
    use HasTranslations;

    protected $fillable = ['name'];

    /** @var list<string> */
    public array $translatable = ['name'];
}
```

Read and write in the current locale:

```php
$topic = Topic::create(['name' => ['en' => 'Investing']]);

app()->setLocale('sk');
$topic->name;                                    // 'Investing' — falls back to `en`
$topic->missingLocales('name');                  // ['sk']

$topic->setTranslation('name', 'sk', 'Investovanie')->save();
$topic->name;                                    // 'Investovanie'
$topic->getTranslations('name');                 // ['en' => 'Investing', 'sk' => 'Investovanie']
```

The facade handles everything that isn't per-model state:

```php
use RoundlyConsulting\Translatable\Facades\Translatable;

Translatable::usingLocale('en', fn (): string => $topic->name);                     // 'Investing'
$topic->setTranslations('name', Translatable::fromInput($request->input('name')));  // replaces the map with the form's locales
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/translatable-for-laravel](https://roundly-consulting.com/open-source/docs/translatable-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Copyright (c) roundly-consulting. See [LICENSE.md](LICENSE.md).
