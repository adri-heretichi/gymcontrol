@props(['terminal' => false])
<div class="sticky {{ $terminal ? 'top-14' : 'top-0' }} z-40 border-b border-orange-100 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm" aria-live="polite" aria-atomic="true">
    <span data-gym-realtime-status>Conectando actualizaciones…</span>
    <span data-gym-realtime-notice class="ml-3 hidden font-semibold text-orange-700"></span>
</div>
