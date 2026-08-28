<?php

declare(strict_types=1);

/*
 * 11 — Adapter that wraps returned ports (return-wrapper map).
 *
 * A method can return *another* type you also want to port (here Service returns a
 * Collaborator). Left alone, the generated adapter leaks the concrete Collaborator
 * back out. By porting Collaborator first and passing a return-wrapper map, the
 * adapter converts the concrete return value into its port — so your boundary stays
 * free of vendor types end to end.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

// 1) Port the returned type first. This defines _Fixture\CollaboratorInterface and
//    _Fixture\CollaboratorAdapter in one self-contained source.
$collaboratorSource = DeriveIt::deriveAdapter(_Fixture\Collaborator::class);

$tmpCollaborator = sys_get_temp_dir() . '/deriveit_collab_' . md5($collaboratorSource) . '.php';
file_put_contents($tmpCollaborator, $collaboratorSource);
require $tmpCollaborator;

// 2) Derive the Service adapter. The type-substitution map rewrites the returned
//    Collaborator to CollaboratorInterface in the port; the wrapper map tells the
//    adapter to wrap the concrete value in the generated CollaboratorAdapter.
$serviceSource = DeriveIt::deriveAdapter(
    _Fixture\Service::class,
    returnWrapperMap: [
        _Fixture\CollaboratorInterface::class => _Fixture\CollaboratorAdapter::class,
    ],
    config: (new DeriveConfig())->withTypeSubstitutionMap([
        _Fixture\Collaborator::class => _Fixture\CollaboratorInterface::class,
    ]),
);

show(
    'Adapter for Service (returned Collaborator wrapped into its port)',
    $serviceSource,
    'ServiceAdapter::collaborator() now returns CollaboratorInterface and wraps via new '
    . 'CollaboratorAdapter(...). Without the map it would have leaked the concrete Collaborator.',
);

// 3) Prove the wiring: load the adapter and confirm the boundary is concrete-free.
$tmpService = sys_get_temp_dir() . '/deriveit_service_' . md5($serviceSource) . '.php';
file_put_contents($tmpService, $serviceSource);
require $tmpService;

$adapter = new _Fixture\ServiceAdapter(new _Fixture\Service());
$wrapped = $adapter->collaborator();

echo "\033[1;33mWrapped return type:\033[0m " . get_debug_type($wrapped) . "\n";
echo "\033[1;33mImplements the port:\033[0m "
    . (($wrapped instanceof _Fixture\CollaboratorInterface) ? 'yes' : 'NO') . "\n";
