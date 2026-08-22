<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Renderer;

use ItPack\ConfigureItSchemaDocs\Model\SchemaNode;

/**
 * Renders a {@see SchemaNode} tree as a human-readable Markdown reference.
 *
 * Nested structures are indented as nested bullet lists; arrays show their
 * item/value schema, unions list their options, and enumerations list the
 * allowed literals. Flags (`required`, `nullable`, `deprecated`), defaults and
 * constraints are inlined per field.
 */
final class MarkdownRenderer
{
    public function render(SchemaNode $root, string $title = 'Configuration'): string
    {
        $out = '# '.$title."\n\n";
        $out .= "Generated from a `Nette\\Schema\\Expect` definition.\n\n";

        if (SchemaNode::KIND_OBJECT === $root->kind) {
            $out .= $this->renderProperties($root->properties, 2);
        } else {
            $out .= $this->renderNode('(root)', $root, 2);
        }

        return rtrim($out)."\n";
    }

    /**
     * @param array<string, SchemaNode> $properties
     */
    private function renderProperties(array $properties, int $level): string
    {
        $out = '';

        foreach ($properties as $name => $node) {
            $out .= $this->renderNode((string) $name, $node, $level);
        }

        return $out;
    }

    private function renderNode(string $name, SchemaNode $node, int $level): string
    {
        $indent = str_repeat('  ', max(0, $level - 2));

        $flags = [];

        if ($node->required) {
            $flags[] = 'required';
        }

        if ($node->nullable) {
            $flags[] = 'nullable';
        }

        if ($node->deprecated) {
            $flags[] = 'deprecated';
        }

        $flagText = [] !== $flags ? ' _('.implode(', ', $flags).')_' : '';
        $defaultText = ($node->hasDefault && null !== $node->default)
            ? ' — default: `'.$this->dump($node->default).'`'
            : '';
        $constraintText = $this->constraintLabel($node);

        $line = $indent.'- **'.$name.'** (`'.$this->typeLabel($node).'`)'
            .$flagText.$defaultText.$constraintText."\n";

        if (SchemaNode::KIND_OBJECT === $node->kind && [] !== $node->properties) {
            $line .= $this->renderProperties($node->properties, $level + 1);
        }

        if (SchemaNode::KIND_ARRAY === $node->kind) {
            if (null !== $node->items) {
                $line .= $indent.'  - _items_: `'.$this->typeLabel($node->items)."`\n";
            } elseif (null !== $node->additionalProperties) {
                $line .= $indent.'  - _values_: `'.$this->typeLabel($node->additionalProperties)."`\n";
            }
        }

        if (SchemaNode::KIND_UNION === $node->kind) {
            foreach ($node->anyOf as $index => $child) {
                $line .= $indent.'  - _option '.($index + 1).'_: `'.$this->typeLabel($child)."`\n";
            }
        }

        if (SchemaNode::KIND_ENUM === $node->kind) {
            $literals = array_map($this->dump(...), $node->enumValues);
            $line .= $indent.'  - _one of_: `'.implode('`, `', $literals)."`\n";
        }

        return $line;
    }

    private function typeLabel(SchemaNode $node): string
    {
        return match ($node->kind) {
            SchemaNode::KIND_OBJECT => 'object',
            SchemaNode::KIND_ARRAY => null !== $node->items
                ? 'array<'.$this->typeLabel($node->items).'>'
                : 'map<string, '.(null !== $node->additionalProperties
                    ? $this->typeLabel($node->additionalProperties)
                    : 'mixed').'>',
            SchemaNode::KIND_ENUM => 'enum',
            SchemaNode::KIND_UNION => 'union',
            default => $node->type ?? 'mixed',
        };
    }

    private function constraintLabel(SchemaNode $node): string
    {
        $parts = [];

        if (null !== $node->pattern) {
            $parts[] = 'pattern: `'.$node->pattern.'`';
        }

        if (null !== $node->range) {
            [$min, $max] = $node->range;

            if (null !== $min && null !== $max) {
                $parts[] = 'range: '.$min.'..'.$max;
            } elseif (null !== $min) {
                $parts[] = 'min: '.$min;
            } elseif (null !== $max) {
                $parts[] = 'max: '.$max;
            }
        }

        return [] !== $parts ? ' — '.implode('; ', $parts) : '';
    }

    private function dump(mixed $value): string
    {
        if (is_string($value)) {
            return "'".$value."'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return gettype($value);
    }
}
