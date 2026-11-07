<?php

declare(strict_types=1);

namespace Blockpc\App\Console\Services;

use App\Models\Permission;
use Blockpc\App\Console\Classes\PermissionList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSynchronizerService
{
    /**
     * @param  array<int, array<int, string>>|null  $definitions  Por defecto, PermissionList::all()
     */
    public function __construct(private readonly ?array $definitions = null)
    {
        //
    }

    /**
     * Sincroniza los permisos definidos en una transacción.
     *
     * La caché de Spatie se invalida siempre, también tras un rollback, porque pudo
     * poblarse con datos que ya no existen.
     */
    public function sync(): void
    {
        try {
            DB::transaction(function (): void {
                foreach ($this->definitions() as $permiso) {
                    [$name, $key, $description, $displayName, $guard] = array_pad($permiso, 5, 'web');

                    Permission::updateOrCreate(
                        ['name' => $name, 'guard_name' => $guard],
                        ['key' => $key, 'description' => $description, 'display_name' => $displayName]
                    );
                }
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function getMissing(): Collection
    {
        return collect($this->definitions())
            ->filter(function ($permiso) {
                [$name, $key, $description, $displayName, $guard] = array_pad($permiso, 5, 'web');

                return ! Permission::where('name', $name)->where('guard_name', $guard)->exists();
            });
    }

    public function getOutdated(): Collection
    {
        return collect($this->definitions())
            ->filter(function ($permiso) {
                [$name, $key, $description, $displayName, $guard] = array_pad($permiso, 5, 'web');
                $perm = Permission::where('name', $name)->where('guard_name', $guard)->first();

                if (! $perm) {
                    return false;
                }

                return $perm->key !== $key
                    || $perm->description !== $description
                    || $perm->display_name !== $displayName;
            });
    }

    public function getOrphans(): Collection
    {
        $defined = collect($this->definitions())->map(fn ($p) => [
            'name' => $p[0],
            'guard_name' => $p[4] ?? 'web',
        ]);

        return Permission::all()->filter(function ($perm) use ($defined) {
            return ! $defined->contains(fn ($p) => $p['name'] === $perm->name && $p['guard_name'] === $perm->guard_name);
        });
    }

    /**
     * Permisos huérfanos que no están asignados a ningún usuario, directamente o a través de un rol.
     */
    public function getPrunable(): Collection
    {
        return $this->getOrphans()->reject(fn (Permission $permission) => $this->isInUse($permission))->values();
    }

    /**
     * Elimina únicamente los huérfanos sin uso. Solo debe invocarse de forma manual.
     * Vuelve a validar cada registro dentro de la transacción, con bloqueo de fila.
     */
    public function prune(): int
    {
        $deleted = DB::transaction(function (): int {
            $count = 0;

            foreach ($this->getPrunable() as $candidate) {
                $permission = Permission::whereKey($candidate->getKey())->lockForUpdate()->first();

                if ($permission === null || $this->isInUse($permission) || ! $this->isOrphan($permission)) {
                    continue;
                }

                $permission->delete();
                $count++;
            }

            return $count;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $deleted;
    }

    /**
     * En uso: asignado directamente a un usuario o a cualquier rol, tenga o no usuarios.
     */
    public function isInUse(Permission $permission): bool
    {
        $tables = config('permission.table_names');
        $permissionKey = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

        return DB::table($tables['model_has_permissions'])->where($permissionKey, $permission->getKey())->exists()
            || DB::table($tables['role_has_permissions'])->where($permissionKey, $permission->getKey())->exists();
    }

    private function isOrphan(Permission $permission): bool
    {
        return ! collect($this->definitions())->contains(
            fn (array $permiso) => $permiso[0] === $permission->name
                && ($permiso[4] ?? 'web') === $permission->guard_name
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function definitions(): array
    {
        return $this->definitions ?? PermissionList::all();
    }
}
