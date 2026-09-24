<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Adapter\Processor;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\ValidationResultFactory;
use Nette\Schema\Expect;
use Tests\Support\Extension\VirtualFileSystem;

/**
 * M7 / E8.1 — validation severity.
 *
 * Warnings (e.g. deprecations) are non-blocking and surfaced as observations;
 * only errors block resolution unless the builder is told to fail on warnings.
 */
class SeverityTest extends Unit
{
    use VirtualFileSystem;

    private string $dir;

    public function testWarningOnlyIsReportedWithWarningSeverity(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('db', ['host' => 'local-host'], 'base', 10),
        ]);

        $result = $this->warningSchema()->validate($graph);

        verify($result->valid())->true();
        verify($result->hasErrors())->false();
        verify($result->hasWarnings())->true();
        verify($result->warnings()[0]['severity'])->equals('warning');
    }

    public function testSchemaErrorsCarryErrorSeverity(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('db', ['host' => 'local-host'], 'base', 10),
        ]);

        $result = $this->errorSchema()->validate($graph);

        verify($result->valid())->false();
        verify($result->hasErrors())->true();
        verify($result->errors()[0]['severity'])->equals('error');
        verify($result->warnings())->equals([]);
    }

    public function testWarningOnlyResolvesAndEmitsWarningObservation(): void
    {
        $reporter = new ResolutionReporter();

        $resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withSchema($this->warningSchema())
            ->build($reporter)
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('local-host');

        $warnings = array_values(array_filter(
            $reporter->observations(),
            static fn ($o) => 'warning' === $o->level(),
        ));

        verify($warnings)->notEmpty();
    }

    public function testErrorThrowsRegardlessOfFailOnWarnings(): void
    {
        $reporter = new ResolutionReporter();

        $resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withSchema($this->errorSchema())
            ->failOnWarnings(false)
            ->build($reporter)
        ;

        $this->expectException(\ItPack\ConfigureIt\Exception\ValidationException::class);

        $resolver->resolve();
    }

    public function testFailOnWarningsMakesWarningFatal(): void
    {
        $reporter = new ResolutionReporter();

        $resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withSchema($this->warningSchema())
            ->failOnWarnings(true)
            ->build($reporter)
        ;

        $this->expectException(\ItPack\ConfigureIt\Exception\ValidationException::class);

        $resolver->resolve();
    }

    protected function _before(): void
    {
        $this->dir = $this->vfsPath('/cfg-sev-' . uniqid());
        mkdir($this->dir, 0o700, true);
        file_put_contents($this->dir . '/base.php', '<?php return ["db" => ["host" => "local-host"]];');
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }

        rmdir($this->dir);
    }

    private function warningSchema(): NativeConfigurationSchema
    {
        return new NativeConfigurationSchema(
            Expect::structure([
                'db' => Expect::structure([
                    'host' => Expect::string()->deprecated('use db.endpoint instead'),
                ])->otherItems(Expect::mixed()),
            ])->otherItems(Expect::mixed())->castTo('array'),
            new Processor(),
            new ValidationResultFactory()
        );
    }

    private function errorSchema(): NativeConfigurationSchema
    {
        return new NativeConfigurationSchema(
            Expect::structure([
                'db' => Expect::structure([
                    'host' => Expect::int(),
                ])->otherItems(Expect::mixed()),
            ])->otherItems(Expect::mixed())->castTo('array'),
            new Processor(),
            new ValidationResultFactory()
        );
    }
}
