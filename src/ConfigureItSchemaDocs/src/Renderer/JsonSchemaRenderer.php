<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Renderer;

use ItPack\ConfigureItSchemaDocs\Model\SchemaNode;

/**
 * Renders a {@see SchemaNode} tree as a JSON-Schema (draft 2020-12) document.
 *
 * The mapping is intentionally conservative: structures become `object`s,
 * `arrayOf`/`listOf` become `additionalProperties`/`items`, scalar unions and
 * enumerations become `anyOf`/`enum`, and `null|` is folded into a nullable
 * `type`. Ranges become `minimum`/`maximum` (or length/item variants) and
 * patterns are preserved verbatim.
 */
final class JsonSchemaRenderer
{
    /**
     * @return array<string, mixed>
     */
    public function render(SchemaNode $root, string $title = 'Configuration'): array
    {
        /** @var array<string, mixed> $schema */
        $schema = $this->nodeToSchema($root);
        $schema['$schema'] = 'https://json-schema.org/draft/2020-12/schema';
        $schema['$id'] = 'https://configure-it.it-pack/'.$this->slug($title);
        $schema['title'] = $title;

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeToSchema(SchemaNode $node): array
    {
        return match ($node->kind) {
            SchemaNode::KIND_OBJECT => $this->objectSchema($node),
            SchemaNode::KIND_ARRAY => $this->arraySchema($node),
            SchemaNode::KIND_ENUM => $this->enumSchema($node),
            SchemaNode::KIND_UNION => $this->unionSchema($node),
            default => $this->scalarSchema($node),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function scalarSchema(SchemaNode $node): array
    {
        /** @var array<string, mixed> $schema */
        $schema = [];

        $type = $node->type;

        if (null !== $type && 'mixed' !== $type) {
            $schema['type'] = $node->nullable ? [$type, 'null'] : $type;
        } elseif ($node->nullable) {
            $schema['type'] = ['null'];
        }

        $schema = array_merge($schema, $this->constraints($node));

        if ($node->hasDefault && null !== $node->default) {
            $schema['default'] = $node->default;
        }

        if ($node->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectSchema(SchemaNode $node): array
    {
        /** @var array<string, mixed> $props */
        $props = [];
        $required = [];

        foreach ($node->properties as $name => $child) {
            $props[$name] = $this->nodeToSchema($child);

            if ($child->required) {
                $required[] = $name;
            }
        }

        /** @var array<string, mixed> $schema */
        $schema = [
            'type' => 'object',
            'properties' => $props,
        ];

        if ([] !== $required) {
            $schema['required'] = $required;
        }

        $schema['additionalProperties'] = null === $node->additionalProperties
            ? false
            : $this->nodeToSchema($node->additionalProperties);

        if ($node->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function arraySchema(SchemaNode $node): array
    {
        /** @var array<string, mixed> $schema */
        $schema = ['type' => 'array'];

        if (null !== $node->items) {
            $schema['items'] = $this->nodeToSchema($node->items);
        } elseif (null !== $node->additionalProperties) {
            $schema['additionalProperties'] = $this->nodeToSchema($node->additionalProperties);
        }

        $schema = array_merge($schema, $this->constraints($node));

        if ($node->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function enumSchema(SchemaNode $node): array
    {
        /** @var array<string, mixed> $schema */
        $schema = ['enum' => $node->enumValues];

        if (null !== $node->type && !$node->nullable) {
            $schema['type'] = $node->type;
        } elseif ($node->nullable) {
            $schema['type'] = [$node->type ?? 'string', 'null'];
        }

        if ($node->deprecated) {
            $schema['deprecated'] = true;
        }

        if ($node->hasDefault && null !== $node->default) {
            $schema['default'] = $node->default;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function unionSchema(SchemaNode $node): array
    {
        $anyOf = [];

        foreach ($node->anyOf as $child) {
            $anyOf[] = $this->nodeToSchema($child);
        }

        /** @var array<string, mixed> $schema */
        $schema = ['anyOf' => $anyOf];

        if ($node->deprecated) {
            $schema['deprecated'] = true;
        }

        if ($node->hasDefault && null !== $node->default) {
            $schema['default'] = $node->default;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function constraints(SchemaNode $node): array
    {
        $constraints = [];

        if (null !== $node->pattern) {
            $constraints['pattern'] = $node->pattern;
        }

        if (null !== $node->range) {
            [$min, $max] = $node->range;

            if ('string' === $node->type) {
                if (null !== $min) {
                    $constraints['minLength'] = (int) $min;
                }

                if (null !== $max) {
                    $constraints['maxLength'] = (int) $max;
                }
            } elseif ('array' === $node->type) {
                if (null !== $min) {
                    $constraints['minItems'] = (int) $min;
                }

                if (null !== $max) {
                    $constraints['maxItems'] = (int) $max;
                }
            } else {
                if (null !== $min) {
                    $constraints['minimum'] = $min;
                }

                if (null !== $max) {
                    $constraints['maximum'] = $max;
                }
            }
        }

        return $constraints;
    }

    private function slug(string $title): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $title) ?? $title;

        return strtolower(trim($slug, '-') ?: 'config');
    }
}
