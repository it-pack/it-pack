<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use Nette\Schema\Expect;
use Nette\Schema\Schema as Ne;

/**
 * Reusable Nette Schema fragment describing feature-flag definitions.
 *
 * Lets consumers compose flag definitions into their own schema under a single
 * key (default `feature_flags`) so the resolved graph carries *validated* flag
 * definitions — the "schema-driven" half of E8.2
 * (docs/ADDITIONAL_FEATURES_PLAN.md §8). The evaluation itself stays in Core
 * ({@see \ItPack\ConfigureIt\Core\FlagEvaluator}) and introduces no new source
 * of truth.
 *
 * Usage:
 *   new NativeConfigurationSchema(
 *       Expect::structure([
 *           'feature_flags' => FeatureFlagSchema::structure(),
 *           // ...your own keys...
 *       ]),
 *   );
 *
 * Each flag may be declared as a bare bool (a shortcut for `enabled`) or as a
 * full definition structure:
 *   feature_flags:
 *     newDashboard: true
 *     betaApi:
 *       enabled: true
 *       environments: [staging]
 *       tenants: [acme]
 *       attributes: { plan: enterprise }
 *       rollout: 25
 *       bucketBy: tenant
 */
final class FeatureFlagSchema
{
    /**
     * Schema for a single flag: a bool shortcut or a full definition structure.
     */
    public static function definition(): Ne
    {
        return Expect::anyOf(
            Expect::bool(),
            Expect::structure([
                'enabled' => Expect::bool()->default(false),
                'environments' => Expect::listOf(Expect::string())->default([]),
                'tenants' => Expect::listOf(Expect::string())->default([]),
                'attributes' => Expect::arrayOf(Expect::string())->default([]),
                'rollout' => Expect::int()->min(0)->max(100)->default(100),
                'bucketBy' => Expect::anyOf('tenant', 'user', 'environment')->default('tenant'),
            ]),
        );
    }

    /**
     * Schema for the whole `feature_flags` mapping (flag name => definition).
     */
    public static function structure(): Ne
    {
        return Expect::arrayOf(self::definition());
    }
}
