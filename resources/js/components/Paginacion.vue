<script setup>
import Icono from './Icono.vue'

defineProps({
    meta: { type: Object, default: null },
})

defineEmits(['ir'])
</script>

<template>
    <div v-if="meta" class="flex items-center gap-4 border-t border-[var(--color-borde)] px-4 py-3 sm:px-8">
        <p class="text-[13px] text-[var(--color-tenue)]">
            <span class="cifra text-[var(--color-tinta)]">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}</span>
            de <span class="cifra text-[var(--color-tinta)]">{{ meta.total }}</span>
        </p>

        <div class="ml-auto flex items-center gap-1.5">
            <button
                class="boton-secundario size-10 !min-h-0 !rounded-full !p-0"
                aria-label="Página anterior"
                :disabled="meta.current_page <= 1"
                @click="$emit('ir', meta.current_page - 1)"
            >
                <Icono nombre="atras" :tamano="16" />
            </button>
            <span class="cifra px-2 text-[13px] text-[var(--color-apagado)]">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button
                class="boton-secundario size-10 !min-h-0 !rounded-full !p-0"
                aria-label="Página siguiente"
                :disabled="meta.current_page >= meta.last_page"
                @click="$emit('ir', meta.current_page + 1)"
            >
                <Icono nombre="flecha" :tamano="16" />
            </button>
        </div>
    </div>
</template>
