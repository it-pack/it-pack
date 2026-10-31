<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$scheduler = $fixture['scheduler'];

echo "=== IO Await Primitives (V.9) ===\n";

// V.9.1 — awaitReadable()/awaitWritable() suspend the running task until the
// stream is ready or the monotonic deadline elapses, without blocking the
// scheduler. Works with any PollerPortInterface implementation.
$pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

$readHandle = $scheduler->registerStream($pair[0]);
$writeStream = $pair[1];

$scheduler->spawn(static function () use ($scheduler, $readHandle): void {
    echo "  [task-1] awaiting readable...\n";
    $ready = $scheduler->awaitReadable($readHandle, Duration::ofSeconds(2));
    echo '  [task-1] readable=', $ready ? 'true' : 'false', "\n";
});

$scheduler->spawn(static function () use ($writeStream): void {
    usleep(50_000); // give the scheduler a turn to reach the await point
    fwrite($writeStream, 'hello');
});

$scheduler->run();

fclose($pair[0]);
fclose($pair[1]);

// V.9.2 — awaitWritable returns false on monotonic timeout.
$pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

$writeHandle = $scheduler->registerStream($pair[0]);

$scheduler->spawn(static function () use ($scheduler, $writeHandle): void {
    echo "  [task-2] awaiting writable...\n";
    $ready = $scheduler->awaitWritable($writeHandle, Duration::ofNanoseconds(1));
    echo '  [task-2] writable=', $ready ? 'true' : 'false', "\n";
});

$scheduler->run();

fclose($pair[0]);
fclose($pair[1]);

echo "=== IO await primitives demonstrated ===\n";
echo "  - awaitReadable(StreamPollHandleInterface, ?DurationInterface): bool\n";
echo "  - awaitWritable(StreamPollHandleInterface, ?DurationInterface): bool\n";
echo "  - Monotonic deadline timer expires -> resumes with false\n";
echo "  - Poller signals readiness -> resumes with true\n";
echo "  - Handle released on terminal transition (Assertion C)\n";
