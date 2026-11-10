<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Coberturas de {{ $socio->nombre }} {{ $socio->apellido }}
            </h2>
            <a href="{{ route('socios.show', $socio) }}" class="text-sm text-blue-600 hover:underline">
                ← Ficha del socio
            </a>
        </div>
    </x-slot>

    <div class="py-6 mx-auto max-w-4xl sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            @if(empty($coberturas))
                <div class="px-6 py-8 text-center text-gray-400 italic">
                    El socio no tiene inscripciones activas.
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Disciplina / Grupo</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Inscripción</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Cobertura</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Vence</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($coberturas as $inscripcionId => $cob)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    {{ $cob['disciplina'] }}
                                    <span class="block text-xs text-gray-500">{{ $cob['grupo'] }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        {{ $cob['inscripcion_estado'] === 'aprobada' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ ucfirst($cob['inscripcion_estado']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    @if($cob['fecha_inicio'])
                                        {{ \Carbon\Carbon::parse($cob['fecha_inicio'])->format('d/m/Y') }}
                                        –
                                        {{ \Carbon\Carbon::parse($cob['fecha_fin'])->format('d/m/Y') }}
                                    @else
                                        <span class="text-gray-400 italic">Sin cobertura</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    @if($cob['vigente'] && $cob['dias_restantes'] !== null)
                                        {{ $cob['dias_restantes'] }} día{{ $cob['dias_restantes'] != 1 ? 's' : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($cob['vigente'])
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">
                                            ✓ Vigente
                                        </span>
                                    @elseif($cob['estado'] === 'vencida')
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium">
                                            Vencida
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-100 text-gray-500 rounded text-xs font-medium">
                                            Sin cobertura
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @can('create', \App\Models\Pago::class)
                                        <a href="{{ route('pagos.create') }}?inscripcion={{ $inscripcionId }}"
                                           class="text-xs text-blue-600 hover:underline">
                                            Cobrar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <p class="text-xs text-gray-400 text-center mt-4">
            Estado económico por inscripción. El vencimiento de cuota no afecta las credenciales.
        </p>
    </div>
</div>
