# configure-it-symfony

Symfony integration for [ConfigureIt](../../ConfigureIt).

Provides a bundle + DI extension that wires the `ConfigurationBuilder` from your
bundle configuration and exposes the resolved, immutable
`ConfigurationSnapshotInterface` as a service — ready to be injected anywhere.

## Install

```bash
composer require it-pack/configure-it-symfony
```

## Register the bundle

```php
// config/bundles.php
return [
    // ...
    ItPack\ConfigureItSymfony\ConfigureItBundle::class => ['all' => true],
];
```

## Configure

```yaml
# config/packages/configure_it.yaml
configure_it:
  sources:
    - '%kernel.project_dir%/config/app'
  environment: '%kernel.environment%'
  # schema: App\Config\AppSchema   # optional Nette\Schema definition
  # cache: '%kernel.cache_dir%/configure-it'  # optional compiled artifact
```

## Consume

```php
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;

final class StatusController
{
    public function __construct(
        private readonly ConfigurationSnapshotInterface $config,
    ) {}

    public function index(): Response
    {
        $name = $this->config->string('service.name');
        // ...
    }
}
```

The configured `ConfigurationBuilder` is also available as a public service
(`ItPack\ConfigureIt\Core\ConfigurationBuilder`) for advanced callers.

## Quality gates

```bash
composer analyse      # PHPStan level 9
composer cs:check     # php-cs-fixer
composer test:unit    # PHPUnit
```
