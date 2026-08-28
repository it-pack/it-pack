<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\ContentClassifier;
use ItPack\DisenchantIt\Adapter\MagicLiteralVisitor;
use ItPack\DisenchantIt\Adapter\NamingContextExtractor;
use ItPack\DisenchantIt\Contract\ContentClassifierInterface;
use ItPack\DisenchantIt\Contract\MagicLiteralVisitorInterface;
use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\NamingContextExtractorInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantScopeInterface;

final readonly class MagicStringVisitorFactory implements MagicStringVisitorFactoryInterface
{
    public function __construct(
        private NamingContextExtractorInterface $extractor = new NamingContextExtractor(),
        private ContentClassifierInterface $contentClassifier = new ContentClassifier(),
        private int $maxHeredocBodyChars = DisenchantScopeInterface::DEFAULT_MAX_HEREDOC_BODY_CHARS,
    ) {
    }

    public static function create(
        int $maxHeredocBodyChars = DisenchantScopeInterface::DEFAULT_MAX_HEREDOC_BODY_CHARS,
    ): self {
        return new self(maxHeredocBodyChars: $maxHeredocBodyChars);
    }

    public function build(RoleClassifierInterface $classifier, string $code): MagicLiteralVisitorInterface
    {
        return new MagicLiteralVisitor(
            $classifier,
            $this->extractor,
            $code,
            $this->contentClassifier,
            $this->maxHeredocBodyChars,
        );
    }
}
