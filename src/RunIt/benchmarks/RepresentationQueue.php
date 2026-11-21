<?php

declare(strict_types=1);

// Standalone microbenchmark, not a replacement for the publisher's regression tests.
final class RepresentationQueue
{
    private array $items = [];
    private int $head = 0;
    private int $tail = 0;
    public int $bytes = 0;
    public array $drops = [];

    public function __construct(private readonly string $kind, private readonly int $limit)
    {
    }

    public function add(array $item, bool $priority = false): void
    {
        while (count($this->items) >= $this->limit || $this->bytes + $item[1] > $this->limit * 2) {
            $key = null;
            if ($priority) {
                foreach ($this->items as $index => $existing) {
                    if (!$existing[2]) {
                        $key = $index;
                        break;
                    }
                }
            }
            $this->remove($key, true);
        }
        if ($this->kind === 'head') {
            $this->items[$this->tail++] = $item;
        } else {
            $this->items[] = $item;
        }
        $this->bytes += $item[1];
    }

    public function remove(?int $key = null, bool $drop = false): array
    {
        if ($key === null && $this->kind === 'array') {
            $item = array_shift($this->items);
        } else {
            if ($key === null && $this->kind === 'head') {
                while (!isset($this->items[$this->head])) {
                    ++$this->head;
                }
                $key = $this->head++;
            }
            $key ??= array_key_first($this->items);
            $item = $this->items[$key];
            unset($this->items[$key]);
            if ($this->kind === 'array') {
                $this->items = array_values($this->items);
            }
        }
        $this->bytes -= $item[1];
        if ($drop) {
            $this->drops[] = $item[0];
        }
        return $item;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function contents(): array
    {
        return array_values($this->items);
    }
}

function fixture(string $kind, int $depth, string $work): RepresentationQueue
{
    $queue = new RepresentationQueue($kind, $depth);
    if ($work !== 'admission') {
        for ($i = 0; $i < $depth; ++$i) {
            $queue->add([$i, 2, $i < intdiv($depth, 2)]);
        }
    }
    return $queue;
}

function exercise(RepresentationQueue $queue, int $depth, string $work): array
{
    $delivered = [];
    if ($work === 'admission') {
        for ($i = 0; $i < $depth; ++$i) {
            $queue->add([$i, 2, false]);
        }
    } elseif ($work === 'drain') {
        while ($queue->count() > 0) {
            $delivered[] = $queue->remove()[0];
        }
    } else {
        for ($i = 0; $i < 64; ++$i) {
            $queue->add([$depth + $i, $work === 'multiple' ? 7 : 2, true], $work === 'priority');
        }
    }
    return [$delivered, $queue->drops, $queue->bytes, $queue->contents()];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$kinds = ['array', 'head', 'sparse'];
foreach ([256, 1024, 4096] as $depth) {
    foreach (['admission', 'drain', 'oldest', 'multiple', 'priority'] as $work) {
        $times = array_fill_keys($kinds, []);
        $expected = null;
        for ($round = 0; $round < 9; ++$round) {
            $order = $round % 2 === 0 ? $kinds : array_reverse($kinds);
            $order = array_merge(array_slice($order, $round % 3), array_slice($order, 0, $round % 3));
            foreach ($order as $kind) {
                $queue = fixture($kind, $depth, $work);
                $start = hrtime(true);
                $result = exercise($queue, $depth, $work);
                $elapsed = (hrtime(true) - $start) / 1000;
                if ($expected === null) {
                    $expected = $result;
                } elseif ($expected !== $result) {
                    throw new RuntimeException("Mismatch: $work $depth $kind");
                }
                if ($round > 0) {
                    $times[$kind][] = $elapsed;
                }
            }
        }
        printf("%s %d", $work, $depth);
        foreach ($kinds as $kind) {
            sort($times[$kind]);
            printf(' %s=%.1f[%.1f-%.1f]', $kind, ($times[$kind][3] + $times[$kind][4]) / 2,
                $times[$kind][0], $times[$kind][7]);
        }
        printf(" us\n");
    }
}

foreach ($kinds as $kind) {
    gc_collect_cycles();
    unset($queue);
    $before = memory_get_usage(false);
    $queue = fixture($kind, 4096, 'oldest');
    $filled = memory_get_usage(false) - $before;
    for ($i = 0; $i < 2048; ++$i) {
        $queue->remove();
    }
    printf("memory %s filled=%d half-drained=%d bytes\n", $kind, $filled, memory_get_usage(false) - $before);
    unset($queue);
}
