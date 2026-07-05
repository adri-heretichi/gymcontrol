<x-app-layout>
    <x-slot name="header">
        <h2 class="font-extrabold text-2xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <!-- Cargamos el componente Livewire Dashboard que procesa los datos en PHP -->
    <livewire:dashboard />
</x-app-layout>
