<?php

declare(strict_types=1);

namespace Blockpc\App\Console\Commands;

use Blockpc\App\Console\Services\RoleSynchronizerService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncRolesCommand extends \Illuminate\Console\Command
{
    protected $signature = 'blockpc:roles-sync
                            {--check : Solo verificar roles existentes}
                            {--orphans : Mostrar roles huérfanos}
                            {--prune : Eliminar roles huérfanos sin uso (solo manual e interactivo, con confirmación)}
                            {--ci : Modo no interactivo para CI/CD}';

    protected $description = 'Sincroniza, valida y limpia los roles definidos en el sistema';

    public function handle(RoleSynchronizerService $sync): int
    {
        if ($this->option('check')) {
            $missing = $sync->getMissing();

            if ($missing->isEmpty()) {
                $this->info('✅ Todo sincronizado.');

                return self::SUCCESS;
            }

            $this->warn('⚠️  Roles faltantes:');
            foreach ($missing as $role) {
                $guard = $role['guard_name'] ?? $role['guard'] ?? 'web';
                $this->warn("❌ Falta rol: {$role['name']} (guard: {$guard})");
            }

            if ($this->option('ci')) {
                $this->error("Errores de sincronización de roles: {$missing->count()}");
                Log::error("Errores de sincronización de roles: {$missing->count()}");
            }

            return self::FAILURE;
        }

        if ($this->option('orphans')) {
            $orphans = $sync->getOrphans();

            if ($orphans->isEmpty()) {
                $this->info('✅ No hay roles huérfanos.');
            } else {
                $this->warn('⚠️  Roles huérfanos:');
                foreach ($orphans as $orphan) {
                    $this->line("- {$orphan->name} ({$orphan->guard_name})");
                }
            }

            return self::SUCCESS;
        }

        if ($this->option('prune')) {
            if ($this->option('ci') || ! $this->input->isInteractive()) {
                $this->error('❌ --prune requiere ejecución interactiva y confirmación manual; no se eliminó nada.');

                return self::FAILURE;
            }

            return $this->prune($sync);
        }

        try {
            $sync->sync();
        } catch (Throwable $e) {
            $this->error("❌ Error de sincronización de roles: {$e->getMessage()}");
            Log::error('Error en blockpc:roles-sync', ['exception' => $e]);

            return self::FAILURE;
        }

        $this->info('🎉 Roles sincronizados.');

        return self::SUCCESS;
    }

    private function prune(RoleSynchronizerService $sync): int
    {
        $orphans = $sync->getOrphans();

        if ($orphans->isEmpty()) {
            $this->info('✅ No hay roles huérfanos.');

            return self::SUCCESS;
        }

        $prunable = $sync->getPrunable();
        $inUse = $orphans->count() - $prunable->count();

        if ($inUse > 0) {
            $this->warn("⚠️  {$inUse} roles huérfanos tienen usuarios asignados y no se eliminarán.");
        }

        if ($prunable->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->confirm("¿Eliminar {$prunable->count()} roles huérfanos?", false)) {
            $this->info('🛑 Cancelado.');

            return self::SUCCESS;
        }

        $this->info("🗑️ Eliminados: {$sync->prune()} roles huérfanos.");

        return self::SUCCESS;
    }
}
