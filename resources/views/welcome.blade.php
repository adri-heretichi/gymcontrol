@auth
    <script>window.location.href = "{{ route('dashboard') }}";</script>
@endauth

<x-guest-layout>
    <livewire:pages.auth.login />
</x-guest-layout>
