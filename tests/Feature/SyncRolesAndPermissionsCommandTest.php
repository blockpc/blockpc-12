<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Blockpc\App\Console\Classes\PermissionList;
use Blockpc\App\Console\Classes\RoleList;
use Blockpc\App\Console\Services\PermissionSynchronizerService;
use Blockpc\App\Console\Services\RoleSynchronizerService;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

uses()->group('sistema', 'permissions', 'roles');

function permissionDefinition(string $name, string $display = 'Display', string $guard = 'web'): array
{
    return [$name, 'grupo', "Descripción {$name}", $display, $guard];
}

function roleDefinition(string $name, array $permissions = [], string $guard = 'web', string $display = 'Rol'): array
{
    return [
        'name' => $name,
        'display_name' => $display,
        'description' => "Descripción {$name}",
        'permissions' => $permissions,
        'guard_name' => $guard,
    ];
}

function bindDefinitions(array $permissions, array $roles): void
{
    app()->bind(PermissionSynchronizerService::class, fn () => new PermissionSynchronizerService($permissions));
    app()->bind(RoleSynchronizerService::class, fn () => new RoleSynchronizerService($roles));
}

// Permisos

it('crea los permisos faltantes y actualiza los metadatos existentes', function () {
    Permission::create(['name' => 'x edit', 'guard_name' => 'web', 'key' => 'viejo', 'display_name' => 'Viejo']);

    $sync = new PermissionSynchronizerService([
        permissionDefinition('x edit', 'Nuevo'),
        permissionDefinition('x create'),
    ]);

    expect($sync->getMissing())->toHaveCount(1)
        ->and($sync->getOutdated())->toHaveCount(1);

    $sync->sync();

    expect($sync->getMissing())->toBeEmpty()
        ->and($sync->getOutdated())->toBeEmpty();

    $edit = Permission::where('name', 'x edit')->first();
    expect($edit->display_name)->toBe('Nuevo')->and($edit->key)->toBe('grupo');
});

it('sincronizar permisos dos veces no duplica registros', function () {
    $sync = new PermissionSynchronizerService([permissionDefinition('x edit')]);

    $sync->sync();
    $sync->sync();

    expect(Permission::where('name', 'x edit')->count())->toBe(1);
});

it('distingue permisos con el mismo nombre en distintos guards', function () {
    $sync = new PermissionSynchronizerService([
        permissionDefinition('x edit'),
        permissionDefinition('x edit', 'API', 'api'),
    ]);

    $sync->sync();

    expect(Permission::where('name', 'x edit')->pluck('guard_name')->sort()->values()->all())->toBe(['api', 'web']);
});

it('detecta permisos huérfanos sin eliminarlos al sincronizar', function () {
    Permission::create(['name' => 'huerfano', 'guard_name' => 'web']);

    $sync = new PermissionSynchronizerService([permissionDefinition('x edit')]);
    $sync->sync();

    expect($sync->getOrphans()->pluck('name'))->toContain('huerfano')->not->toContain('x edit')
        ->and(Permission::where('name', 'huerfano')->exists())->toBeTrue();
});

// Roles

it('crea roles faltantes con sus permisos y actualiza metadatos', function () {
    $permissions = new PermissionSynchronizerService([permissionDefinition('x edit'), permissionDefinition('x view')]);
    $permissions->sync();
    Role::create(['name' => 'editor', 'guard_name' => 'web', 'display_name' => 'Viejo']);

    $sync = new RoleSynchronizerService([roleDefinition('editor', ['x edit', 'x view'], display: 'Nuevo')]);
    $sync->sync();

    $role = Role::where('name', 'editor')->first();
    expect($role->display_name)->toBe('Nuevo')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['x edit', 'x view']);
});

it('sincronizar roles dos veces es idempotente', function () {
    (new PermissionSynchronizerService([permissionDefinition('x edit')]))->sync();
    $sync = new RoleSynchronizerService([roleDefinition('editor', ['x edit'])]);

    $sync->sync();
    $sync->sync();

    $role = Role::where('name', 'editor')->first();
    expect(Role::withTrashed()->where('name', 'editor')->count())->toBe(1)
        ->and($role->permissions)->toHaveCount(1);
});

it('reemplaza los permisos del rol por los definidos y conserva las asignaciones a usuarios', function () {
    (new PermissionSynchronizerService([permissionDefinition('x edit'), permissionDefinition('x view')]))->sync();
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('x view');
    $user = new_user();
    $user->assignRole($role);

    (new RoleSynchronizerService([roleDefinition('editor', ['x edit'])]))->sync();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['x edit'])
        ->and($user->fresh()->hasRole('editor'))->toBeTrue();
});

it('respeta el guard_name del rol (y el alias guard) en sync, faltantes y huérfanos', function () {
    (new PermissionSynchronizerService([permissionDefinition('api read', 'API', 'api')]))->sync();
    $apiRole = roleDefinition('api-role', ['api read'], 'api');
    $aliasRole = ['name' => 'alias-role', 'display_name' => 'A', 'description' => 'A', 'guard' => 'api'];
    $sync = new RoleSynchronizerService([$apiRole, $aliasRole]);

    expect($sync->getMissing())->toHaveCount(2);

    $sync->sync();

    expect(Role::where('name', 'api-role')->value('guard_name'))->toBe('api')
        ->and(Role::where('name', 'alias-role')->value('guard_name'))->toBe('api')
        ->and($sync->getMissing())->toBeEmpty()
        ->and($sync->getOrphans()->pluck('name'))->not->toContain('api-role')
        ->and($sync->getOrphans()->pluck('name'))->not->toContain('alias-role');
});

it('un permiso solo existente en otro guard se considera inexistente', function () {
    (new PermissionSynchronizerService([permissionDefinition('x edit')]))->sync();

    $sync = new RoleSynchronizerService([roleDefinition('api-role', ['x edit'], 'api')]);

    expect(fn () => $sync->sync())->toThrow(Exception::class, "guard 'api'");
});

it('restaura un rol eliminado lógicamente sin duplicarlo', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->delete();

    (new RoleSynchronizerService([roleDefinition('editor')]))->sync();

    expect(Role::where('name', 'editor')->count())->toBe(1)
        ->and(Role::withTrashed()->where('name', 'editor')->count())->toBe(1);
});

it('detecta roles huérfanos sin eliminarlos al sincronizar', function () {
    Role::create(['name' => 'huerfano', 'guard_name' => 'web']);

    $sync = new RoleSynchronizerService([roleDefinition('editor')]);
    $sync->sync();

    expect($sync->getOrphans()->pluck('name'))->toContain('huerfano')
        ->and(Role::where('name', 'huerfano')->exists())->toBeTrue();
});

it('revierte todo el servicio de roles si un permiso no existe', function () {
    (new PermissionSynchronizerService([permissionDefinition('x edit')]))->sync();
    $sync = new RoleSynchronizerService([
        roleDefinition('primero', ['x edit']),
        roleDefinition('segundo', ['no existe']),
    ]);

    expect(fn () => $sync->sync())->toThrow(Exception::class, "El permiso 'no existe'");
    expect(Role::where('name', 'primero')->exists())->toBeFalse();
});

// blockpc:sync-all

it('sync-all --ci es idempotente y termina con éxito usando las listas oficiales', function () {
    $this->artisan('blockpc:sync-all --ci')->assertSuccessful();
    $permissions = Permission::count();
    $roles = Role::count();

    $this->artisan('blockpc:sync-all --ci')->assertSuccessful();

    expect(Permission::count())->toBe($permissions)->and(Role::count())->toBe($roles)
        ->and($permissions)->toBe(count(PermissionList::all()))
        ->and($roles)->toBe(count(RoleList::all()));
});

it('sync-all --ci advierte de huérfanos, devuelve éxito y no los elimina', function () {
    Permission::create(['name' => 'huerfano', 'guard_name' => 'web']);
    Role::create(['name' => 'huerfano', 'guard_name' => 'web']);

    $this->artisan('blockpc:sync-all --ci')
        ->expectsOutputToContain('Permisos huérfanos')
        ->expectsOutputToContain('Roles huérfanos')
        ->assertExitCode(0);

    expect(Permission::where('name', 'huerfano')->exists())->toBeTrue()
        ->and(Role::where('name', 'huerfano')->exists())->toBeTrue();
});

it('sync-all conserva las asignaciones de roles de los usuarios', function () {
    $user = new_user(role: 'admin');

    $this->artisan('blockpc:sync-all --ci')->assertSuccessful();

    expect($user->fresh()->hasRole('admin'))->toBeTrue();
});

it('sync-all --ci devuelve FAILURE y revierte permisos y roles si falla un rol', function () {
    Log::spy();
    bindDefinitions(
        [permissionDefinition('x nuevo')],
        [roleDefinition('rol nuevo', ['x nuevo']), roleDefinition('rol roto', ['no existe'])]
    );

    $this->artisan('blockpc:sync-all --ci')
        ->expectsOutputToContain("El permiso 'no existe'")
        ->assertExitCode(1);

    Log::shouldHaveReceived('error')->once()->with('Error en blockpc:sync-all', Mockery::type('array'));

    expect(Permission::where('name', 'x nuevo')->exists())->toBeFalse()
        ->and(Role::where('name', 'rol nuevo')->exists())->toBeFalse();
});

it('sync-all limpia la caché de Spatie tras un rollback', function () {
    Log::spy();
    bindDefinitions(
        [permissionDefinition('x nuevo')],
        [roleDefinition('rol roto', ['x nuevo', 'no existe'])]
    );

    $this->artisan('blockpc:sync-all --ci')->assertExitCode(1);

    $cached = app(PermissionRegistrar::class)->getPermissions()->pluck('name');
    expect($cached)->not->toContain('x nuevo');
});

// --prune manual

it('permissions-sync --prune elimina solo huérfanos sin uso y tras confirmar', function () {
    $free = Permission::create(['name' => 'libre', 'guard_name' => 'web']);
    $used = Permission::create(['name' => 'usado', 'guard_name' => 'web']);
    new_user()->givePermissionTo($used);
    $viaRole = Permission::create(['name' => 'por rol', 'guard_name' => 'web']);
    new_user(role: 'admin');
    Role::findByName('admin')->givePermissionTo($viaRole);

    bindDefinitions([permissionDefinition('x nuevo')], []);
    $count = app(PermissionSynchronizerService::class)->getPrunable()->count();

    $this->artisan('blockpc:permissions-sync --prune')
        ->expectsConfirmation("¿Eliminar {$count} permisos huérfanos?", 'yes')
        ->assertSuccessful();

    expect(Permission::find($free->id))->toBeNull()
        ->and(Permission::find($used->id))->not->toBeNull()
        ->and(Permission::find($viaRole->id))->not->toBeNull();
});

it('permissions-sync --prune cancelado no elimina nada', function () {
    Permission::create(['name' => 'libre', 'guard_name' => 'web']);
    bindDefinitions([permissionDefinition('x nuevo')], []);
    $count = app(PermissionSynchronizerService::class)->getPrunable()->count();

    $this->artisan('blockpc:permissions-sync --prune')
        ->expectsConfirmation("¿Eliminar {$count} permisos huérfanos?", 'no')
        ->assertSuccessful();

    expect(Permission::where('name', 'libre')->exists())->toBeTrue();
});

it('roles-sync --prune elimina (soft delete) roles huérfanos sin usuarios y protege los usados', function () {
    $free = Role::create(['name' => 'libre', 'guard_name' => 'web']);
    $used = Role::create(['name' => 'usado', 'guard_name' => 'web']);
    $user = new_user();
    $user->assignRole($used);
    bindDefinitions([], [roleDefinition('rol nuevo')]);
    $count = app(RoleSynchronizerService::class)->getPrunable()->count();

    $this->artisan('blockpc:roles-sync --prune')
        ->expectsConfirmation("¿Eliminar {$count} roles huérfanos?", 'yes')
        ->assertSuccessful();

    expect(Role::find($free->id))->toBeNull()
        ->and(Role::withTrashed()->find($free->id))->not->toBeNull()
        ->and(Role::find($used->id))->not->toBeNull()
        ->and($user->fresh()->hasRole('usado'))->toBeTrue();
});

it('roles-sync --check --ci devuelve FAILURE si falta un rol definido', function () {
    Log::spy();
    bindDefinitions([], [roleDefinition('rol nuevo')]);

    $this->artisan('blockpc:roles-sync --check --ci')->assertExitCode(1);

    Log::shouldHaveReceived('error')->once()->with('Errores de sincronización de roles: 1');
});

it('permissions-sync --orphans --ci no falla por huérfanos', function () {
    Permission::create(['name' => 'huerfano', 'guard_name' => 'web']);

    $this->artisan('blockpc:permissions-sync --orphans --ci')->assertExitCode(0);
});

it('los comandos no usan exit()', function () {
    foreach (glob(base_path('Blockpc/App/Console/Commands/Sync*.php')) as $file) {
        expect(file_get_contents($file))->not->toMatch('/\bexit\s*\(/');
    }
});

it('un permiso asignado a un rol sin usuarios se considera en uso y no se elimina', function () {
    $permission = Permission::create(['name' => 'en rol', 'guard_name' => 'web']);
    Role::create(['name' => 'sin usuarios', 'guard_name' => 'web'])->givePermissionTo($permission);
    $free = Permission::create(['name' => 'libre', 'guard_name' => 'web']);

    $sync = new PermissionSynchronizerService([permissionDefinition('x nuevo')]);

    expect($sync->isInUse($permission))->toBeTrue()
        ->and($sync->getPrunable()->pluck('name')->all())->toContain('libre')->not->toContain('en rol');

    $sync->prune();

    expect(Permission::find($permission->id))->not->toBeNull()
        ->and(Permission::find($free->id))->toBeNull();
});

it('un permiso asignado directamente a un usuario se considera en uso', function () {
    $permission = Permission::create(['name' => 'directo', 'guard_name' => 'web']);
    new_user()->givePermissionTo($permission);

    expect((new PermissionSynchronizerService([]))->isInUse($permission))->toBeTrue();
});

it('--prune falla sin eliminar nada con --ci o sin interacción', function (string $command, string $options) {
    Permission::create(['name' => 'libre', 'guard_name' => 'web']);
    Role::create(['name' => 'libre', 'guard_name' => 'web']);
    bindDefinitions([permissionDefinition('x nuevo')], [roleDefinition('rol nuevo')]);

    $this->artisan("{$command} --prune {$options}")->assertExitCode(1);

    expect(Permission::where('name', 'libre')->exists())->toBeTrue()
        ->and(Role::where('name', 'libre')->exists())->toBeTrue();
})->with([
    'permisos --ci' => ['blockpc:permissions-sync', '--ci'],
    'permisos --no-interaction' => ['blockpc:permissions-sync', '--no-interaction'],
    'roles --ci' => ['blockpc:roles-sync', '--ci'],
    'roles --no-interaction' => ['blockpc:roles-sync', '--no-interaction'],
]);

it('prune vuelve a validar dentro de la transacción y omite permisos que pasaron a estar en uso', function () {
    $permission = Permission::create(['name' => 'carrera', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'carrera', 'guard_name' => 'web']);
    $sync = new PermissionSynchronizerService(PermissionList::all());

    // Simula una asignación concurrente tras calcular los candidatos y antes de bloquear la fila.
    $retrievals = 0;
    Permission::retrieved(function (Permission $model) use (&$retrievals, $role) {
        if ($model->name === 'carrera' && ++$retrievals === 2) {
            $role->givePermissionTo($model);
        }
    });

    expect($sync->prune())->toBe(0)
        ->and(Permission::find($permission->id))->not->toBeNull();
});
