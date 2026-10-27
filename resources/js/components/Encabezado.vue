<script setup>
defineProps({
    titulo: { type: String, required: true },
    subtitulo: { type: String, default: '' },
})
</script>

<template>
    <header class="sticky top-14 z-20 flex flex-wrap items-center gap-x-4 gap-y-3 border-b border-[var(--color-borde)] bg-white/95 px-4 py-4 backdrop-blur sm:px-8 sm:py-5 lg:top-0">
        <div class="min-w-0">
            <h1 class="text-[24px] font-normal leading-tight tracking-[-0.01em]">{{ titulo }}</h1>
            <p v-if="subtitulo" class="mt-0.5 text-[13px] text-[var(--color-tenue)] first-letter:uppercase">{{ subtitulo }}</p>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <slot name="acciones" />
        </div>
    </header>
</template>
