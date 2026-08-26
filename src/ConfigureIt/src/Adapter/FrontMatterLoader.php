<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use function class_exists;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;
use Throwable;
use Webuni\FrontMatter\FrontMatter;

/**
 * Parses Markdown Front Matter into a configuration document.
 *
 * The leading front matter block (default `---`) is parsed as YAML metadata and
 * becomes the document body; the remaining content is attached under the
 * `content` key when present. Uses `webuni/front-matter`. Implements the
 * {@see ConfigurationLoaderInterface} port and translates engine failures into
 * the domain {@see ParseException}.
 */
final readonly class FrontMatterLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
        private ?FrontMatter $frontMatter = null,
    ) {
    }

    public function name(): string
    {
        return 'frontmatter-loader';
    }
    public function supports(Format $format): bool
    {
        return Format::FRONTMATTER === $format;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        if (!class_exists(FrontMatter::class)) {
            throw new ParseException('Front Matter support requires the webuni/front-matter package.');
        }

        $frontMatter = $this->frontMatter ?? FrontMatter::createYaml();

        try {
            $document = $frontMatter->parse($raw);
            /** @var array<string, mixed> $data */
            $data = $document->getData();
        } catch (Throwable $e) {
            throw new ParseException(sprintf('Invalid Front Matter configuration: %s', $e->getMessage()), 0, $e);
        }

        $content = $document->getContent();

        if ('' !== $content) {
            $data['content'] = $content;
        }

        return $this->documentFactory->build($data, Format::FRONTMATTER);
    }
}
