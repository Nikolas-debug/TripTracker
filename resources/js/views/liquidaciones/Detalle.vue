<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { mensajeDeError } from '../../lib/api'
import { pesos, numero, hoy } from '../../lib/formato'
import { useAuth } from '../../stores/auth'
import { useAvisos } from '../../stores/avisos'
import Estado from '../../components/Estado.vue'
import Cargando from '../../components/Cargando.vue'
import Icono from '../../components/Icono.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const auth = useAuth()
const avisos = useAvisos()
const router = useRouter()

const cuenta = ref(null)
const cargando = ref(true)

const anticipo = reactive({ abierto: false, fecha: hoy(), valor: '', observacion: '' })

const esCobro = computed(() => cuenta.value?.tipo === 'cobro')
const esMaterial = computed(() => cuenta.value?.tipo === 'material')
const abierta = computed(() => cuenta.value?.estado === 'abierta')

/**
 * Los viajes agrupados por ruta y material, como en las cuentas del Excel:
 * una línea por combinación, con el total de viajes y m³.
 */
const porRuta = computed(() => {
    if (!cuenta.value?.viajes) return []

    const mapa = new Map()

    for (const viaje of cuenta.value.viajes) {
        // En la de material, el unitario ES el precio del material por m³.
        const unitario = esMaterial.value ? viaje.valor_material_m3 : esCobro.value ? viaje.valor_cobro_unitario : viaje.valor_pago_unitario
        const materialM3 = esCobro.value ? viaje.valor_material_m3 : null
        // Los precios van en la clave: si la arena subió a mitad del periodo, son dos filas.
        const clave = `${viaje.ruta}|${viaje.material ?? 'retiro'}|${unitario}|${materialM3}`
        const total = esMaterial.value ? viaje.total_material : esCobro.value ? viaje.total_cobro : viaje.total_pago

        const fila = mapa.get(clave) ?? {
            ruta: viaje.ruta,
            material: viaje.material,
            viajes: 0,
            m3: 0,
            unitario,
            materialM3,
            unidad: esMaterial.value ? 'm3' : esCobro.value ? viaje.unidad_cobro : viaje.unidad_pago,
            subtotal: 0,
        }

        fila.viajes += 1
        fila.m3 += viaje.m3_confirmado ?? viaje.m3_registrado
        fila.subtotal += total ?? 0

        mapa.set(clave, fila)
    }

    return [...mapa.values()]
})

// Quien cobra: va igual en todas las cuentas de cobro.
const EMPRESA = { nombre: 'GRUP MULTIPURPOSE S.A.S', nit: '901.744.396-2', representante: 'KARINA PATERNINA', ciudad: 'Montería' }

function diaMes(iso, conAnio = false) {
    return new Date(`${iso}T12:00:00`).toLocaleDateString('es-CO', {
        day: 'numeric', month: 'long', ...(conAnio && { year: 'numeric' }),
    })
}

/** "25 al 27 de agosto de 2026", sin repetir mes ni año cuando coinciden. */
const periodoEnTexto = computed(() => {
    const { desde, hasta } = cuenta.value.periodo

    if (desde === hasta) return diaMes(desde, true)
    if (desde.slice(0, 7) === hasta.slice(0, 7)) return `${Number(desde.slice(8))} al ${diaMes(hasta, true)}`
    return `${diaMes(desde, desde.slice(0, 4) !== hasta.slice(0, 4))} al ${diaMes(hasta, true)}`
})

function descargarPdf() {
    const titulo = document.title
    // El navegador propone el título como nombre del archivo PDF.
    document.title = `Cuenta de cobro N°${cuenta.value.numero_cuenta} ${cuenta.value.destinatario}`
    window.print()
    document.title = titulo
}

async function cargar() {
    cargando.value = true

    try {
        const { data } = await api.get(`liquidaciones/${props.id}`)
        cuenta.value = data.data ?? data
    } finally {
        cargando.value = false
    }
}

async function agregarAnticipo() {
    try {
        await api.post(`liquidaciones/${props.id}/anticipos`, {
            fecha: anticipo.fecha,
            valor: Number(anticipo.valor),
            observacion: anticipo.observacion || null,
        })

        avisos.ok('Anticipo registrado.')
        anticipo.abierto = false
        anticipo.valor = ''
        anticipo.observacion = ''
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo registrar el anticipo.'))
    }
}

async function quitarAnticipo(id) {
    try {
        await api.delete(`anticipos/${id}`)
        avisos.ok('Anticipo eliminado.')
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e))
    }
}

onMounted(cargar)

async function cerrar() {
    try {
        await api.post(`liquidaciones/${props.id}/cerrar`)
        avisos.ok('Cuenta cerrada. Sus viajes quedaron bloqueados.')
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo cerrar la cuenta.'))
    }
}
</script>

<template>
    <Cargando :activo="cargando">
        <template v-if="cuenta">
            <header class="sticky top-14 z-20 flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-[var(--color-borde)] bg-white/95 px-4 py-4 backdrop-blur sm:px-8 sm:py-5 lg:top-0">
                <button class="text-[var(--color-apagado)] hover:text-[var(--color-tinta)]" @click="router.push({ name: 'liquidaciones' })">←</button>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-[24px] font-normal leading-tight tracking-[-0.01em]">
                            Cuenta #{{ cuenta.numero_cuenta }} · {{ cuenta.destinatario }}
                        </h1>
                        <Estado :valor="cuenta.estado" />
                    </div>
                    <p class="cifra text-xs text-[var(--color-apagado)]">
                        {{ esMaterial ? 'Material pagado a la cantera' : esCobro ? 'Cobro al consorcio' : 'Pago a dueño' }} ·
                        {{ cuenta.periodo.desde }} — {{ cuenta.periodo.hasta }}
                    </p>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <button v-if="esCobro" class="boton-secundario" @click="descargarPdf">
                        Cuenta de cobro PDF
                    </button>
                    <button
                        v-if="abierta && auth.esAdministrador"
                        class="boton-primario"
                        @click="cerrar"
                    >
                        Cerrar cuenta
                    </button>
                    <span v-else-if="abierta" class="text-xs text-[var(--color-tenue)]">
                        Solo el administrador puede cerrar la cuenta
                    </span>
                </div>
            </header>

            <div class="flex gap-4 p-6">
                <!-- Detalle -->
                <section class="tarjeta min-w-0 flex-1">
                    <div class="flex items-center border-b border-[var(--color-borde)] px-5 py-3.5">
                        <h2 class="text-[15px] font-medium">Viajes confirmados en el periodo</h2>
                        <span class="cifra ml-auto text-[12.5px] text-[var(--color-apagado)]">
                            {{ numero(cuenta.total_viajes) }} viajes
                        </span>
                    </div>

                    <div class="tabla-scroll">
                    <table class="w-full text-[12.5px]">
                        <thead>
                            <tr class="bg-[var(--color-superficie-sutil)] text-left text-[11px] font-medium text-[var(--color-apagado)]">
                                <th class="py-2.5 pl-5 pr-2.5">RUTA</th>
                                <th class="w-32 px-2.5 py-2.5">MATERIAL</th>
                                <th class="w-20 px-2.5 py-2.5 text-right">VIAJES</th>
                                <th class="w-20 px-2.5 py-2.5 text-right">M³</th>
                                <th class="w-32 px-2.5 py-2.5 text-right">UNITARIO</th>
                                <th class="w-36 py-2.5 pl-2.5 pr-5 text-right">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(fila, i) in porRuta" :key="i" class="border-b border-[var(--color-borde)]">
                                <td class="py-2.5 pl-5 pr-2.5">{{ fila.ruta }}</td>
                                <td class="px-2.5 py-2.5 text-[var(--color-cuerpo)]">
                                    <span v-if="fila.material">{{ fila.material }}</span>
                                    <span v-else class="italic text-[var(--color-tenue)]">retiro</span>
                                </td>
                                <td class="cifra px-2.5 py-2.5 text-right">{{ fila.viajes }}</td>
                                <td class="cifra px-2.5 py-2.5 text-right text-[var(--color-apagado)]">{{ numero(fila.m3) }}</td>
                                <td class="cifra px-2.5 py-2.5 text-right">
                                    {{ pesos(fila.unitario) }}
                                    <span class="text-[var(--color-tenue)]">/{{ fila.unidad === 'm3' ? 'm³' : 'viaje' }}</span>
                                    <span v-if="fila.materialM3" class="block text-[11.5px] text-[var(--color-tenue)]">+ {{ pesos(fila.materialM3) }}/m³ material</span>
                                </td>
                                <td class="cifra py-2.5 pl-2.5 pr-5 text-right font-medium">{{ pesos(fila.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <p v-if="abierta" class="border-t border-[var(--color-borde)] bg-[var(--color-superficie-sutil)] px-5 py-3 text-[12.5px] text-[var(--color-apagado)]">
                        Al cerrar la cuenta, estos viajes quedan bloqueados y no se pueden editar.
                    </p>
                </section>

                <!-- Resumen y anticipos -->
                <div class="flex w-full max-w-[372px] shrink-0 flex-col gap-3.5">
                    <div class="tarjeta space-y-3 p-5">
                        <div class="flex items-baseline">
                            <span class="text-[13px] text-[var(--color-apagado)]">Total bruto</span>
                            <span class="cifra ml-auto text-base font-medium">{{ pesos(cuenta.total_bruto) }}</span>
                        </div>
                        <p v-if="esMaterial" class="text-[12.5px] text-[var(--color-apagado)]">
                            Pagado en la cantera al cargar, con plata de la empresa que lleva el conductor. Se le cobra al consorcio en su cuenta de cobro.
                        </p>
                        <div v-else class="flex items-baseline">
                            <span class="text-[13px] text-[var(--color-apagado)]">Anticipos</span>
                            <span class="cifra ml-auto text-base font-medium text-[var(--color-pendiente)]">
                                {{ cuenta.total_anticipos ? `− ${pesos(cuenta.total_anticipos)}` : '—' }}
                            </span>
                        </div>
                        <div v-if="!esMaterial" class="h-px bg-[var(--color-borde)]"></div>
                        <div v-if="!esMaterial" class="flex items-baseline">
                            <span class="text-sm font-medium">Saldo por pagar</span>
                            <span class="cifra ml-auto text-[22px] font-medium tracking-tight">{{ pesos(cuenta.saldo) }}</span>
                        </div>
                    </div>

                    <div v-if="!esMaterial" class="tarjeta flex-1">
                        <div class="flex items-center border-b border-[var(--color-borde)] px-5 py-3.5">
                            <h2 class="text-[15px] font-medium">Anticipos</h2>
                            <button
                                v-if="abierta"
                                class="ml-auto text-[12.5px] font-medium text-[var(--color-enlace)]"
                                @click="anticipo.abierto = !anticipo.abierto"
                            >
                                + Agregar
                            </button>
                        </div>

                        <!-- Alta rápida -->
                        <div v-if="anticipo.abierto" class="space-y-3 border-b border-[var(--color-borde)] bg-[var(--color-superficie-sutil)] p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div>
                                    <label class="etiqueta">Fecha</label>
                                    <input v-model="anticipo.fecha" type="date" class="campo py-1.5 text-[12.5px]">
                                </div>
                                <div>
                                    <label class="etiqueta">Valor</label>
                                    <input v-model="anticipo.valor" type="number" class="campo cifra py-1.5 text-[12.5px]">
                                </div>
                            </div>
                            <input v-model="anticipo.observacion" class="campo py-1.5 text-[12.5px]" placeholder="Observación (opcional)">
                            <button class="boton-primario w-full justify-center py-1.5" @click="agregarAnticipo">Registrar anticipo</button>
                        </div>

                        <div
                            v-for="a in cuenta.anticipos"
                            :key="a.id"
                            class="group flex items-center gap-3 border-b border-[var(--color-borde)] px-5 py-3 last:border-0"
                        >
                            <div>
                                <p class="cifra text-[13px] font-medium">{{ a.fecha }}</p>
                                <p class="text-[11.5px] text-[var(--color-tenue)]">{{ a.observacion ?? 'Abono' }}</p>
                            </div>
                            <span class="cifra ml-auto text-sm font-medium">{{ pesos(a.valor) }}</span>
                            <button
                                v-if="abierta"
                                class="opacity-0 transition group-hover:opacity-100 text-[var(--color-tenue)] hover:text-[var(--color-critico)]"
                                @click="quitarAnticipo(a.id)"
                            ><Icono nombre="x" :tamano="16" /></button>
                        </div>

                        <p v-if="!cuenta.anticipos?.length && !anticipo.abierto" class="px-5 py-8 text-center text-[12.5px] text-[var(--color-tenue)]">
                            Cada abono que se registre aquí baja el saldo de la cuenta.
                        </p>
                    </div>
                </div>
            </div>

            <!-- La cuenta de cobro, igual al formato en Word. Solo existe al imprimir. -->
            <Teleport to="body">
                <article v-if="esCobro" class="cuenta-impresa">
                    <p>{{ EMPRESA.ciudad }}, {{ diaMes(hoy(), true) }}.</p>

                    <div class="my-12 text-center font-bold uppercase">
                        <p class="mb-6">Cuenta de cobro N°{{ cuenta.numero_cuenta }}</p>
                        <p>{{ cuenta.destinatario }}</p>
                        <p>NIT: {{ cuenta.nit_destinatario }}</p>
                        <p class="mt-6">Debe a:</p>
                        <p>{{ EMPRESA.nombre }}</p>
                        <p>NIT: {{ EMPRESA.nit }}</p>
                    </div>

                    <div class="space-y-4 text-justify">
                        <p>La suma de: {{ cuenta.total_en_letras }}. M/C (${{ pesos(cuenta.total_bruto) }}=)</p>
                        <p>Por concepto de: Transporte de material a obra.</p>
                        <p>El periodo relacionado es del {{ periodoEnTexto.toUpperCase() }}.</p>
                        <p>Se anexan los ({{ cuenta.total_viajes }}) recibos originales que fueron entregados en obra.</p>
                        <p class="pt-8">Cordialmente,</p>
                    </div>

                    <div class="mt-24 font-bold">
                        <p>{{ EMPRESA.representante }}.</p>
                        <p>R.L {{ EMPRESA.nombre }}</p>
                    </div>
                </article>
            </Teleport>
        </template>
    </Cargando>
</template>
