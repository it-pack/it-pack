<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs;

use ItPack\ConfigureItSchemaDocs\Model\SchemaNode;
use ItPack\ConfigureItSchemaDocs\Renderer\JsonSchemaRenderer;
use ItPack\ConfigureItSchemaDocs\Renderer\MarkdownRenderer;
use Nette\Schema\Schema;

/**
 * Facade: turn a `Nette\Schema\Expect` definition into Markdown docs or a
 * JSON-Schema document.
 *
 * Usage:
 *   $docs = SchemaDocGenerator::fromExpect(
 *       Expect::structure(['db' => Expect::string()->required()]),
 *       'App Config',
 *   );
 *   echo $docs->toMarkdown();
 *   file_put_contents('schema.json', $docs->toJsonSchemaJson());
 */
final class SchemaDocGenerator
{
    private function __construct(
        private SchemaNode $root,
        private string $title,
    ) {
    }

    public static function fromExpect(Schema $schema, string $title = 'Configuration'): self
    {
        return new self((new SchemaIntrospector())->introspect($schema), $title);
    }

    public function withTitle(string $title): self
    {
        return new self($this->root, $title);
    }

    public function toMarkdown(): string
    {
        return (new MarkdownRenderer())->render($this->root, $this->title);
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        return (new JsonSchemaRenderer())->render($this->root, $this->title);
    }

    public function toJsonSchemaJson(): string
    {
        $encoded = json_encode(
            $this->toJsonSchema(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return is_string($encoded) ? $encoded : '{}';
    }
}
