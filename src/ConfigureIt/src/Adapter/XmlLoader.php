<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_merge;
use function implode;
use function is_array;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;

use LibXMLError;

use function simplexml_load_string;

use SimpleXMLElement;
use Throwable;

use function trim;

/**
 * Parses XML configuration content into a nested array using `ext-simplexml`.
 *
 * Attributes are preserved under an `@attributes` key; repeated sibling tags
 * become a list. The conversion is inherently lossy (XML is not a subset of
 * JSON), so this is a best-effort mapping for configuration use. Never performs
 * merging or validation. Implements the {@see ConfigurationLoaderInterface}
 * port and translates engine failures into the domain {@see ParseException}.
 */
final readonly class XmlLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
    ) {
    }

    public function supports(Format $format): bool
    {
        return Format::XML === $format;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        $useErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($raw);

            if (false === $xml) {
                $messages = array_map(
                    static fn (LibXMLError $error): string => trim($error->message),
                    libxml_get_errors(),
                );
                libxml_clear_errors();

                $detail = [] !== $messages ? implode('; ', $messages) : 'unknown error';

                throw new ParseException(sprintf('Invalid XML configuration: %s', $detail));
            }

            /** @var array<string, mixed> $data */
            $data = $this->toArray($xml);
        } catch (Throwable $e) {
            if ($e instanceof ParseException) {
                throw $e;
            }

            throw new ParseException(sprintf('Invalid XML configuration: %s', $e->getMessage()), 0, $e);
        } finally {
            libxml_use_internal_errors($useErrors);
        }

        return $this->documentFactory->build($data, Format::XML);
    }

    /**
     * @return array<string, mixed>|string
     */
    private function toArray(SimpleXMLElement $element): array|string
    {
        $result = [];

        foreach ($element->children() as $name => $child) {
            $value = $this->toArray($child);
            $attributes = $this->attributes($child);

            if ([] !== $attributes) {
                $value = is_array($value)
                    ? array_merge($value, ['@attributes' => $attributes])
                    : ['@value' => $value, '@attributes' => $attributes];
            }

            if (array_key_exists($name, $result)) {
                if (!is_array($result[$name]) || !array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }

                $result[$name][] = $value;

                continue;
            }

            $result[$name] = $value;
        }

        $attributes = $this->attributes($element);

        if ([] === $result && ($text = trim((string) $element)) !== '') {
            return [] === $attributes ? $text : ['@value' => $text, '@attributes' => $attributes];
        }

        if ([] !== $attributes) {
            $result['@attributes'] = $attributes;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(SimpleXMLElement $element): array
    {
        $attributes = [];

        foreach ($element->attributes() as $key => $value) {
            $attributes[$key] = (string) $value;
        }

        return $attributes;
    }
}
