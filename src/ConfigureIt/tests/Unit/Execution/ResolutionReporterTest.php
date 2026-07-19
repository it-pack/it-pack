<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

class ResolutionReporterTest extends Unit
{
    public function testRecordsOccurrencesInOrder(): void
    {
        $reporter = new ResolutionReporter();

        $reporter->record(new Occurrence('ConfigurationLoaded', 1.0, ['origin' => 'base']));
        $reporter->record(new Occurrence('SnapshotCreated', 2.0));

        $occurrences = $reporter->occurrences();

        verify(count($occurrences))->equals(2);
        verify($occurrences[0]->type())->equals('ConfigurationLoaded');
        verify($occurrences[0]->payload())->equals(['origin' => 'base']);
        verify($occurrences[0]->at())->equals(1.0);
        verify($occurrences[1]->type())->equals('SnapshotCreated');
    }

    public function testObservesDiagnostics(): void
    {
        $reporter = new ResolutionReporter();
        $reporter->observe(new Observation('warning', 'deprecated key used', ['key' => 'x']));

        $observations = $reporter->observations();

        verify(count($observations))->equals(1);
        verify($observations[0]->level())->equals('warning');
        verify($observations[0]->message())->equals('deprecated key used');
        verify($observations[0]->context())->equals(['key' => 'x']);
    }

    public function testCarriesExecutionContext(): void
    {
        $context = new ExecutionContext('testing', ['tenant' => 'acme']);
        $reporter = new ResolutionReporter($context);

        verify($reporter->context()?->environment())->equals('testing');
        verify($reporter->context()?->attribute('tenant'))->equals('acme');
        verify($reporter->context()?->attributes())->equals(['tenant' => 'acme']);
    }
}
