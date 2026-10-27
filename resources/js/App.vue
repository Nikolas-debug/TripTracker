<script setup>
import { useAvisos } from './stores/avisos'
import Icono from './components/Icono.vue'

const avisos = useAvisos()
</script>

<template>
    <router-view />

    <!-- Avisos: tinta oscura, abajo en el celular y arriba a la derecha en escritorio -->
    <div
        class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col gap-2 sm:inset-x-auto sm:bottom-auto sm:right-6 sm:top-6 sm:w-[360px]"
        style="margin-bottom: env(safe-area-inset-bottom)"
        aria-live="polite"
    >
        <TransitionGroup name="aviso">
            <div
                v-for="aviso in avisos.lista"
                :key="aviso.id"
                class="pointer-events-auto flex items-start gap-3 rounded-xl px-4 py-3 text-[14px] text-white shadow-[0_8px_30px_rgb(24_29_38/0.25)]"
                :class="aviso.tipo === 'error' ? 'bg-[var(--color-critico)]' : 'bg-[var(--color-tinta)]'"
                role="status"
            >
                <Icono :nombre="aviso.tipo === 'error' ? 'alerta' : 'check'" :tamano="18" class="mt-px opacity-80" />
                <span class="flex-1">{{ aviso.texto }}</span>
                <button class="-m-1 rounded-full p-1 opacity-70 hover:opacity-100" aria-label="Cerrar aviso" @click="avisos.quitar(aviso.id)">
                    <Icono nombre="x" :tamano="16" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.aviso-enter-active {
    transition: opacity 240ms var(--ease-salida), transform 240ms var(--ease-salida);
}

.aviso-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.aviso-move {
    transition: transform 240ms var(--ease-salida);
}

.aviso-enter-from,
.aviso-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}
</style>
