<nav x-data="{ open: false }" class="nav-dark w-full sticky top-0 z-10">
    {{-- Primary Navigation Menu --}}
    <div class="md:mx-2 h-16 flex">
        <div class="flex items-center w-64">
            {{-- Logo --}}
            <div class="flex items-center space-x-4 w-64">
                <a href="{{ route('home') }}">
                    <x-logo class="h-10" src="{{ asset('img/logo.png') }}" />
                </a>
                <span class="text-lg font-semibold">{{ config('app.name', 'Backend') }}</span>
            </div>
            <div class="flex items-center">
                <button x-on:click="sidebar = ! sidebar" class="inline-flex items-center justify-center p-2 rounded-md focus:bg-gray-200 dark:focus:bg-gray-700 transition duration-150 ease-in-out">
                    <div :class="sidebar ? 'hidden' : 'inline-flex'">
                        <x-bx-menu class="h-5 w-5" />
                    </div>
                    <div :class="! sidebar ? 'hidden' : 'inline-flex' ">
                        <x-bx-x class="h-5 w-5" />
                    </div>
                </button>
            </div>
        </div>
        <div class="flex justify-end w-full">

            {{-- Settings Dropdown --}}
            <div class="flex items-center md:space-x-4">
                <div class="h-16 flex">
                    @livewire('blockpc-btn-notifications', [], key('blockpc-btn-notifications'))
                </div>
                <div class="h-16 flex">
                    <button id="theme-toggle" type="button" class="rounded-lg text-sm p-1">
                        <x-bx-sun class="hidden h-6 w-6 text-yellow-600" id="theme-toggle-dark-icon" />
                        <x-bx-moon class="hidden h-6 w-6 text-gray-200" id="theme-toggle-light-icon" />
                    </button>
                </div>
                {{-- menu options user --}}
                <x-dropdown align="right" width="72" :bx="false">
                    <x-slot name="trigger">
                        <button class="flex items-center space-x-2 text-sm font-medium text-dark transition duration-150 ease-in-out mx-2">
                            <x-logo class="rounded-full w-8 h-8 bg-inherit" src="{{ image_profile() }}" alt="{{ current_user()->fullname }}" />
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="flex items-center space-x-2 p-2">
                            <div class="w-16">
                                <img class="rounded-full bg-inherit" src="{{ image_profile() }}" alt="{{ current_user()->fullname }}">
                            </div>
                            <div class="w-full">
                                <div class="font-bold text-base text-gray-800 dark:text-gray-200">{{ current_user()->fullname }}</div>
                                <div class="font-medium text-xs text-gray-500 dark:text-gray-400">Administrador</div>
                            </div>
                        </div>
                        <hr class="hr-xs">
                        <a wire:navigate class="flex items-center dark:hover:bg-gray-700 hover:bg-gray-300 p-2 h-10 rounded-sm" href="{{ route('dashboard') }}">{{ __('pages.dashboard.titles.link') }}</a>
                        <a wire:navigate class="flex items-center dark:hover:bg-gray-700 hover:bg-gray-300 p-2 h-10 rounded-sm" href="{{ route('profile') }}">{{ __('pages.users.titles.profile') }}</a>
                        <hr class="hr-xs">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex items-center dark:hover:bg-red-200 hover:bg-red-200 p-2 h-10 rounded-sm text-red-500 dark:text-red-500 w-full">{{ __('common.logout') }}</button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
