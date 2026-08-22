# configure-it-psr11

PSR-11 container integration for [ConfigureIt](../../ConfigureIt).

Exposes the resolved, immutable `ConfigurationSnapshotInterface` through any
PSR-11 compliant container — so any framework or library that understands
`Psr\Container\ContainerInterface` can pull configuration out of ConfigureIt
without a bespoke adapter.

## Install

```bash
composer require it-pack/configure-it-psr11
```

## Usage

```php
use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureItPsr11\ConfigureItContainerFactory;

$builder = (new ConfigurationBuilder())
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/config'))
    ->addLoaders(ConfigurationLoaders::default());

$container = ConfigureItContainerFactory::fromBuilder($builder);

// Any PSR-11 consumer can now fetch the snapshot:
$snapshot = $container->get(\ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface::class);
```

The container is intentionally closed: it serves exactly one entry (by default
the `ConfigurationSnapshotInterface` class name; override via the `$entryId`
argument). Any other id throws a `Psr\Container\NotFoundExceptionInterface`.

## Quality gates

```bash
composer analyse      # PHPStan level 9
composer cs:check     # php-cs-fixer
composer test:unit    # PHPUnit
```
