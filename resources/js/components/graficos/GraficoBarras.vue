<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { SERIES, TINTA, compacto } from './paleta'

/**
 * Barras horizontales para comparar magnitudes entre categorías con nombres
 * largos (obras, placas).
 *
 * Una sola serie, un solo color: pintar cada barra de un tono distinto según
 * su tamaño duplicaría en color lo que ya dice la longitud, y quemaría el
 * único canal libre que queda.
 */
const props = defineProps({
    // [{ etiqueta: 'Parque San Jerónimo', valor: 12000000, detalle: '117 viajes' }]
    filas: { type: Array, required: true },
    color: { type: String, default: SERIES[0] },
    alturaFila: { type: Number, default: 30 },
})

const contenedor = ref(null)
const ancho = ref(560)
const activa = ref(null)

let observador

const ANCHO_ETIQUETA = 168
const ANCHO_VALOR = 92
const GROSOR = 18 // por debajo del tope de 24px: el resto de la banda es aire

const anchoBarra = computed(() => Math.max(60, ancho.value - ANCHO_ETIQUETA - ANCHO_VALOR))
const maximo = computed(() => Math.max(1, ...props.filas.map((f) => Math.abs(f.valor))))
const alto = computed(() => props.filas.length * props.alturaFila + 4)

function largo(valor) {
    return Math.max(2, (Math.abs(valor) / maximo.value) * anchoBarra.value)
}

function medir() {
    if (contenedor.value) ancho.value = contenedor.value.clientWidth
}

onMounted(() => {
    medir()
    observador = new ResizeObserver(medir)
    observador.observe(contenedor.value)
})

onBeforeUnmount(() => observador?.disconnect())
</script>

<template>
    <div ref="contenedor" class="relative w-full px-5 pb-4">
        <svg :width="ancho - 40" :height="alto" class="block overflow-visible">
            <g
                v-for="(fila, i) in filas"
                :key="fila.etiqueta"
                :transform="`translate(0, ${i * alturaFila})`"
                @mouseenter="activa = i"
                @mouseleave="activa = null"
            >
                <!-- Área de hover del alto completo de la banda, no solo de la barra -->
                <rect
                    x="0"
                    y="0"
                    :width="ancho - 40"
                    :height="alturaFila"
                    fill="transparent"
                />

                <text
                    x="0"
                    :y="alturaFila / 2 + 4"
                    :fill="TINTA.secundaria"
                    style="font-size: 11.5px"
                >
                    {{ fila.etiqueta.length > 26 ? `${fila.etiqueta.slice(0, 25)}…` : fila.etiqueta }}
                </text>

                <!-- Extremo redondeado en la punta, recto contra la línea base -->
                <rect
                    :x="ANCHO_ETIQUETA"
                    :y="(alturaFila - GROSOR) / 2"
                    :width="largo(fila.valor)"
                    :height="GROSOR"
                    rx="4"
                    :fill="fila.valor < 0 ? '#b42318' : color"
                    :fill-opacity="activa === null || activa === i ? 1 : 0.55"
                />
                <rect
                    :x="ANCHO_ETIQUETA"
                    :y="(alturaFila - GROSOR) / 2"
                    :width="Math.min(4, largo(fila.valor))"
                    :height="GROSOR"
                    :fill="fila.valor < 0 ? '#b42318' : color"
                    :fill-opacity="activa === null || activa === i ? 1 : 0.55"
                />

                <!-- El valor va en la punta, fuera de la barra: nunca recortado dentro -->
                <text
                    :x="ANCHO_ETIQUETA + largo(fila.valor) + 8"
                    :y="alturaFila / 2 + 4"
                    :fill="TINTA.primaria"
                    style="font-size: 11.5px; font-weight: 500; font-variant-numeric: tabular-nums"
                >
                    {{ compacto(fila.valor) }}
                </text>
            </g>
        </svg>

        <div
            v-if="activa !== null && filas[activa].detalle"
            class="flotante pointer-events-none absolute left-5 px-3 py-2"
            :style="{ top: `${activa * alturaFila + 26}px` }"
        >
            <p class="text-[11px] font-medium">{{ filas[activa].etiqueta }}</p>
            <p class="mt-0.5 text-[11px] text-[var(--color-apagado)]">{{ filas[activa].detalle }}</p>
        </div>
    </div>
</template>
