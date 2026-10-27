<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api, { mensajeDeError } from '../lib/api'
import { pesos } from '../lib/formato'
import { useAvisos } from '../stores/avisos'
import Icono from './Icono.vue'

/**
 * Qué materiales vende una cantera (cantera_material). Es lo que aparece en el
 * selector "Cantera / material" al registrar viajes. Quitar un material lo
 * desactiva, no lo borra: los viajes viejos siguen apuntando a él.
 */
const props = defineProps({ canteraId: { type: Number, required: true } })

const avisos = useAvisos()

const vinculos = ref([])
const materiales = ref([])
const nuevo = reactive({ material_id: '', precio_compra_m3: '', guardando: false })

const activos = computed(() => vinculos.value.filter((v) => v.activo))

// Los ya vinculados también salen: elegirlos de nuevo actualiza su precio.
const vinculados = computed(() => new Set(activos.value.map((v) => v.material_id)))
const actualizando = computed(() => vinculados.value.has(Number(nuevo.material_id)))

function editar(vinculo) {
    nuevo.material_id = vinculo.material_id
    nuevo.precio_compra_m3 = vinculo.precio_compra_m3 ?? ''
}

async function cargar() {
    const { data } = await api.get('cantera-material', { params: { cantera_id: props.canteraId, por_pagina: 100 } })
    vinculos.value = data.data
}

async function agregar() {
    if (!nuevo.material_id) return

    const datos = {
        cantera_id: props.canteraId,
        material_id: Number(nuevo.material_id),
        precio_compra_m3: nuevo.precio_compra_m3 === '' ? null : Number(nuevo.precio_compra_m3),
        activo: true,
    }

    // Si ya existió y se desactivó, se reactiva: el par cantera–material es único.
    const previo = vinculos.value.find((v) => v.material_id === datos.material_id)
    nuevo.guardando = true

    try {
        if (previo) {
            await api.put(`cantera-material/${previo.id}`, datos)
        } else {
            await api.post('cantera-material', datos)
        }

        nuevo.material_id = ''
        nuevo.precio_compra_m3 = ''
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo agregar el material.'))
    } finally {
        nuevo.guardando = false
    }
}

async function quitar(vinculo) {
    try {
        await api.delete(`cantera-material/${vinculo.id}`)
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo quitar el material.'))
    }
}

onMounted(async () => {
    const [, mats] = await Promise.all([
        cargar(),
        api.get('materiales', { params: { por_pagina: 200, solo_activos: 1 } }),
    ])
    materiales.value = mats.data.data
})
</script>

<template>
    <section class="rounded-[10px] border border-[var(--color-borde)] bg-[var(--color-superficie-sutil)] p-4">
        <h3 class="text-[14px] font-medium">Materiales que vende</h3>
        <p class="mt-0.5 text-[12px] text-[var(--color-tenue)]">El precio por m³ se le suma al flete en el cobro al consorcio. Sin precio no se pueden registrar viajes de ese material.</p>

        <ul v-if="activos.length" class="mt-3 flex flex-wrap gap-1.5">
            <li
                v-for="v in activos"
                :key="v.id"
                class="flex items-center gap-1.5 rounded-md border border-[var(--color-borde)] bg-white py-1 pl-2.5 pr-1 text-[13px]"
            >
                <button type="button" class="flex items-center gap-1.5" :title="`Cambiar el precio de ${v.material?.nombre}`" @click="editar(v)">
                    {{ v.material?.nombre }}
                <span v-if="v.precio_compra_m3" class="cifra text-[12px] text-[var(--color-tenue)]">{{ pesos(v.precio_compra_m3, true) }}/m³</span>
                <span v-else class="text-[12px] text-[var(--color-pendiente)]">sin precio</span>
                </button>
                <button
                    type="button"
                    class="rounded p-0.5 text-[var(--color-tenue)] hover:text-[var(--color-critico)]"
                    :aria-label="`Quitar ${v.material?.nombre}`"
                    @click="quitar(v)"
                >
                    <Icono nombre="x" :tamano="14" />
                </button>
            </li>
        </ul>
        <p v-else class="mt-3 text-[13px] text-[var(--color-apagado)]">Todavía no tiene materiales.</p>

        <form class="mt-3 flex flex-wrap gap-2" @submit.prevent="agregar">
            <select v-model="nuevo.material_id" class="campo min-h-9 flex-1 py-1.5 text-[13px]" aria-label="Material">
                <option value="">Material…</option>
                <option v-for="m in materiales" :key="m.id" :value="m.id">{{ m.nombre }}{{ vinculados.has(m.id) ? ' (cambiar precio)' : '' }}</option>
            </select>
            <input
                v-model="nuevo.precio_compra_m3"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                class="campo cifra min-h-9 w-28 py-1.5 text-[13px]"
                placeholder="$ por m³"
                aria-label="Precio del material por m³"
            >
            <button type="submit" class="boton-secundario min-h-9 px-3" :disabled="!nuevo.material_id || nuevo.guardando">
                <template v-if="actualizando">Guardar precio</template>
                <template v-else><Icono nombre="mas" :tamano="14" /> Agregar</template>
            </button>
        </form>
    </section>
</template>
