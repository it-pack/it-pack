<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Poller\NativePoller;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;

/**
 * Builds the poller backend selected by capability and PHP version.
 *
 * When PHP 8.6 `Io\Poll` is present and the negotiated descriptor reports
 * {@see CapabilityKind::EdgeTriggeredPolling} as {@see CapabilityStatus::Supported},
 * the native {@see NativePoller} is used; otherwise the {@see SelectPoller}
 * fallback is selected. The scheduler and ports are identical across both.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 */
final class PollerFactory implements PollerFactoryInterface
{
    public function create(CapabilityDescriptor $descriptor): PollerPortInterface
    {
        if (self::nativePollAvailable()
            && $descriptor->statusFor(CapabilityKind::EdgeTriggeredPolling) === CapabilityStatus::Supported) {
            $nativeClass = 'Io\\Poll';
            /** @var \Io\Poll $native */
            $native = new $nativeClass();

            return new NativePoller($native);
        }

        return new SelectPoller();
    }

    /**
     * True when the PHP 8.6 native `Io\Poll` readiness notifier is available.
     */
    public static function nativePollAvailable(): bool
    {
        // Resolved as always-false under PHP < 8.6 analysis; in CI on PHP 8.6 the
        // native `Io\Poll` poller is selected. `class_exists` guards the runtime.
/** @phpstan-ignore-next-line greaterOrEqual.alwaysFalse, booleanAnd.alwaysFalse */
        return \PHP_VERSION_ID >= 80600 && class_exists('Io\\Poll');
    }
}
