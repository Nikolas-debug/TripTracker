<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../lib/api'
import { pesos } from '../../lib/formato'
import Encabezado from '../../components/Encabezado.vue'
import Estado from '../../components/Estado.vue'
import Cargando from '../../components/Cargando.vue'
import Paginacion from '../../components/Paginacion.vue'

const route = useRoute()

const cargando = ref(true)
const viajes = ref([])
const meta = ref(null)
const obras = ref([])

const filtros = reactive({
    estado: route.query.estado ?? 'por_confirmar',
    obra_id: '',
    placa: '',
    desde: '',
    hasta: '',
    page: 1,
})

const pestanas = [
    { valor: 'por_confirmar', texto: 'Por confirmar' },
    { valor: 'confirmado', texto: 'Confirmados' },
    { valor: 'anulado', texto: 'Anulados' },
    { valor: '', texto: 'Todos' },
]

// Los totales son de la página que se está viendo, no de todo el periodo:
// decirlo evita que alguien los tome por el total de la cuenta.
const totales = computed(() => ({
    cobro: viajes.value.reduce((s, v) => s + (v.total_cobro ?? 0), 0),
    pago: viajes.value.reduce((s, v) => s + (v.total_pago ?? 0), 0),
}))

async function cargar() {
    cargando.value = true

    try {
        const { data } = await api.get('viajes', {
            params: { ...filtros, por_pagina: 50 },
        })
        viajes.value = data.data
        meta.value = data.meta
    } finally {
        cargando.value = false
    }
}

function cambiarPestana(valor) {
    filtros.estado = valor
    filtros.page = 1
}

watch(() => ({ ...filtros }), cargar, { deep: true })

onMounted(async () => {
    const { data } = await api.get('obras', { params: { por_pagina: 100, solo_activos: 1 } })
    obras.value = data.data
    await cargar()
})
</script>

<template>
    <Encabezado titulo="Viajes" subtitulo="Registro y seguimiento">
        <template #acciones>
            <router-link :to="{ name: 'vales.registrar' }" class="boton-secundario">Registrar vale</router-link>
            <router-link :to="{ name: 'viajes.registrar' }" class="boton-primario">Registrar viajes</router-link>
        </template>
    </Encabezado>

    <!-- Pestañas de estado -->
    <div class="flex gap-1 overflow-x-auto border-b border-[var(--color-borde)] bg-white px-4 pt-2.5 sm:px-8" role="tablist">
        <button
            v-for="pestana in pestanas"
            :key="pestana.valor"
            class="shrink-0 border-b-2 px-3 pb-3 pt-1.5 text-[14px] transition-colors" role="tab" :aria-selected="filtros.estado === pestana.valor"
            :class="filtros.estado === pestana.valor
                ? 'border-[var(--color-marca)] font-medium text-[var(--color-tinta)]'
                : 'border-transparent text-[var(--color-apagado)] hover:text-[var(--color-tinta)]'"
            @click="cambiarPestana(pestana.valor)"
        >
            {{ pestana.texto }}
        </button>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap items-center gap-2 border-b border-[var(--color-borde)] bg-[var(--color-superficie-sutil)] px-4 sm:px-8 py-3">
        <select v-model="filtros.obra_id" class="campo min-h-9 w-full py-1.5 text-[13px] sm:w-64">
            <option value="">Todas las obras</option>
            <option v-for="obra in obras" :key="obra.id" :value="obra.id">{{ obra.nombre }}</option>
        </select>

        <input v-model="filtros.placa" class="campo min-h-9 w-full py-1.5 text-[13px] sm:w-48" placeholder="Placa">
        <input v-model="filtros.desde" type="date" class="campo min-h-9 w-full py-1.5 text-[13px] sm:w-40">
        <input v-model="filtros.hasta" type="date" class="campo min-h-9 w-full py-1.5 text-[13px] sm:w-40">
    </div>

    <Cargando :activo="cargando" :vacio="!viajes.length" mensaje-vacio="No hay viajes con esos filtros.">
        <div class="bg-white">
            <div class="tabla-scroll">
            <table class="w-full text-[12.5px]">
                <thead>
                    <tr class="bg-[var(--color-superficie-sutil)] text-left text-[11px] font-medium text-[var(--color-apagado)]">
                        <th class="py-2 pl-4 sm:pl-8 pr-2.5">FECHA</th>
                        <th class="w-24 px-2.5 py-2">PLACA</th>
                        <th class="px-2.5 py-2">RUTA</th>
                        <th class="w-36 px-2.5 py-2">MATERIAL</th>
                        <th class="w-24 px-2.5 py-2 text-right">M³</th>
                        <th class="w-20 px-2.5 py-2 text-right">VALE</th>
                        <th class="w-28 px-2.5 py-2 text-right">COBRO</th>
                        <th class="w-28 px-2.5 py-2 text-right">PAGO</th>
                        <th class="w-32 py-2 pl-2.5 pr-4 sm:pr-8">ESTADO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="viaje in viajes" :key="viaje.id" class="border-b border-[var(--color-borde)] hover:bg-[var(--color-superficie-sutil)]">
                        <td class="cifra py-2 pl-4 sm:pl-8 pr-2.5 text-[var(--color-cuerpo)]">{{ viaje.fecha_viaje }}</td>
                        <td class="cifra px-2.5 py-2 font-medium">{{ viaje.placa }}</td>
                        <td class="px-2.5 py-2 text-[var(--color-cuerpo)]">{{ viaje.ruta }}</td>
                        <td class="px-2.5 py-2 text-[var(--color-cuerpo)]">
                            <span v-if="viaje.material">{{ viaje.material }}</span>
                            <span v-else class="italic text-[var(--color-tenue)]">retiro de escombro</span>
                        </td>
                        <td class="cifra px-2.5 py-2 text-right">
                            <!-- Cuando el vale ajustó los m³, se ven los dos -->
                            <template v-if="viaje.ajustado">
                                <span class="text-[var(--color-pendiente)]">{{ viaje.m3_confirmado }}</span>
                                <span class="ml-1 text-[var(--color-borde-fuerte)] line-through">{{ viaje.m3_registrado }}</span>
                            </template>
                            <template v-else>{{ viaje.m3_confirmado ?? viaje.m3_registrado }}</template>
                        </td>
                        <td class="cifra px-2.5 py-2 text-right">
                            <span v-if="viaje.vale" class="font-medium text-[var(--color-enlace)]">{{ viaje.vale }}</span>
                            <span v-else class="text-[var(--color-borde-fuerte)]">—</span>
                        </td>
                        <td class="cifra px-2.5 py-2 text-right">{{ pesos(viaje.total_cobro) }}</td>
                        <td class="cifra px-2.5 py-2 text-right text-[var(--color-apagado)]">{{ pesos(viaje.total_pago) }}</td>
                        <td class="whitespace-nowrap py-2 pl-2.5 pr-4 sm:pr-8">
                            <Estado :valor="viaje.estado" />
                            <span v-if="viaje.liquidado" class="ml-1 rounded bg-[var(--color-info-fondo)] px-1.5 py-0.5 text-[10px] font-medium text-[var(--color-enlace)]">
                                liquidado
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>

            <div class="flex items-center gap-6 border-t border-[var(--color-borde)] px-4 sm:px-8 py-3">
                <p class="text-xs text-[var(--color-apagado)]">Totales de esta página</p>
                <div class="ml-auto flex items-center gap-6">
                    <p class="text-xs text-[var(--color-apagado)]">
                        A cobrar <span class="cifra ml-1 text-sm font-medium text-[var(--color-tinta)]">{{ pesos(totales.cobro, true) }}</span>
                    </p>
                    <p class="text-xs text-[var(--color-apagado)]">
                        A pagar <span class="cifra ml-1 text-sm font-medium text-[var(--color-cuerpo)]">{{ pesos(totales.pago, true) }}</span>
                    </p>
                </div>
            </div>

            <Paginacion :meta="meta" @ir="(p) => (filtros.page = p)" />
        </div>
    </Cargando>
</template>
