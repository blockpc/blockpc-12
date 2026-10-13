<div>
    <x-page-header titulo="pages.permissions.titles.table" icon="bx-label">
        <x-slot name="buttons">
            <x-links.href class="btn-sm btn-default" href="{{ route('dashboard') }}">
                <span>{{__('pages.dashboard.titles.link')}}</span>
            </x-links.href>
        </x-slot>
    </x-page-header>

    <section class="mt-2 mx-auto w-full">

        <div class="content-table">
            <x-tables.search :search="$search" :paginate="$paginate" :clean="true" name="permissions" placeholder="Buscador" >
                <x-slot name="actions">

                    <div class="flex items-center space-x-2">
                        <label class="label" for="permissions_keys">
                            <span>{{title(__('pages.permissions.titles.keys'))}}</span>
                        </label>
                        <select name="permissions_keys" id="permissions_keys" class="select text-sm scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-gray-700" title="Seleccionar por grupo" wire:model.live.change="key_id">
                            <option value="" wire:key="0">{{__('common.select')}}...</option>
                            @foreach ($this->claves as $option_key => $option_value)
                                <option value="{{ $option_key }}" wire:key="{{ $option_key }}">{{ __('pages.permissions.keys.'. $option_value) }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-slot>
            </x-tables.search>

            <x-tables.table>
                <x-slot name="thead">
                    <tr>
                        <th class="td">{{ __('pages.permissions.table.name') }}</th>
                        <th class="td text-right">{{ __('common.actions') }}</th>
                    </tr>
                </x-slot>
                <x-slot name="tbody">
                    @forelse ($this->permissions as $permission)
                    <tr class="tr tr-hover">
                        <td class="td">
                            <div class="flex flex-col space-y-1">
                                <span class="font-semibold">{{ $permission->display_name }}</span>
                                <span class="text-xs italic">{{ $permission->description }}</span>
                            </div>
                        </td>
                        <td class="td">
                            <div class="td-actions">
                                <x-buttons.btn class="btn-success" wire:click="update_permission({{ $permission->id }})">
                                    <span>Editar</span>
                                </x-buttons.btn>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <x-tables.no-records span="2" :search="$search" />
                    @endforelse
                </x-slot>
            </x-tables.table>

            <x-pagination :model="$this->permissions" />
        </div>
    </section>
</div>
