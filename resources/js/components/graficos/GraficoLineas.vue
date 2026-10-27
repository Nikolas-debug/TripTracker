<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { SERIES, TINTA, escalaBonita, compacto } from './paleta'

/**
 * Líneas sobre un solo eje. Nunca dos escalas: dos medidas de magnitudes
 * distintas van en dos gráficos, no en uno con dos ejes.
 *
 * Cuando se pasan dos series se pinta además el área entre ellas, que es como
 * se lee el margen de un vistazo sin inventar una tercera línea redundante.
 */
const props = defineProps({
    // [{ etiqueta: 'Semana del 24', valores: [350000, 200000] }]
    puntos: { type: Array, required: true },
    // [{ nombre: 'Cobrado' }, { nombre: 'Pagado' }]
    series: { type: Array, required: true },
    areaEntreSeries: { type: Boolean, default: false },
    alto: { type: Number, default: 220 },
})

const contenedor = ref(null)
const ancho = ref(720)
const activo = ref(null)

let observador

const MARGEN = { arriba: 16, derecha: 76, abajo: 28, izquierda: 60 }

const anchoPlot = computed(() => Math.max(120, ancho.value - MARGEN.izquierda - MARGEN.derecha))
const altoPlot = computed(() => props.alto - MARGEN.arriba - MARGEN.abajo)

const maximo = computed(() =>
    Math.max(1, ...props.puntos.flatMap((p) => p.valores.map((v) => v ?? 0))),
)

const escala = computed(() => escalaBonita(maximo.value))

function x(indice) {
    if (props.puntos.length <= 1) return MARGEN.izquierda + anchoPlot.value / 2
    return MARGEN.izquierda + (indice / (props.puntos.length - 1)) * anchoPlot.value
}

function y(valor) {
    return MARGEN.arriba + altoPlot.value - ((valor ?? 0) / escala.value.tope) * altoPlot.value
}

const trazos = computed(() =>
    props.series.map((serie, s) => ({
        ...serie,
        color: SERIES[s % SERIES.length],
        d: props.puntos.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p.valores[s])}`).join(' '),
        ultimo: props.puntos.at(-1)?.valores[s] ?? 0,
    })),
)

/** El polígono entre la primera y la segunda serie. */
const areaMargen = computed(() => {
    if (!props.areaEntreSeries || props.series.length < 2) return null

    const arriba = props.puntos.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p.valores[0])}`).join(' ')
    const abajo = [...props.puntos]
        .map((p, i) => ({ p, i }))
        .reverse()
        .map(({ p, i }) => `L ${x(i)} ${y(p.valores[1])}`)
        .join(' ')

    return `${arriba} ${abajo} Z`
})

/** El punto más cercano al cursor: no hay que acertarle al marcador. */
function alMover(evento) {
    const caja = contenedor.value.getBoundingClientRect()
    const relativo = evento.clientX - caja.left - MARGEN.izquierda
    const paso = props.puntos.length > 1 ? anchoPlot.value / (props.puntos.length - 1) : anchoPlot.value

    const indice = Math.round(relativo / paso)
    activo.value = indice >= 0 && indice < props.puntos.length ? indice : null
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
    <div ref="contenedor" class="relative w-full" @mousemove="alMover" @mouseleave="activo = null">
        <svg :width="ancho" :height="alto" class="block">
            <!-- Rejilla: hairline sólida, un paso por encima de la superficie -->
            <g>
                <line
                    v-for="tick in escala.ticks"
                    :key="tick"
                    :x1="MARGEN.izquierda"
                    :x2="ancho - MARGEN.derecha"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    :stroke="tick === 0 ? TINTA.eje : TINTA.rejilla"
                    stroke-width="1"
                />
                <text
                    v-for="tick in escala.ticks"
                    :key="`t${tick}`"
                    :x="MARGEN.izquierda - 10"
                    :y="y(tick) + 3.5"
                    text-anchor="end"
                    :fill="TINTA.apagada"
                    style="font-size: 10.5px; font-variant-numeric: tabular-nums"
                >
                    {{ compacto(tick) }}
                </text>
            </g>

            <!-- El margen como área entre las dos líneas -->
            <path v-if="areaMargen" :d="areaMargen" :fill="SERIES[0]" fill-opacity="0.1" />

            <!-- Guía vertical del punto activo -->
            <line
                v-if="activo !== null"
                :x1="x(activo)"
                :x2="x(activo)"
                :y1="MARGEN.arriba"
                :y2="MARGEN.arriba + altoPlot"
                :stroke="TINTA.eje"
                stroke-width="1"
            />

            <!-- Las líneas -->
            <path
                v-for="trazo in trazos"
                :key="trazo.nombre"
                :d="trazo.d"
                fill="none"
                :stroke="trazo.color"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <!-- Marcador del punto activo, con anillo del color de la superficie -->
            <template v-if="activo !== null">
                <circle
                    v-for="(trazo, s) in trazos"
                    :key="`m${s}`"
                    :cx="x(activo)"
                    :cy="y(puntos[activo].valores[s])"
                    r="4.5"
                    :fill="trazo.color"
                    :stroke="TINTA.superficie"
                    stroke-width="2"
                />
            </template>

            <!-- Etiqueta solo en el extremo: nunca un número en cada punto -->
            <template v-if="puntos.length">
                <text
                    v-for="(trazo, s) in trazos"
                    :key="`e${s}`"
                    :x="ancho - MARGEN.derecha + 10"
                    :y="y(trazo.ultimo) + 3.5"
                    :fill="TINTA.secundaria"
                    style="font-size: 11px; font-variant-numeric: tabular-nums"
                >
                    {{ compacto(trazo.ultimo) }}
                </text>
            </template>

            <!-- Eje de tiempo: primera, última y alguna intermedia -->
            <g>
                <text
                    v-for="(punto, i) in puntos"
                    v-show="i === 0 || i === puntos.length - 1 || i % Math.ceil(puntos.length / 5) === 0"
                    :key="`x${i}`"
                    :x="x(i)"
                    :y="alto - 8"
                    text-anchor="middle"
                    :fill="TINTA.apagada"
                    style="font-size: 10.5px"
                >
                    {{ punto.etiqueta }}
                </text>
            </g>
        </svg>

        <!-- Tooltip: complementa, nunca es la única vía al dato -->
        <div
            v-if="activo !== null"
            class="flotante pointer-events-none absolute z-10 min-w-40 px-3 py-2"
            :style="{
                left: `${Math.min(x(activo) + 12, ancho - 170)}px`,
                top: `${MARGEN.arriba}px`,
            }"
        >
            <p class="mb-1 text-[11px] font-medium text-[var(--color-tinta)]">{{ puntos[activo].etiqueta }}</p>
            <p v-for="(trazo, s) in trazos" :key="`tt${s}`" class="flex items-center gap-2 text-[11.5px]">
                <span class="size-2 shrink-0 rounded-full" :style="{ background: trazo.color }"></span>
                <span class="text-[var(--color-apagado)]">{{ trazo.nombre }}</span>
                <span class="cifra ml-auto font-medium text-[var(--color-tinta)]">
                    {{ (puntos[activo].valores[s] ?? 0).toLocaleString('es-CO') }}
                </span>
            </p>
        </div>
    </div>
</template>
