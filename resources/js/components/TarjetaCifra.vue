<script setup>
import { computed } from 'vue'
import Icono from './Icono.vue'

/**
 * Stat tile: etiqueta, valor, la variación contra el periodo anterior y,
 * opcionalmente, una chispa (sparkline) con la forma de esas mismas semanas.
 *
 * La chispa es contexto, no la fuente del dato — por eso no lleva eje ni
 * tooltip propios: quien necesita los números exactos de esas semanas ya los
 * tiene en el gráfico de abajo y su vista de tabla. Aquí solo se pregunta
 * "¿esto viene subiendo o cayendo?", y eso se lee sin ninguna cifra.
 */
const props = defineProps({
    etiqueta: { type: String, required: true },
    valor: { type: String, required: true },
    anterior: { type: Number, default: null },
    actual: { type: Number, default: null },
    subirEsBueno: { type: Boolean, default: true },
    destacado: { type: Boolean, default: false },
    puntos: { type: Array, default: null }, // [120, 340, 280, ...] — cronológico
})

const variacion = computed(() => {
    if (props.anterior === null || props.actual === null || props.anterior === 0) return null

    const porcentaje = ((props.actual - props.anterior) / Math.abs(props.anterior)) * 100

    return {
        porcentaje,
        texto: `${porcentaje > 0 ? '+' : ''}${porcentaje.toLocaleString('es-CO', { maximumFractionDigits: 0 })}%`,
        bueno: porcentaje === 0 ? null : (porcentaje > 0) === props.subirEsBueno,
    }
})

const ANCHO = 60
const ALTO = 20

/** Una polilínea de 60x20: suficiente para ver la forma, no para leerla como gráfico. */
const chispa = computed(() => {
    if (!props.puntos || props.puntos.length < 2) return null

    const min = Math.min(...props.puntos)
    const max = Math.max(...props.puntos)
    const rango = max - min || 1
    const paso = ANCHO / (props.puntos.length - 1)

    const coords = props.puntos.map((v, i) => [i * paso, ALTO - 2 - ((v - min) / rango) * (ALTO - 4)])

    return {
        linea: coords.map(([x, y], i) => `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`).join(' '),
        ultimo: coords.at(-1),
    }
})

const colorVariacion = computed(() => {
    const v = variacion.value
    if (!v || v.bueno === null) return props.destacado ? 'text-white/75' : 'text-[var(--color-apagado)]'
    // Sobre la tinta oscura el verde y el rojo no contrastan: menta y durazno.
    if (props.destacado) return v.bueno ? 'text-[var(--color-menta)]' : 'text-[var(--color-durazno)]'
    return v.bueno ? 'text-[var(--color-confirmado)]' : 'text-[var(--color-critico)]'
})

const colorChispa = computed(() => {
    if (!variacion.value || variacion.value.bueno === null) return props.destacado ? 'rgb(255 255 255 / 0.6)' : 'var(--color-tenue)'
    if (props.destacado) return variacion.value.bueno ? 'var(--color-menta)' : 'var(--color-durazno)'
    return variacion.value.bueno ? 'var(--color-confirmado)' : 'var(--color-critico)'
})
</script>

<template>
    <div class="p-5" :class="destacado ? 'firma-tinta' : 'tarjeta'">
        <div class="flex items-start justify-between gap-3">
            <p class="text-[13px]" :class="destacado ? 'text-white/75' : 'text-[var(--color-apagado)]'">{{ etiqueta }}</p>

            <!-- Chispa: solo forma, sin eje ni cifras propias -->
            <svg v-if="chispa" :width="ANCHO" :height="ALTO" class="mt-0.5 shrink-0 overflow-visible">
                <path :d="chispa.linea" fill="none" :stroke="colorChispa" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <circle :cx="chispa.ultimo[0]" :cy="chispa.ultimo[1]" r="1.75" :fill="colorChispa" />
            </svg>
        </div>

        <p
            class="mt-1.5 cifra font-normal tracking-[-0.02em]"
            :class="destacado ? 'text-[36px] leading-none' : 'text-[28px] leading-none'"
        >
            {{ valor }}
        </p>

        <p v-if="variacion" class="mt-2 flex items-center gap-1.5 text-[11.5px]">
            <!-- La flecha acompaña al color: el estado nunca se comunica solo con color -->
            <span
                :class="colorVariacion"
            >
                <Icono v-if="variacion.porcentaje !== 0" :nombre="variacion.porcentaje > 0 ? 'subir' : 'bajar'" :tamano="12" class="inline -mt-0.5" />
                {{ variacion.texto }}
            </span>
            <span :class="destacado ? 'text-white/60' : 'text-[var(--color-tenue)]'">vs. periodo anterior</span>
        </p>
    </div>
</template>
