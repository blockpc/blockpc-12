<?php

declare(strict_types=1);

namespace Blockpc\App\Console\Commands;

use Blockpc\App\Console\Services\PermissionSynchronizerService;
use Blockpc\App\Console\Services\RoleSynchronizerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class SyncRolesAndPermissionsCommand extends \Illuminate\Console\Command
{
    protected $signature = 'blockpc:sync-all {--ci : Modo no interactivo para CI/CD}';

    protected $description = 'Sincroniza roles y permisos definidos en código (nunca elimina huérfanos)';

    /**
     * Sincroniza permisos y roles en una única transacción: si algo falla, no queda ningún
     * cambio parcial y se devuelve FAILURE. La caché de Spatie se invalida siempre, porque
     * pudo poblarse dentro de la transacción revertida. Los huérfanos solo se advierten.
     */
    public function handle(
        PermissionSynchronizerService $permissionSync,
        RoleSynchronizerService $roleSync
    ): int {
        try {
            DB::transaction(function () use ($permissionSync, $roleSync): void {
                $this->info('🔄 Sincronizando permisos...');
                $permissionSync->sync();

                $this->info('🔄 Sincronizando roles...');
                $roleSync->sync();

                $missing = $permissionSync->getMissing()->count() + $roleSync->getMissing()->count();

                if ($missing > 0) {
                    throw new RuntimeException("Quedaron {$missing} roles o permisos definidos sin sincronizar.");
                }
            });
        } catch (Throwable $e) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->error("❌ Error de sincronización: {$e->getMessage()}");
            Log::error('Error en blockpc:sync-all', ['exception' => $e]);

            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $orphanPerms = $permissionSync->getOrphans();
        $orphanRoles = $roleSync->getOrphans();

        if ($orphanPerms->isEmpty() && $orphanRoles->isEmpty()) {
            $this->info('✅ Todo está perfectamente sincronizado.');

            return self::SUCCESS;
        }

        if ($orphanPerms->isNotEmpty()) {
            $this->warn('⚠️ Permisos huérfanos (no se eliminan):');
            foreach ($orphanPerms as $perm) {
                $this->line("- {$perm->name} ({$perm->guard_name})");
            }
        }

        if ($orphanRoles->isNotEmpty()) {
            $this->warn('⚠️ Roles huérfanos (no se eliminan):');
            foreach ($orphanRoles as $role) {
                $this->line("- {$role->name} ({$role->guard_name})");
            }
        }

        $this->info('✅ Sincronización completada.');

        return self::SUCCESS;
    }
}
