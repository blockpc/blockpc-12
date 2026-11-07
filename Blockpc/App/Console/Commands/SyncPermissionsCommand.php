<?php

declare(strict_types=1);

namespace Blockpc\App\Console\Commands;

use Blockpc\App\Console\Services\PermissionSynchronizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'blockpc:permissions-sync
                            {--check : Solo verificar permisos existentes}
                            {--orphans : Mostrar permisos huérfanos}
                            {--prune : Eliminar permisos huérfanos sin uso (solo manual e interactivo, con confirmación)}
                            {--ci : Modo no interactivo para CI/CD}';

    protected $description = 'Sincroniza, valida y limpia los permisos definidos en el sistema';

    public function handle(PermissionSynchronizerService $sync): int
    {
        if ($this->option('check')) {
            $missing = $sync->getMissing();

            if ($missing->isEmpty()) {
                $this->info('✅ Todo sincronizado.');

                return self::SUCCESS;
            }

            $this->warn('⚠️  Permisos faltantes:');
            foreach ($missing as $perm) {
                $guard = $perm[4] ?? 'web';
                $this->warn("❌ Falta permiso: {$perm[0]} (guard: {$guard})");
            }

            if ($this->option('ci')) {
                $this->error("Errores de sincronización de permisos: {$missing->count()}");
                Log::error("Errores de sincronización de permisos: {$missing->count()}");
            }

            return self::FAILURE;
        }

        if ($this->option('orphans')) {
            $orphans = $sync->getOrphans();

            if ($orphans->isEmpty()) {
                $this->info('✅ No hay permisos huérfanos.');
            } else {
                $this->warn('⚠️  Permisos huérfanos:');
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
            $this->error("❌ Error de sincronización de permisos: {$e->getMessage()}");
            Log::error('Error en blockpc:permissions-sync', ['exception' => $e]);

            return self::FAILURE;
        }

        $this->info('🎉 Permisos sincronizados.');

        return self::SUCCESS;
    }

    private function prune(PermissionSynchronizerService $sync): int
    {
        $orphans = $sync->getOrphans();

        if ($orphans->isEmpty()) {
            $this->info('✅ No hay permisos huérfanos.');

            return self::SUCCESS;
        }

        $prunable = $sync->getPrunable();
        $inUse = $orphans->count() - $prunable->count();

        if ($inUse > 0) {
            $this->warn("⚠️  {$inUse} permisos huérfanos están en uso y no se eliminarán.");
        }

        if ($prunable->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->confirm("¿Eliminar {$prunable->count()} permisos huérfanos?", false)) {
            $this->info('🛑 Cancelado.');

            return self::SUCCESS;
        }

        $this->info("🗑️ Eliminados: {$sync->prune()} permisos huérfanos.");

        return self::SUCCESS;
    }
}
