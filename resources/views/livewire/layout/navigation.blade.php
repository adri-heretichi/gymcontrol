<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="relative z-30 border-b border-orange-100 bg-white shadow-sm">
    @php
        $modules = [
            ['label' => 'Panel Administrativo', 'route' => 'dashboard', 'active' => ['dashboard', 'solicitudes.*']],
            ['label' => 'Socios', 'route' => 'socios.index', 'active' => ['socios.*', 'inscripciones.*']],
            ['label' => 'Membresías', 'route' => 'membresias.index', 'active' => ['membresias.*', 'disciplinas.*', 'grupos.*']],
            ['label' => 'Aptos Físicos', 'route' => 'aptos-fisicos.index', 'active' => ['aptos-fisicos.*'], 'permission' => ['viewAny', \App\Models\AptoFisico::class]],
            ['label' => 'Pagos', 'route' => 'pagos.index', 'active' => ['pagos.*']],
            ['label' => 'Control de Acceso', 'route' => 'control-acceso', 'active' => ['control-acceso', 'asistencias.*']],
            ['label' => 'Operadores', 'route' => 'operadores.index', 'active' => ['operadores.*'], 'permission' => ['administrar-operadores']],
            ['label' => 'Auditoría', 'route' => 'auditoria.index', 'active' => ['auditoria.*'], 'permission' => ['viewAny', \App\Models\Auditoria::class]],
        ];
        $modules = array_filter($modules, fn ($module) => !isset($module['permission']) || auth()->user()->can(...$module['permission']));
    @endphp
    <!-- Primary Navigation Menu -->
    <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
        <div class="flex min-h-16 h-auto flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2">
            <div class="flex min-w-0 flex-1 items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-gym.marca class="max-w-full" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div data-navigation-menu="desktop" aria-label="Módulos principales" class="hidden min-w-0 flex-1 flex-wrap items-center justify-start gap-x-3 gap-y-0.5 xl:flex xl:ms-3">
                    @foreach($modules as $module)
                        <x-nav-link data-module="{{ $module['route'] }}" class="whitespace-nowrap px-1" :href="route($module['route'])" :active="request()->routeIs(...$module['active'])" wire:navigate>
                            {{ $module['label'] }}
                        </x-nav-link>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden shrink-0 items-center xl:flex xl:ms-3">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="ms-auto flex items-center xl:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-orange-100 bg-white xl:hidden">
        <div data-navigation-menu="mobile" aria-label="Módulos principales móviles" class="pt-2 pb-3 space-y-1">
            @foreach($modules as $module)
                <x-responsive-nav-link data-module="{{ $module['route'] }}" :href="route($module['route'])" :active="request()->routeIs(...$module['active'])" wire:navigate>
                    {{ $module['label'] }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
