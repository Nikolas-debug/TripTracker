<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../lib/api'
import { pesos, numero, fecha } from '../lib/formato'
import Encabezado from '../components/Encabezado.vue'
import Cargando from '../components/Cargando.vue'
import Icono from '../components/Icono.vue'

const cargando = ref(true)
const viajesPendientes = ref([])
const ultimosVales = ref([])
const cuentasAbiertas = ref([])
const totalCuentas = ref(0)

const DIA = 86400000
const diasDesde = (iso) => Math.max(0, Math.floor((Date.now() - new Date(`${iso}T12:00:00`)) / DIA))

/**
 * Qué tan viejo es lo que espera vale. Un viaje sin vale no se puede cobrar:
 * entre más días, más plata quieta y más difícil conseguir la firma.
 */
const TRAMOS = [
    { clave: 'reciente', texto: '0–2 días', hasta: 2, color: 'var(--color-menta)', chip: 'bg-[var(--color-confirmado-fondo)] text-[var(--color-confirmado)]' },
    { clave: 'semana', texto: '3–7 días', hasta: 7, color: 'var(--color-amarillo)', chip: 'bg-[var(--color-pendiente-fondo)] text-[var(--color-pendiente)]' },
    { clave: 'vencido', texto: 'Más de 7 días', hasta: Infinity, color: 'var(--color-coral)', chip: 'bg-[var(--color-critico-fondo)] text-[var(--color-critico)]' },
]

const tramoDe = (dias) => TRAMOS.find((t) => dias <= t.hasta)

const totalPendiente = computed(() =>
    viajesPendientes.value.reduce((suma, v) => suma + (v.total_cobro ?? 0), 0),
)

const envejecimiento = computed(() =>
    TRAMOS.map((tramo) => {
        const viajes = viajesPendientes.value.filter((v) => tramoDe(diasDesde(v.fecha_viaje)) === tramo)
        return { ...tramo, viajes: viajes.length, valor: viajes.reduce((s, v) => s + (v.total_cobro ?? 0), 0) }
    }),
)

/** Los pendientes agrupados por obra, que es como se trabaja la confirmación. */
const porObra = computed(() => {
    const mapa = new Map()

    for (const viaje of viajesPendientes.value) {
        const obra = viaje.ruta?.split('→').pop()?.trim() ?? 'Sin obra'
        const actual = mapa.get(obra) ?? { obra, viajes: 0, valor: 0, masAntiguo: viaje.fecha_viaje }

        actual.viajes += 1
        actual.valor += viaje.total_cobro ?? 0
        if (viaje.fecha_viaje < actual.masAntiguo) actual.masAntiguo = viaje.fecha_viaje

        mapa.set(obra, actual)
    }

    // Primero lo más viejo: es lo que más urge conseguir firmado.
    return [...mapa.values()]
        .map((fila) => ({ ...fila, dias: diasDesde(fila.masAntiguo) }))
        .sort((a, b) => b.dias - a.dias || b.viajes - a.viajes)
})

const maxViajesObra = computed(() => Math.max(1, ...porObra.value.map((f) => f.viajes)))
const diasEsperando = computed(() => porObra.value[0]?.dias ?? 0)

onMounted(async () => {
    try {
        const [pendientes, vales, cuentas] = await Promise.all([
            api.get('vales/pendientes', { params: { por_pagina: 200 } }),
            api.get('vales', { params: { por_pagina: 5 } }),
            api.get('liquidaciones', { params: { estado: 'abierta', por_pagina: 5 } }),
        ])

        viajesPendientes.value = pendientes.data.data
        ultimosVales.value = vales.data.data
        cuentasAbiertas.value = cuentas.data.data
        totalCuentas.value = cuentas.data.meta?.total ?? cuentas.data.data.length
    } finally {
        cargando.value = false
    }
})
</script>

<template>
    <Encabezado titulo="Inicio" :subtitulo="new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })">
        <template #acciones>
            <router-link :to="{ name: 'vales.registrar' }" class="boton-secundario">
                <Icono nombre="vales" :tamano="16" /> Registrar vale
            </router-link>
            <router-link :to="{ name: 'viajes.registrar' }" class="boton-primario">
                <Icono nombre="mas" :tamano="16" /> Registrar viajes
            </router-link>
        </template>
    </Encabezado>

    <Cargando :activo="cargando" :filas="8">
        <div class="mx-auto max-w-[1360px] space-y-6 px-4 py-6 sm:px-8 sm:py-8">
            <!-- Lo que hay que hacer hoy: la tarjeta de firma, solo cuando hay trabajo -->
            <section v-if="viajesPendientes.length" class="firma-coral flex flex-col gap-6 p-6 sm:p-8 md:flex-row md:items-end">
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] text-white/80">Esperando vale</p>
                    <p class="mt-1 text-[40px] font-normal leading-none tracking-[-0.02em] sm:text-[48px]">
                        <span class="cifra">{{ numero(viajesPendientes.length) }}</span>
                        <span class="text-[24px] text-white/80 sm:text-[28px]"> viajes</span>
                    </p>
                    <p class="mt-3 max-w-xl text-[14px] leading-relaxed text-white/85">
                        <span class="cifra">{{ pesos(totalPendiente, true) }}</span> sin poder cobrar en
                        {{ porObra.length }} {{ porObra.length === 1 ? 'obra' : 'obras' }}.
                        <template v-if="diasEsperando > 0">El más antiguo lleva <span class="cifra">{{ diasEsperando }}</span> días.</template>
                    </p>
                </div>
                <router-link :to="{ name: 'vales.registrar' }" class="boton-firma self-start md:self-auto">
                    Confirmar con vale <Icono nombre="flecha" :tamano="16" />
                </router-link>
            </section>

            <section v-else class="firma-crema flex items-center gap-4 p-5">
                <div class="flex size-10 items-center justify-center rounded-full bg-[var(--color-bosque)] text-white">
                    <Icono nombre="check" :tamano="18" />
                </div>
                <div>
                    <p class="text-[16px] font-medium">Todo confirmado</p>
                    <p class="text-[13px] text-[var(--color-apagado)]">No hay viajes esperando vale.</p>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                <!-- Cola de confirmación -->
                <section class="tarjeta overflow-hidden">
                    <div class="flex items-center gap-3 px-5 pb-4 pt-5">
                        <h2 class="text-[16px]">Cola de confirmación</h2>
                        <router-link :to="{ name: 'viajes', query: { estado: 'por_confirmar' } }" class="ml-auto text-[13px] font-medium text-[var(--color-enlace)]">
                            Ver viajes
                        </router-link>
                    </div>

                    <!-- Envejecimiento: cuánto lleva esperando lo pendiente -->
                    <div v-if="viajesPendientes.length" class="px-5 pb-5">
                        <div class="flex h-2.5 gap-0.5 overflow-hidden rounded-full" role="img" :aria-label="envejecimiento.map((t) => `${t.texto}: ${t.viajes} viajes`).join(', ')">
                            <div
                                v-for="tramo in envejecimiento.filter((t) => t.viajes)"
                                :key="tramo.clave"
                                class="h-full transition-[flex-grow] duration-500 [transition-timing-function:var(--ease-salida)]"
                                :style="{ flexGrow: tramo.viajes, background: tramo.color }"
                                :title="`${tramo.texto}: ${tramo.viajes} viajes`"
                            />
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-3">
                            <div v-for="tramo in envejecimiento" :key="tramo.clave">
                                <dt class="flex items-center gap-1.5 text-[12px] text-[var(--color-tenue)]">
                                    <span class="size-2 rounded-full" :style="{ background: tramo.color }" />
                                    {{ tramo.texto }}
                                </dt>
                                <dd class="cifra mt-0.5 text-[18px] leading-tight">{{ tramo.viajes }}</dd>
                                <dd class="cifra text-[12px] text-[var(--color-tenue)]">{{ pesos(tramo.valor, true) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="tabla-scroll border-t border-[var(--color-borde)]">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="bg-[var(--color-superficie-sutil)] text-left text-[12px] text-[var(--color-apagado)]">
                                    <th class="py-2.5 pl-5 pr-3">Obra</th>
                                    <th class="w-40 px-3 py-2.5">Viajes</th>
                                    <th class="w-32 px-3 py-2.5">Esperando</th>
                                    <th class="w-32 py-2.5 pl-3 pr-5 text-right">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="fila in porObra" :key="fila.obra" class="border-t border-[var(--color-borde)] hover:bg-[var(--color-superficie-sutil)]">
                                    <td class="py-3 pl-5 pr-3 font-medium">{{ fila.obra }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="cifra w-6 text-right">{{ fila.viajes }}</span>
                                            <span class="h-1.5 flex-1 rounded-full bg-[var(--color-neutro-fondo)]">
                                                <span class="block h-full rounded-full bg-[var(--color-tinta)]" :style="{ width: `${(fila.viajes / maxViajesObra) * 100}%` }" />
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="chip cifra" :class="tramoDe(fila.dias).chip" :title="`Desde el ${fecha(fila.masAntiguo, 'larga')}`">
                                            <Icono nombre="reloj" :tamano="12" />
                                            {{ fila.dias }} {{ fila.dias === 1 ? 'día' : 'días' }}
                                        </span>
                                    </td>
                                    <td class="cifra py-3 pl-3 pr-5 text-right">{{ pesos(fila.valor) }}</td>
                                </tr>
                                <tr v-if="!porObra.length">
                                    <td colspan="4" class="px-5 py-10 text-center text-[var(--color-apagado)]">
                                        No hay viajes esperando vale.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="flex flex-col gap-6">
                    <!-- Cuentas abiertas -->
                    <section class="tarjeta">
                        <div class="flex items-center gap-3 px-5 pb-3 pt-5">
                            <h2 class="text-[16px]">Liquidaciones abiertas</h2>
                            <span class="cifra chip bg-[var(--color-neutro-fondo)] text-[var(--color-apagado)]">{{ totalCuentas }}</span>
                            <router-link :to="{ name: 'liquidaciones' }" class="ml-auto text-[13px] font-medium text-[var(--color-enlace)]">Ver todas</router-link>
                        </div>

                        <router-link
                            v-for="cuenta in cuentasAbiertas"
                            :key="cuenta.id"
                            :to="{ name: 'liquidaciones.detalle', params: { id: cuenta.id } }"
                            class="group flex items-center gap-3 border-t border-[var(--color-borde)] px-5 py-3 hover:bg-[var(--color-superficie-sutil)]"
                        >
                            <span
                                class="chip w-14 justify-center"
                                :class="cuenta.tipo === 'cobro' ? 'bg-[var(--color-info-fondo)] text-[var(--color-info)]' : 'bg-[var(--color-neutro-fondo)] text-[var(--color-apagado)]'"
                            >
                                {{ cuenta.tipo === 'cobro' ? 'Cobro' : 'Pago' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-medium">{{ cuenta.destinatario }}</p>
                                <p class="text-[12px] text-[var(--color-tenue)]">
                                    Cuenta #{{ cuenta.numero_cuenta }} · {{ numero(cuenta.total_viajes) }} viajes
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="cifra text-[14px]">{{ pesos(cuenta.saldo) }}</p>
                                <p class="text-[12px] text-[var(--color-tenue)]">saldo</p>
                            </div>
                            <Icono nombre="flecha" :tamano="16" class="text-[var(--color-tenue)] transition-transform group-hover:translate-x-0.5" />
                        </router-link>

                        <p v-if="!cuentasAbiertas.length" class="border-t border-[var(--color-borde)] px-5 py-8 text-center text-[13px] text-[var(--color-apagado)]">
                            No hay cuentas abiertas.
                        </p>
                    </section>

                    <!-- Últimos vales -->
                    <section class="tarjeta">
                        <div class="flex items-center gap-3 px-5 pb-3 pt-5">
                            <h2 class="text-[16px]">Últimos vales</h2>
                            <router-link :to="{ name: 'vales' }" class="ml-auto text-[13px] font-medium text-[var(--color-enlace)]">Ver todos</router-link>
                        </div>

                        <div v-for="vale in ultimosVales" :key="vale.id" class="flex items-center gap-3 border-t border-[var(--color-borde)] px-5 py-3">
                            <span class="cifra flex h-8 min-w-12 items-center justify-center rounded-md bg-[var(--color-crema)] px-2 text-[13px] font-medium">
                                {{ vale.numero }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px]">{{ vale.obra }}</p>
                                <p class="truncate text-[12px] text-[var(--color-tenue)]">
                                    {{ numero(vale.total_viajes) }} viajes<template v-if="vale.firmado_por"> · {{ vale.firmado_por }}</template>
                                </p>
                            </div>
                            <span class="cifra text-[12px] text-[var(--color-tenue)]">{{ fecha(vale.fecha_vale) }}</span>
                        </div>

                        <p v-if="!ultimosVales.length" class="border-t border-[var(--color-borde)] px-5 py-8 text-center text-[13px] text-[var(--color-apagado)]">
                            Todavía no se ha registrado ningún vale.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </Cargando>
</template>
