<script setup>
/**
 * El equivalente en tabla de un gráfico. No es un extra: un tooltip nunca
 * puede ser la única forma de leer un valor, y quien no distingue los colores
 * necesita las cifras.
 */
defineProps({
    columnas: { type: Array, required: true },
    filas: { type: Array, required: true },
})
</script>

<template>
    <div class="max-h-64 overflow-auto border-t border-[var(--color-borde)]">
        <div class="tabla-scroll">
        <table class="w-full text-[12px]">
            <thead class="sticky top-0 bg-[var(--color-superficie-sutil)]">
                <tr class="text-left text-[10.5px] font-medium text-[var(--color-apagado)]">
                    <th
                        v-for="(col, i) in columnas"
                        :key="col.clave"
                        class="px-3 py-1.5"
                        :class="[col.derecha ? 'text-right' : '', i === 0 ? 'pl-4' : '']"
                    >
                        {{ col.texto }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(fila, f) in filas" :key="f" class="border-b border-[var(--color-borde)] last:border-0">
                    <td
                        v-for="(col, i) in columnas"
                        :key="col.clave"
                        class="px-3 py-1.5"
                        :class="[col.derecha ? 'cifra text-right' : '', i === 0 ? 'pl-4' : 'text-[var(--color-cuerpo)]']"
                    >
                        {{ fila[col.clave] }}
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</template>
