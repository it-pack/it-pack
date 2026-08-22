<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\View;

/**
 * Dependency-free HTML presenter for the read-only explorer.
 *
 * Every dynamic value is escaped with {@see esc()}; the explorer is an
 * unauthenticated local tool and must never reflect user input back unescaped.
 * Sensitive values are already masked to `****` by the Core's redacting renderer
 * before they reach this layer, so nothing secret is rendered in clear text.
 */
final class HtmlRenderer
{
    /**
     * @param list<array{label: string, href: string}> $nav
     */
    public function page(string $title, string $content, array $nav = []): string
    {
        $navHtml = '';

        foreach ($nav as $item) {
            $navHtml .= \sprintf(
                '<a class="nav-link" href="%s">%s</a>',
                $this->esc($item['href']),
                $this->esc($item['label']),
            );
        }

        return \sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>%s — ConfigureIt Explorer</title><style>%s</style></head>'
            .'<body><header class="topbar"><span class="brand">ConfigureIt Explorer</span>'
            .'<nav class="nav">%s</nav></header><main>%s</main>'
            .'<footer class="foot">Read-only · built on ConfigurationInspector / GraphVisualizer / ConfigurationDiffer</footer>'
            .'</body></html>',
            $this->esc($title),
            $this->css(),
            $navHtml,
            $content,
        );
    }

    public function error(int $code, string $message): string
    {
        return $this->page(
            (string) $code,
            \sprintf('<div class="card"><h1>%d</h1><p>%s</p></div>', $code, $this->esc($message)),
        );
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): string
    {
        $head = '';

        foreach ($headers as $header) {
            $head .= \sprintf('<th>%s</th>', $this->esc($header));
        }

        $body = '';

        foreach ($rows as $row) {
            $body .= '<tr>';

            foreach ($row as $cell) {
                $body .= \sprintf('<td>%s</td>', $this->esc((string) $cell));
            }

            $body .= '</tr>';
        }

        return \sprintf('<table class="grid"><thead><tr>%s</tr></thead><tbody>%s</tbody></table>', $head, $body);
    }

    /**
     * @param list<string> $lines
     */
    public function pre(array $lines): string
    {
        return \sprintf('<pre class="code">%s</pre>', $this->esc(\implode("\n", $lines)));
    }

    /**
     * @param array<string, string> $fields name => label
     */
    public function form(string $action, array $fields, string $submit): string
    {
        $inputs = '';

        foreach ($fields as $name => $label) {
            $inputs .= \sprintf(
                '<label>%s<input type="text" name="%s" placeholder="%s"></label>',
                $this->esc($label),
                $this->esc($name),
                $this->esc($name),
            );
        }

        return \sprintf(
            '<form class="form" method="get" action="%s">%s<button type="submit">%s</button></form>',
            $this->esc($action),
            $inputs,
            $this->esc($submit),
        );
    }

    public function esc(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function css(): string
    {
        return <<<'CSS'
            :root { color-scheme: light dark; }
            * { box-sizing: border-box; }
            body { margin: 0; font: 14px/1.5 system-ui, sans-serif; background: #0f1115; color: #e6e6e6; }
            .topbar { display: flex; align-items: center; gap: 1rem; padding: .75rem 1.25rem; background: #161a22; border-bottom: 1px solid #262c38; }
            .brand { font-weight: 700; letter-spacing: .02em; }
            .nav { display: flex; gap: .5rem; flex-wrap: wrap; }
            .nav-link { color: #9fd0ff; text-decoration: none; padding: .25rem .5rem; border-radius: 6px; }
            .nav-link:hover { background: #1d2430; }
            main { padding: 1.25rem; max-width: 980px; margin: 0 auto; }
            .card { background: #161a22; border: 1px solid #262c38; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
            .card h1, .card h2 { margin-top: 0; }
            .form { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; background: #161a22; border: 1px solid #262c38; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
            .form label { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; color: #aab2c0; }
            .form input { padding: .45rem .6rem; border-radius: 6px; border: 1px solid #2c3340; background: #0f1115; color: #e6e6e6; min-width: 220px; }
            .form button { padding: .5rem 1rem; border: 0; border-radius: 6px; background: #2f6fed; color: #fff; cursor: pointer; }
            .grid { width: 100%; border-collapse: collapse; background: #161a22; border: 1px solid #262c38; border-radius: 10px; overflow: hidden; }
            .grid th, .grid td { text-align: left; padding: .5rem .75rem; border-bottom: 1px solid #262c38; }
            .grid th { background: #1d2430; }
            .code { background: #0b0d12; border: 1px solid #262c38; border-radius: 10px; padding: 1rem; overflow: auto; white-space: pre; }
            .hint { color: #8b94a3; font-size: .85rem; }
            code { background: #1d2430; padding: .1rem .35rem; border-radius: 4px; }
            .foot { padding: 1rem 1.25rem; color: #6b7280; font-size: .8rem; }
CSS;
    }
}
