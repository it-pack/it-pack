<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Server;

use Closure;
use ItPack\ConfigureItExplorer\View\HtmlRenderer;

/**
 * Maps an exact request path to a handler and produces a 404 when none matches.
 *
 * The explorer exposes a small, fixed set of read-only routes, so a simple
 * exact-match table is sufficient (no regex router, no framework dependency).
 */
final class Router
{
    /**
     * @param array<string, callable(Request): Response> $routes
     */
    public function __construct(
        private array $routes,
        private ?Closure $notFound = null,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->path] ?? null;

        if (\is_callable($handler)) {
            return $handler($request);
        }

        if (null !== $this->notFound) {
            return ($this->notFound)($request);
        }

        return Response::html(
            (new HtmlRenderer())->error(404, \sprintf('No route for "%s".', $request->path)),
            404,
        );
    }
}
