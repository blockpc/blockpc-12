<?php

declare(strict_types=1);

namespace Blockpc\App\Console\Services;

use App\Models\Permission;
use App\Models\Role;
use Blockpc\App\Console\Classes\RoleList;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class RoleSynchronizerService
{
    /**
     * @param  list<array{name: string, display_name: string, description: string, permissions?: list<string>, guard_name?: string, guard?: string}>|null  $definitions  Por defecto, RoleList::all()
     */
    public function __construct(private readonly ?array $definitions = null)
    {
        //
    }

    /**
     * Sincroniza los roles definidos y sus permisos en una transacción.
     *
     * Los roles se buscan con withTrashed() y se restauran, para evitar duplicados y la
     * violación del índice único con roles borrados lógicamente. La caché de Spatie se
     * invalida siempre, también tras un rollback, porque pudo poblarse con datos que ya no existen.
     *
     * @throws Exception Si algún rol referencia un permiso inexistente; no se persiste ningún cambio.
     */
    public function sync(): void
    {
        try {
            DB::transaction(function (): void {
                foreach ($this->definitions() as $roleData) {
                    $guard = $this->guardOf($roleData);

                    $role = Role::withTrashed()->firstOrNew(['name' => $roleData['name'], 'guard_name' => $guard]);
                    $role->display_name = $roleData['display_name'];
                    $role->description = $roleData['description'];
                    $role->save();

                    if ($role->trashed()) {
                        $role->restore();
                    }

                    $role->syncPermissions($this->resolvePermissions($roleData['permissions'] ?? [], $guard));
                }
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function getMissing(): Collection
    {
        return collect($this->definitions())
            ->filter(function ($roleData) {
                return ! Role::where('name', $roleData['name'])
                    ->where('guard_name', $this->guardOf($roleData))
                    ->exists();
            });
    }

    public function getOrphans(): Collection
    {
        $defined = collect($this->definitions())->map(fn ($r) => [
            'name' => $r['name'],
            'guard_name' => $this->guardOf($r),
        ]);

        return Role::all()->filter(function ($role) use ($defined) {
            return ! $defined->contains(fn ($r) => $r['name'] === $role->name && $r['guard_name'] === $role->guard_name);
        });
    }

    /**
     * Roles huérfanos que no están asignados a ningún usuario.
     */
    public function getPrunable(): Collection
    {
        return $this->getOrphans()->reject(fn (Role $role) => $this->isInUse($role))->values();
    }

    /**
     * Elimina (soft delete) únicamente los huérfanos sin uso. Solo debe invocarse de forma manual.
     */
    public function prune(): int
    {
        $deleted = DB::transaction(function (): int {
            $count = 0;

            foreach ($this->getPrunable() as $candidate) {
                $role = Role::whereKey($candidate->getKey())->lockForUpdate()->first();

                if ($role === null || $this->isInUse($role) || ! $this->isOrphan($role)) {
                    continue;
                }

                $role->delete();
                $count++;
            }

            return $count;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $deleted;
    }

    public function isInUse(Role $role): bool
    {
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';

        return DB::table(config('permission.table_names.model_has_roles'))
            ->where($roleKey, $role->getKey())
            ->exists();
    }

    private function isOrphan(Role $role): bool
    {
        return ! collect($this->definitions())->contains(
            fn (array $roleData) => $roleData['name'] === $role->name
                && $this->guardOf($roleData) === $role->guard_name
        );
    }

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, Permission>
     *
     * @throws Exception
     */
    private function resolvePermissions(array $names, string $guard): Collection
    {
        $permissions = Permission::where('guard_name', $guard)->whereIn('name', $names)->get();

        $unknown = collect($names)->diff($permissions->pluck('name'));

        if ($unknown->isNotEmpty()) {
            throw new Exception("El permiso '{$unknown->first()}' no existe para el guard '{$guard}'");
        }

        return $permissions;
    }

    /**
     * @param  array{name: string, display_name: string, description: string, permissions?: list<string>, guard_name?: string, guard?: string}  $roleData
     */
    private function guardOf(array $roleData): string
    {
        return $roleData['guard_name'] ?? $roleData['guard'] ?? 'web';
    }

    /**
     * @return list<array{name: string, display_name: string, description: string, permissions?: list<string>, guard_name?: string, guard?: string}>
     */
    private function definitions(): array
    {
        return $this->definitions ?? RoleList::all();
    }
}
