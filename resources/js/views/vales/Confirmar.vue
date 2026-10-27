<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api, { mensajeDeError } from '../../lib/api'
import { pesos, hoy } from '../../lib/formato'
import { useAvisos } from '../../stores/avisos'
import Cargando from '../../components/Cargando.vue'
import Icono from '../../components/Icono.vue'

const router = useRouter()
const avisos = useAvisos()

const obras = ref([])
const pendientes = ref([])
const cargando = ref(false)
const guardando = ref(false)

const vale = reactive({
    numero: '',
    obra_id: '',
    fecha_vale: hoy(),
    fecha_recepcion: hoy(),
    firmado_por: '',
    observacion: '',
})

/** viaje_id -> { marcado, m3 } */
const seleccion = reactive({})

const marcados = computed(() =>
    pendientes.value.filter((v) => seleccion[v.id]?.marcado),
)

const ajustados = computed(() =>
    marcados.value.filter((v) => Number(seleccion[v.id].m3) !== v.m3_registrado),
)

const totalConfirmado = computed(() =>
    marcados.value.reduce((suma, viaje) => suma + cobroDe(viaje), 0),
)

function cobroDe(viaje) {
    const m3 = Number(seleccion[viaje.id]?.m3 ?? viaje.m3_registrado)
    const flete = viaje.unidad_cobro === 'm3' ? viaje.valor_cobro_unitario * m3 : viaje.valor_cobro_unitario
    // El material va por m³ encima del flete, con los m³ que dice el vale.
    return flete + (viaje.valor_material_m3 ?? 0) * m3
}

function alternarTodos(marcar) {
    for (const viaje of pendientes.value) {
        seleccion[viaje.id].marcado = marcar
    }
}

async function cargarPendientes() {
    pendientes.value = []

    if (!vale.obra_id) return

    cargando.value = true

    try {
        const { data } = await api.get('vales/pendientes', {
            params: { obra_id: vale.obra_id, por_pagina: 200 },
        })

        pendientes.value = data.data

        for (const viaje of data.data) {
            seleccion[viaje.id] = { marcado: false, m3: viaje.m3_registrado }
        }
    } finally {
        cargando.value = false
    }
}

async function confirmar() {
    if (!marcados.value.length) {
        avisos.error('Marca al menos un viaje que ampare este vale.')
        return
    }

    guardando.value = true

    try {
        const { data } = await api.post('vales', {
            ...vale,
            viajes: marcados.value.map((v) => ({
                viaje_id: v.id,
                m3_confirmado: Number(seleccion[v.id].m3),
            })),
        })

        avisos.ok(data.message)
        router.push({ name: 'viajes', query: { estado: 'confirmado' } })
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo registrar el vale.'))
    } finally {
        guardando.value = false
    }
}

watch(() => vale.obra_id, cargarPendientes)

onMounted(async () => {
    const { data } = await api.get('obras', { params: { por_pagina: 100, solo_activos: 1 } })
    obras.value = data.data
})
</script>

<template>
    <header class="sticky top-14 z-20 flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-[var(--color-borde)] bg-white/95 px-4 py-4 backdrop-blur sm:px-8 sm:py-5 lg:top-0">
        <button class="text-[var(--color-apagado)] hover:text-[var(--color-tinta)]" @click="router.back()"><Icono nombre="x" :tamano="16" /></button>
        <h1 class="text-[24px] font-normal leading-tight tracking-[-0.01em]">Registrar vale</h1>
        <span class="text-[13px] text-[var(--color-tenue)]">y confirmar los viajes que ampara</span>

        <div class="ml-auto flex items-center gap-2">
            <button class="boton-secundario" @click="router.back()">Cancelar</button>
            <button
                class="boton bg-[var(--color-confirmado)] text-white hover:opacity-90"
                :disabled="guardando || !marcados.length || !vale.numero"
                @click="confirmar"
            >
                {{ guardando ? 'Confirmando…' : `Confirmar ${marcados.length} viajes` }}
            </button>
        </div>
    </header>

    <div class="mx-auto max-w-[1360px] space-y-6 px-4 py-6 sm:px-8">
        <section class="tarjeta">
            <div class="flex items-center gap-2 border-b border-[var(--color-borde)] px-5 py-3.5">
                <h2 class="text-[15px] font-medium">Datos del vale</h2>
                <span class="text-[12.5px] text-[var(--color-tenue)]">— tal como viene firmado por el ingeniero de obra</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[200px_1fr_190px_260px] gap-3.5 p-5">
                <div>
                    <label class="etiqueta">Número de vale</label>
                    <input v-model="vale.numero" type="number" class="campo cifra" placeholder="1531">
                    <p class="mt-1 text-[11.5px] text-[var(--color-tenue)]">Único dentro de la obra</p>
                </div>

                <div>
                    <label class="etiqueta">Obra</label>
                    <select v-model="vale.obra_id" class="campo">
                        <option value="">Seleccionar…</option>
                        <option v-for="obra in obras" :key="obra.id" :value="obra.id">{{ obra.nombre }}</option>
                    </select>
                </div>

                <div>
                    <label class="etiqueta">Fecha del vale</label>
                    <input v-model="vale.fecha_vale" type="date" class="campo">
                </div>

                <div>
                    <label class="etiqueta">Firmado por</label>
                    <input v-model="vale.firmado_por" class="campo" placeholder="Ing. …">
                </div>
            </div>
        </section>

        <section class="tarjeta">
            <div class="flex items-center gap-2.5 border-b border-[var(--color-borde)] px-5 py-3.5">
                <h2 class="text-[15px] font-medium">Viajes por confirmar en esta obra</h2>
                <span class="text-[12.5px] text-[var(--color-tenue)]">— marca los que ampara este vale y corrige los m³ si el papel dice otra cosa</span>
                <span v-if="pendientes.length" class="cifra ml-auto rounded bg-[var(--color-pendiente-fondo)] px-2.5 py-1 text-xs font-medium text-[var(--color-pendiente)]">
                    {{ pendientes.length }} pendientes
                </span>
            </div>

            <Cargando
                :activo="cargando"
                :vacio="!pendientes.length"
                :mensaje-vacio="vale.obra_id ? 'Esta obra no tiene viajes esperando vale.' : 'Elige una obra para ver sus viajes pendientes.'"
            >
                <div class="tabla-scroll">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="bg-[var(--color-superficie-sutil)] text-left text-[11px] font-medium text-[var(--color-apagado)]">
                            <th class="w-11 py-2 pl-5 pr-2.5">
                                <input
                                    type="checkbox"
                                    class="size-3.5"
                                    :checked="marcados.length === pendientes.length && pendientes.length > 0"
                                    @change="alternarTodos($event.target.checked)"
                                >
                            </th>
                            <th class="w-20 px-2.5 py-2">FECHA</th>
                            <th class="w-24 px-2.5 py-2">PLACA</th>
                            <th class="px-2.5 py-2">RUTA Y MATERIAL</th>
                            <th class="w-24 px-2.5 py-2 text-right">M³ REG.</th>
                            <th class="w-32 px-2.5 py-2 text-right">M³ DEL VALE</th>
                            <th class="w-36 py-2 pl-2.5 pr-5 text-right">COBRO RESULT.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="viaje in pendientes"
                            :key="viaje.id"
                            class="border-b border-[var(--color-borde)]"
                            :class="seleccion[viaje.id]?.marcado ? 'bg-[var(--color-confirmado-fondo)]' : ''"
                        >
                            <td class="py-2 pl-5 pr-2.5">
                                <input v-model="seleccion[viaje.id].marcado" type="checkbox" class="size-3.5">
                            </td>
                            <td class="cifra px-2.5 py-2 text-[var(--color-cuerpo)]">{{ viaje.fecha_viaje }}</td>
                            <td class="cifra px-2.5 py-2 font-medium">{{ viaje.placa }}</td>
                            <td class="px-2.5 py-2 text-[var(--color-cuerpo)]">
                                {{ viaje.ruta }}
                                <span class="text-[var(--color-tenue)]"> · {{ viaje.material ?? 'retiro' }}</span>
                            </td>
                            <td class="cifra px-2.5 py-2 text-right text-[var(--color-apagado)]">{{ viaje.m3_registrado }}</td>
                            <td class="px-2.5 py-2">
                                <input
                                    v-model="seleccion[viaje.id].m3"
                                    type="number"
                                    min="1"
                                    max="60"
                                    class="campo cifra py-1.5 text-right"
                                    :class="Number(seleccion[viaje.id].m3) !== viaje.m3_registrado
                                        ? 'border-[var(--color-pendiente)] bg-[var(--color-pendiente-fondo)] font-medium text-[var(--color-pendiente)]'
                                        : ''"
                                    :disabled="!seleccion[viaje.id].marcado"
                                >
                            </td>
                            <td class="py-2 pl-2.5 pr-5 text-right">
                                <span class="cifra font-medium" :class="!seleccion[viaje.id].marcado ? 'text-[var(--color-tenue)]' : ''">
                                    {{ pesos(cobroDe(viaje)) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <div class="flex items-center gap-3 border-t border-[var(--color-borde)] bg-[var(--color-superficie-sutil)] px-5 py-3.5">
                    <p class="text-[13px]">
                        <span class="cifra font-medium">{{ marcados.length }}</span> viajes quedarán confirmados
                        <template v-if="vale.numero">
                            con el vale <span class="cifra font-medium">{{ vale.numero }}</span>
                        </template>
                    </p>

                    <span v-if="ajustados.length" class="rounded bg-[var(--color-pendiente-fondo)] px-2.5 py-1 text-xs text-[var(--color-pendiente)]">
                        {{ ajustados.length }} con m³ distintos a lo registrado
                    </span>

                    <p class="ml-auto text-[12.5px] text-[var(--color-apagado)]">
                        Total confirmado
                        <span class="cifra ml-1.5 text-[15px] font-medium text-[var(--color-tinta)]">{{ pesos(totalConfirmado, true) }}</span>
                    </p>
                </div>
            </Cargando>
        </section>
    </div>
</template>
