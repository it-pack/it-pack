<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer;

use ItPack\ConfigureItExplorer\Graph\FromFileGraphProvider;
use ItPack\ConfigureItExplorer\Graph\GraphProviderInterface;
use ItPack\ConfigureItExplorer\Http\ExplorerController;
use ItPack\ConfigureItExplorer\Server\Request;
use ItPack\ConfigureItExplorer\Server\Response;
use ItPack\ConfigureItExplorer\Server\Router;
use ItPack\ConfigureItExplorer\View\HtmlRenderer;

/**
 * Wires the read-only explorer and dispatches a {@see Request} to a {@see Response}.
 *
 * Framework-free by design (no PSR-7/15 dependency, no framework bias): the only
 * runtime cost is the Core package. The built-in PHP server (`bin/configure-it-explorer`)
 * serves it via `public/index.php`, but the same application object is driven
 * directly in unit tests.
 */
final class ExplorerApplication
{
    public function __construct(
        private Router $router,
    ) {
    }

    public static function bootstrap(GraphProviderInterface $graphs = new FromFileGraphProvider()): self
    {
        $controller = new ExplorerController($graphs);

        $routes = [
            '/' => [$controller, 'dashboard'],
            '/explain' => [$controller, 'explain'],
            '/diff' => [$controller, 'diff'],
            '/graph' => [$controller, 'graph'],
            '/query' => [$controller, 'query'],
        ];

        $router = new Router(
            $routes,
            static fn (Request $request): Response => Response::html(
                (new HtmlRenderer())->error(404, \sprintf('No route for "%s".', $request->path)),
                404,
            ),
        );

        return new self($router);
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
