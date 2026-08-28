<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use ItPack\DeriveIt\Cli\ArgParser;
use ItPack\DeriveIt\Cli\Preset;
use ItPack\DeriveIt\Generator\DeriveMode;
use ItPack\DeriveIt\Generator\Visibility;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixture\ArgParserSample;

final class ArgParserTest extends TestCase
{
    private ArgParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgParser();
    }

    public function testFlatModeIsDefaultWithSingleTarget(): void
    {
        $input = $this->parser->parse([ArgParserSample::class]);

        self::assertSame(DeriveMode::FLAT, $input->mode);
        self::assertSame([ArgParserSample::class], $input->targets);
    }

    public function testModeParsesAndValidates(): void
    {
        $input = $this->parser->parse(['--mode=family', ArgParserSample::class]);

        self::assertSame(DeriveMode::FAMILY, $input->mode);
    }

    public function testUnknownModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse(['--mode=bogus']);
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse(['--nope=x']);
    }

    public function testValueOptionRequiresAValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse(['--out']);
    }

    public function testBooleanFlagsSetConfig(): void
    {
        $input = $this->parser->parse([
            '--include-static',
            '--include-magic',
            '--include-constants',
            '--protected',
            '--preserve-docblocks',
            '--audit-leakage',
            '--fail-on-empty',
            '--attribute-pass-through',
            '--throws-capture',
            ArgParserSample::class,
        ]);

        $config = $input->config;
        self::assertTrue($config->includeStatic);
        self::assertTrue($config->includeMagic);
        self::assertTrue($config->includeConstants);
        self::assertSame(Visibility::PROTECTED, $config->visibility);
        self::assertTrue($config->preserveDocblocks);
        self::assertTrue($config->auditLeakage);
        self::assertTrue($config->failOnEmpty);
        self::assertTrue($config->attributePassThrough);
        self::assertTrue($config->throwsCapture);
    }

    public function testListOptionsAccumulateAcrossRepeats(): void
    {
        $input = $this->parser->parse([
            '--skip=a,b',
            '--skip=c',
            '--only=d',
            '--owned-attr=e',
            '--re-expose=f,g',
            '--re-expose=h',
        ]);

        self::assertSame(['a', 'b', 'c'], $input->config->skipMethods);
        self::assertSame(['d'], $input->config->onlyMethods);
        self::assertSame(['e'], $input->config->ownedAttributes);
        self::assertSame(['f', 'g', 'h'], $input->config->reExposeOwnedInterfaces);
    }

    public function testPairOptionsAccumulateAcrossRepeats(): void
    {
        $input = $this->parser->parse([
            '--subst=A=B',
            '--subst=C=D',
            '--rename=e=f',
            '--wrapper=G=H',
            '--ns-prefix=I=J',
        ]);

        self::assertSame(['A' => 'B', 'C' => 'D'], $input->config->typeSubstitutionMap);
        self::assertSame(['e' => 'f'], $input->config->methodRenames);
        self::assertSame(['G' => 'H'], $input->wrapperMap);
        self::assertSame(['I' => 'J'], $input->namespacePrefixMap);
    }

    public function testBaseDirIsCaptured(): void
    {
        $input = $this->parser->parse(['--base-dir=/tmp/sandbox', ArgParserSample::class]);

        self::assertSame('/tmp/sandbox', $input->baseDir);
    }

    public function testVerboseFlagIsCaptured(): void
    {
        $plain = $this->parser->parse([ArgParserSample::class]);
        $verbose = $this->parser->parse(['--verbose', ArgParserSample::class]);

        self::assertFalse($plain->verbose);
        self::assertTrue($verbose->verbose);
    }

    public function testEqualsAndSpaceValueFormsAreEquivalent(): void
    {
        $equals = $this->parser->parse(['--out=file.php', ArgParserSample::class]);
        $space = $this->parser->parse(['--out', 'file.php', ArgParserSample::class]);

        self::assertSame($equals->out, $space->out);
        self::assertSame('file.php', $space->out);
    }

    public function testValueOptionDoesNotConsumeFollowingFlag(): void
    {
        // `--out` followed immediately by another option must be treated as missing
        // its required value rather than swallowing the next flag.
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse(['--out', '--mode=flat', ArgParserSample::class]);
    }

    public function testPresetsMapToTheirConfigFlags(): void
    {
        $strict = $this->parser->parse(['--preset=strict'])->config;
        self::assertTrue($strict->failOnEmpty);
        self::assertTrue($strict->auditLeakage);
        self::assertTrue($strict->substituteSelfStatic);

        $adapter = $this->parser->parse(['--preset=adapter'])->config;
        self::assertTrue($adapter->includeStatic);
        self::assertTrue($adapter->substituteSelfStatic);

        $family = $this->parser->parse(['--preset=family'])->config;
        self::assertTrue($family->substituteSelfStatic);
        self::assertFalse($family->includeStatic);
    }

    public function testExplicitFlagOverridesPresetDefault(): void
    {
        $input = $this->parser->parse(['--preset=family', '--include-static']);

        self::assertTrue($input->config->substituteSelfStatic);
        self::assertTrue($input->config->includeStatic);
    }

    public function testUnknownPresetMessageEnumeratesValidValues(): void
    {
        try {
            $this->parser->parse(['--preset=bogus']);
            self::fail('expected an InvalidArgumentException for the unknown preset');
        } catch (\InvalidArgumentException $e) {
            foreach (Preset::values() as $value) {
                self::assertStringContainsString($value, $e->getMessage());
            }
        }
    }
}
