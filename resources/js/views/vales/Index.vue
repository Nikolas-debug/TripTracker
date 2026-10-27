<script setup>
import { onMounted, ref } from 'vue'
import api from '../../lib/api'
import { numero } from '../../lib/formato'
import Encabezado from '../../components/Encabezado.vue'
import Cargando from '../../components/Cargando.vue'
import Paginacion from '../../components/Paginacion.vue'

const vales = ref([])
const meta = ref(null)
const cargando = ref(true)

async function cargar(page = 1) {
    cargando.value = true

    try {
        const { data } = await api.get('vales', { params: { page, por_pagina: 25 } })
        vales.value = data.data
        meta.value = data.meta
    } finally {
        cargando.value = false
    }
}

onMounted(() => cargar())
</script>

<template>
    <Encabezado titulo="Vales" subtitulo="Los papeles firmados que respaldan los viajes">
        <template #acciones>
            <router-link :to="{ name: 'vales.registrar' }" class="boton-primario">Registrar vale</router-link>
        </template>
    </Encabezado>

    <Cargando :activo="cargando" :vacio="!vales.length" mensaje-vacio="Todavía no se ha registrado ningún vale.">
        <div class="bg-white">
            <div class="tabla-scroll">
            <table class="w-full text-[12.5px]">
                <thead>
                    <tr class="bg-[var(--color-superficie-sutil)] text-left text-[11px] font-medium text-[var(--color-apagado)]">
                        <th class="w-24 py-2 pl-4 sm:pl-8 pr-2.5">NÚMERO</th>
                        <th class="px-2.5 py-2">OBRA</th>
                        <th class="w-44 px-2.5 py-2">FIRMADO POR</th>
                        <th class="w-28 px-2.5 py-2">FECHA</th>
                        <th class="w-28 px-2.5 py-2">RECIBIDO</th>
                        <th class="w-24 py-2 pl-2.5 pr-4 sm:pr-8 text-right">VIAJES</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="vale in vales" :key="vale.id" class="border-b border-[var(--color-borde)] hover:bg-[var(--color-superficie-sutil)]">
                        <td class="cifra py-2.5 pl-4 sm:pl-8 pr-2.5 font-medium">{{ vale.numero }}</td>
                        <td class="px-2.5 py-2.5">{{ vale.obra }}</td>
                        <td class="px-2.5 py-2.5 text-[var(--color-apagado)]">{{ vale.firmado_por ?? '—' }}</td>
                        <td class="cifra px-2.5 py-2.5 text-[var(--color-apagado)]">{{ vale.fecha_vale }}</td>
                        <td class="cifra px-2.5 py-2.5 text-[var(--color-apagado)]">{{ vale.fecha_recepcion ?? '—' }}</td>
                        <td class="cifra py-2.5 pl-2.5 pr-4 sm:pr-8 text-right font-medium">{{ numero(vale.total_viajes) }}</td>
                    </tr>
                </tbody>
            </table>
            </div>

            <Paginacion :meta="meta" @ir="cargar" />
        </div>
    </Cargando>
</template>
