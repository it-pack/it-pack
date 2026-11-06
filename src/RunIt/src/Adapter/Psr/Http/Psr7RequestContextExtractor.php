<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use ItPack\RunIt\Adapter\Psr\Contract\HttpContextExtractorInterface;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;
use ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Derives RunIt execution inputs from a PSR-7 server request.
 *
 * The execution name is `METHOD /normalized-path`. Path segments that are purely
 * numeric or UUID-like are replaced with `{id}` so that request URLs never become
 * a high-cardinality execution-name dimension. The correlation identifier is read
 * from a configurable request header (default `X-Correlation-Id`) and carried in
 * the initial execution context.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class Psr7RequestContextExtractor implements HttpContextExtractorInterface
{
    public function __construct(
        private string $kind = 'http.request',
        private string $correlationHeader = CorrelationKeys::HTTP_CORRELATION_HEADER,
        private string $cardinalityPlaceholder = 'id',
    ) {
    }

    public function extract(ServerRequestInterface $request): HttpExecutionContextInterface
    {
        $correlationId = $request->getHeaderLine($this->correlationHeader);
        $correlationId = $correlationId === '' ? null : $correlationId;

        $context = ExecutionContext::empty();

        if ($correlationId !== null) {
            $context = $context->with(
                ContextEntry::public(
                    ContextKey::fromString(CorrelationKeys::CONTEXT_CORRELATION_KEY),
                    $correlationId,
                ),
            );
        }

        return new HttpExecutionContext(
            ExecutionName::fromString($this->deriveName($request)),
            ExecutionKind::fromString($this->kind),
            $correlationId,
            $context,
        );
    }

    private function deriveName(ServerRequestInterface $request): string
    {
        $raw = strtolower($request->getMethod()) . ' ' . $this->normalizePath($request->getUri()->getPath());

        return $this->toValidName($raw);
    }

    /**
     * Collapses the raw "METHOD /path" string into a bounded, valid execution name:
     * only letters, digits, dot, dash, and underscore survive, runs are collapsed,
     * and the whole value is lower-cased.
     */
    private function toValidName(string $raw): string
    {
        $collapsed = preg_replace('/[^a-z0-9._-]/', '.', strtolower($raw)) ?? '';
        $collapsed = preg_replace('/\.+/', '.', $collapsed) ?? '';

        return trim($collapsed, '.');
    }

    private function normalizePath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        $normalized = array_map(
            fn (string $segment): string => $this->isCardinalitySegment($segment)
                ? $this->cardinalityPlaceholder
                : $segment,
            $segments,
        );

        return '/' . implode('/', $normalized);
    }

    private function isCardinalitySegment(string $segment): bool
    {
        if ($segment === '') {
            return false;
        }

        if (preg_match('/^\d+$/', $segment) === 1) {
            return true;
        }
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment) === 1;
    }
}
