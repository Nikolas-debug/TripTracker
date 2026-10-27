<script setup>
import { computed } from 'vue'
import { ESTADOS } from '../lib/formato'

const props = defineProps({ valor: { type: String, required: true } })

const estado = computed(() => ESTADOS[props.valor] ?? { texto: props.valor, clase: 'bg-[var(--color-anulado-fondo)] text-[var(--color-anulado)]' })
</script>

<template>
    <!-- El punto repite el color, el texto dice el estado: nunca solo color -->
    <span class="chip" :class="estado.clase">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ estado.texto }}
    </span>
</template>
