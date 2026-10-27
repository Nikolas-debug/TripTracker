<script setup>
import Icono from './Icono.vue'

defineProps({
    activo: { type: Boolean, default: false },
    vacio: { type: Boolean, default: false },
    mensajeVacio: { type: String, default: 'No hay nada que mostrar.' },
    filas: { type: Number, default: 6 },
})
</script>

<template>
    <!-- Esqueleto con la forma de una tabla: lo que viene es una lista, no un spinner -->
    <div v-if="activo" class="space-y-3 px-4 py-6 sm:px-8" aria-busy="true" aria-label="Cargando">
        <div v-for="i in filas" :key="i" class="flex items-center gap-4" :style="{ opacity: 1 - i * 0.12 }">
            <div class="esqueleto h-4 w-20" />
            <div class="esqueleto h-4 flex-1" />
            <div class="esqueleto hidden h-4 w-32 sm:block" />
            <div class="esqueleto h-4 w-24" />
        </div>
    </div>

    <div v-else-if="vacio" class="flex flex-col items-center px-6 py-20 text-center">
        <div class="flex size-11 items-center justify-center rounded-full bg-[var(--color-neutro-fondo)] text-[var(--color-apagado)]">
            <Icono nombre="check" />
        </div>
        <p class="mt-3 max-w-sm text-[14px] text-[var(--color-apagado)]">{{ mensajeVacio }}</p>
        <div class="mt-4"><slot name="vacio" /></div>
    </div>

    <slot v-else />
</template>
