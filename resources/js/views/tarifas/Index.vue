<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api, { mensajeDeError } from '../../lib/api'
import { hoy, pesos } from '../../lib/formato'
import { useAuth } from '../../stores/auth'
import { useAvisos } from '../../stores/avisos'
import Encabezado from '../../components/Encabezado.vue'
import Cargando from '../../components/Cargando.vue'
import Icono from '../../components/Icono.vue'

const auth = useAuth()
const avisos = useAvisos()

const tarifas = ref([])
const obras = ref([])
const cargando = ref(true)
const soloVigentes = ref(true)
const obraId = ref('')

const cambio = reactive({ abierto: false, tarifa: null, valor: '', desde: '', motivo: '' })

const TIPOS = {
    cobro: { texto: 'Cobro', clase: 'bg-[var(--color-info-fondo)] text-[var(--color-info)]' },
    pago_propietario: { texto: 'Pago al dueño', clase: 'bg-[var(--color-neutro-fondo)] text-[var(--color-apagado)]' },
    pago_conductor: { texto: 'Pago conductor', clase: 'bg-[var(--color-neutro-fondo)] text-[var(--color-apagado)]' },
}

async function cargar() {
    cargando.value = true

    try {
        const { data } = await api.get('tarifas', {
            params: { solo_vigentes: soloVigentes.value ? 1 : 0, obra_id: obraId.value || undefined, por_pagina: 100 },
        })
        tarifas.value = data.data
    } finally {
        cargando.value = false
    }
}

function abrirCambio(tarifa) {
    cambio.abierto = true
    cambio.tarifa = tarifa
    cambio.valor = tarifa.valor
    cambio.desde = new Date().toISOString().slice(0, 10)
    cambio.motivo = ''
}

/**
 * Cambiar un precio no edita la fila: cierra la vigente y crea una nueva.
 * Por eso el formulario pide desde cuándo aplica y por qué.
 */
async function guardarCambio() {
    try {
        await api.post(`tarifas/${cambio.tarifa.id}/cambiar`, {
            valor: Number(cambio.valor),
            desde: cambio.desde,
            motivo: cambio.motivo || null,
        })

        avisos.ok('Tarifa actualizada. La anterior quedó cerrada, no borrada.')
        cambio.abierto = false
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo cambiar la tarifa.'))
    }
}

/**
 * Nueva tarifa: se pacta por ruta (obra + cantera, u obra + botadero). El
 * material solo va cuando ese material tiene un precio distinto en la ruta.
 */
const nueva = reactive({ abierta: false, guardando: false, datos: {} })
const listas = reactive({ canteras: [], botaderos: [], terceros: [], materialesCantera: [] })

const TIPOS_VIAJE = [{ valor: 'suministro', texto: 'Suministro' }, { valor: 'retiro', texto: 'Retiro' }]

const esRetiro = computed(() => nueva.datos.tipo_viaje === 'retiro')
const esConductor = computed(() => nueva.datos.tipo === 'pago_conductor')
const esCobro = computed(() => nueva.datos.tipo === 'cobro')

function abrirNueva() {
    nueva.datos = {
        tipo: auth.esAdministrador ? 'cobro' : 'pago_propietario',
        tipo_viaje: 'suministro',
        obra_id: obraId.value || '',
        cantera_id: '',
        botadero_id: '',
        material_id: '',
        tercero_id: '',
        unidad: 'm3',
        valor: '',
        valor_base: '',
        porcentaje: '',
        fecha_inicio: hoy(),
    }
    nueva.abierta = true

    if (!listas.canteras.length) {
        Promise.all([
            api.get('canteras', { params: { por_pagina: 200, solo_activos: 1 } }),
            api.get('botaderos', { params: { por_pagina: 200, solo_activos: 1 } }),
            api.get('terceros', { params: { por_pagina: 200 } }),
        ]).then(([c, b, t]) => {
            listas.canteras = c.data.data
            listas.botaderos = b.data.data
            listas.terceros = t.data.data
        })
    }
}

// La unidad sigue al tipo: el cobro va por m³, el pago al dueño por viaje y
// el del conductor siempre es un porcentaje.
watch(() => nueva.datos.tipo, (tipo) => {
    if (!tipo) return
    nueva.datos.unidad = { cobro: 'm3', pago_propietario: 'viaje', pago_conductor: 'porcentaje' }[tipo]
})

// Solo se ofrecen los materiales que esa cantera vende.
watch(() => nueva.datos.cantera_id, async (id) => {
    nueva.datos.material_id = ''
    listas.materialesCantera = []
    if (!id) return

    const { data } = await api.get('cantera-material', { params: { cantera_id: id, solo_activos: 1, por_pagina: 100 } })
    listas.materialesCantera = data.data
})

async function guardarNueva() {
    const d = nueva.datos
    nueva.guardando = true

    try {
        await api.post('tarifas', {
            tipo: d.tipo,
            tipo_viaje: d.tipo_viaje,
            obra_id: d.obra_id || null,
            cantera_id: esRetiro.value ? null : d.cantera_id || null,
            botadero_id: esRetiro.value ? d.botadero_id || null : null,
            material_id: esRetiro.value ? null : d.material_id || null,
            tercero_id: esCobro.value ? null : d.tercero_id || null,
            unidad: d.unidad,
            valor: esConductor.value ? null : (d.valor === '' ? null : Number(d.valor)),
            valor_base: esConductor.value ? Number(d.valor_base) || null : null,
            porcentaje: esConductor.value ? Number(d.porcentaje) || null : null,
            fecha_inicio: d.fecha_inicio,
        })

        avisos.ok('Tarifa creada. Ya se puede registrar viajes en esa ruta.')
        nueva.abierta = false
        await cargar()
    } catch (e) {
        avisos.error(mensajeDeError(e, 'No se pudo crear la tarifa.'))
    } finally {
        nueva.guardando = false
    }
}

watch([soloVigentes, obraId], cargar)

onMounted(async () => {
    const { data } = await api.get('obras', { params: { por_pagina: 100 } })
    obras.value = data.data
    await cargar()
})
</script>

<template>
    <Encabezado titulo="Tarifas" subtitulo="Precios pactados por ruta">
        <template #acciones>
            <label class="flex items-center gap-2 text-[13px] text-[var(--color-apagado)]">
                <input v-model="soloVigentes" type="checkbox" class="size-3.5">
                Solo vigentes
            </label>
            <select v-model="obraId" class="campo min-h-9 w-full py-1.5 text-[13px] sm:w-56">
                <option value="">Todas las obras</option>
                <option v-for="obra in obras" :key="obra.id" :value="obra.id">{{ obra.nombre }}</option>
            </select>
            <button class="boton-primario" @click="abrirNueva">
                <Icono nombre="mas" :tamano="16" /> Nueva tarifa
            </button>
        </template>
    </Encabezado>

    <Cargando :activo="cargando" :vacio="!tarifas.length" mensaje-vacio="No hay tarifas registradas.">
        <div class="px-4 py-6 sm:px-8">
            <div class="tarjeta overflow-hidden">
                <div class="tabla-scroll">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="bg-[var(--color-superficie-sutil)] text-left text-[11px] font-medium text-[var(--color-apagado)]">
                            <th class="py-2.5 pl-5 pr-2.5">RUTA</th>
                            <th class="w-32 px-2.5 py-2.5">MATERIAL</th>
                            <th class="w-40 px-2.5 py-2.5">TIPO</th>
                            <th class="w-44 px-2.5 py-2.5">CON QUIÉN</th>
                            <th class="w-40 px-2.5 py-2.5 text-right">VALOR</th>
                            <th class="w-48 px-2.5 py-2.5">VIGENCIA</th>
                            <th class="w-20 py-2.5 pl-2.5 pr-5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tarifa in tarifas"
                            :key="tarifa.id"
                            class="border-b border-[var(--color-borde)]"
                            :class="tarifa.vigente ? '' : 'text-[var(--color-tenue)]'"
                        >
                            <td class="py-2.5 pl-5 pr-2.5">{{ tarifa.ruta }}</td>
                            <td class="px-2.5 py-2.5">
                                <span v-if="tarifa.material" class="font-medium">{{ tarifa.material }}</span>
                                <span v-else class="italic text-[var(--color-tenue)]">cualquiera</span>
                            </td>
                            <td class="px-2.5 py-2.5">
                                <span class="rounded px-2 py-0.5 text-[11px] font-medium" :class="TIPOS[tarifa.tipo]?.clase">
                                    {{ TIPOS[tarifa.tipo]?.texto ?? tarifa.tipo }}
                                </span>
                            </td>
                            <td class="px-2.5 py-2.5">{{ tarifa.tercero ?? (tarifa.tipo === 'cobro' ? 'Consorcio de la obra' : 'Todos los dueños') }}</td>
                            <td class="cifra px-2.5 py-2.5 text-right font-medium">
                                <template v-if="tarifa.unidad === 'porcentaje'">
                                    {{ tarifa.porcentaje }}%
                                    <span class="font-normal text-[var(--color-tenue)]">de {{ pesos(tarifa.valor_base) }}</span>
                                </template>
                                <template v-else>
                                    {{ pesos(tarifa.valor) }}
                                    <span class="font-normal text-[var(--color-tenue)]">/ {{ tarifa.unidad === 'm3' ? 'm³' : 'viaje' }}</span>
                                </template>
                            </td>
                            <td class="cifra px-2.5 py-2.5">
                                {{ tarifa.fecha_inicio }} —
                                <span v-if="tarifa.vigente" class="text-[var(--color-confirmado)]">vigente</span>
                                <span v-else>{{ tarifa.fecha_fin }}</span>
                            </td>
                            <td class="py-2.5 pl-2.5 pr-5 text-right">
                                <button
                                    v-if="tarifa.vigente && auth.esAdministrador && tarifa.unidad !== 'porcentaje'"
                                    class="text-[12.5px] font-medium text-[var(--color-enlace)]"
                                    @click="abrirCambio(tarifa)"
                                >
                                    Cambiar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <p class="flex items-center gap-2.5 border-t border-[var(--color-borde)] bg-[var(--color-superficie-sutil)] px-5 py-3 text-[12.5px] text-[var(--color-apagado)]">
                    Subir un precio no reescribe el anterior: se cierra la tarifa vigente y se crea una nueva.
                    Los viajes ya registrados conservan el valor que tenían.
                </p>
            </div>
        </div>
    </Cargando>

    <!-- Cambio de precio -->
    <div v-if="cambio.abierto" class="fixed inset-0 z-40 flex items-center justify-center velo p-6" @click.self="cambio.abierto = false">
        <div class="w-full max-w-[440px] flotante dialogo">
            <div class="border-b border-[var(--color-borde)] px-5 py-4">
                <h2 class="text-[18px] font-normal">Cambiar tarifa</h2>
                <p class="mt-0.5 text-xs text-[var(--color-apagado)]">{{ cambio.tarifa?.ruta }}</p>
            </div>

            <div class="space-y-4 p-5">
                <div>
                    <label class="etiqueta">Nuevo valor</label>
                    <input v-model="cambio.valor" type="number" class="campo cifra">
                    <p class="mt-1 text-[11.5px] text-[var(--color-tenue)]">
                        Antes: {{ pesos(cambio.tarifa?.valor) }} / {{ cambio.tarifa?.unidad === 'm3' ? 'm³' : 'viaje' }}
                    </p>
                </div>

                <div>
                    <label class="etiqueta">Aplica desde</label>
                    <input v-model="cambio.desde" type="date" class="campo">
                </div>

                <div>
                    <label class="etiqueta">Motivo</label>
                    <input v-model="cambio.motivo" class="campo" placeholder="alza del combustible, renegociación…">
                </div>

                <p class="rounded-md bg-[var(--color-superficie-sutil)] px-3 py-2.5 text-[12px] text-[var(--color-apagado)]">
                    La tarifa actual quedará cerrada el día anterior. Los viajes ya registrados no cambian.
                </p>
            </div>

            <div class="flex gap-2 border-t border-[var(--color-borde)] px-5 py-4">
                <button class="boton-secundario flex-1 justify-center" @click="cambio.abierto = false">Cancelar</button>
                <button class="boton-primario flex-1 justify-center" @click="guardarCambio">Guardar cambio</button>
            </div>
        </div>
    </div>
    <!-- Nueva tarifa (la de cobro solo el administrador; el backend también lo exige) -->
    <div v-if="nueva.abierta" class="velo fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-6" @click.self="nueva.abierta = false">
        <form class="flotante dialogo flex max-h-[92dvh] w-full max-w-[520px] flex-col" @submit.prevent="guardarNueva">
            <div class="flex items-start border-b border-[var(--color-borde)] px-5 py-4">
                <div>
                    <h2 class="text-[18px] font-normal">Nueva tarifa</h2>
                    <p class="mt-0.5 text-[12.5px] text-[var(--color-apagado)]">Se pacta por ruta. Una tarifa de cobro por ruta basta.</p>
                </div>
                <button type="button" class="-mr-1 ml-auto rounded-full p-1 text-[var(--color-tenue)] hover:text-[var(--color-tinta)]" aria-label="Cerrar" @click="nueva.abierta = false">
                    <Icono nombre="x" :tamano="16" />
                </button>
            </div>

            <div class="space-y-4 overflow-y-auto p-5" style="overscroll-behavior: contain">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="etiqueta">Tipo de viaje</label>
                        <div class="flex overflow-hidden rounded-md border border-[var(--color-borde-fuerte)]">
                            <button
                                v-for="t in TIPOS_VIAJE"
                                :key="t.valor"
                                type="button"
                                class="min-h-10 flex-1 text-[13px] transition-colors"
                                :class="nueva.datos.tipo_viaje === t.valor ? 'bg-[var(--color-marca)] font-medium text-white' : 'text-[var(--color-apagado)]'"
                                @click="nueva.datos.tipo_viaje = t.valor"
                            >
                                {{ t.texto }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="etiqueta">Tipo de tarifa</label>
                        <select v-model="nueva.datos.tipo" class="campo">
                            <option v-if="auth.esAdministrador" value="cobro">Cobro al consorcio</option>
                            <option value="pago_propietario">Pago al dueño</option>
                            <option value="pago_conductor">Pago al conductor (volqueta propia)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="etiqueta">Obra <span class="text-[var(--color-critico)]">*</span></label>
                    <select v-model="nueva.datos.obra_id" class="campo" required>
                        <option value="">Seleccionar…</option>
                        <option v-for="obra in obras" :key="obra.id" :value="obra.id">{{ obra.nombre }}</option>
                    </select>
                </div>

                <div v-if="!esRetiro" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="etiqueta">Cantera <span class="text-[var(--color-critico)]">*</span></label>
                        <select v-model="nueva.datos.cantera_id" class="campo" required>
                            <option value="">Seleccionar…</option>
                            <option v-for="c in listas.canteras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="etiqueta">Material</label>
                        <select v-model="nueva.datos.material_id" class="campo" :disabled="!nueva.datos.cantera_id">
                            <option value="">Cualquiera (lo normal)</option>
                            <option v-for="cm in listas.materialesCantera" :key="cm.id" :value="cm.material_id">{{ cm.material?.nombre }}</option>
                        </select>
                        <p class="mt-1 text-[11.5px] text-[var(--color-tenue)]">Solo si ese material tiene un precio distinto en esta ruta.</p>
                    </div>
                </div>

                <div v-else>
                    <label class="etiqueta">Botadero <span class="text-[var(--color-critico)]">*</span></label>
                    <select v-model="nueva.datos.botadero_id" class="campo" required>
                        <option value="">Seleccionar…</option>
                        <option v-for="b in listas.botaderos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                    </select>
                </div>

                <div v-if="!esCobro">
                    <label class="etiqueta">{{ esConductor ? 'Conductor' : 'Dueño de la volqueta' }} <span v-if="esConductor" class="text-[var(--color-critico)]">*</span></label>
                    <select v-model="nueva.datos.tercero_id" class="campo" :required="esConductor">
                        <option value="">{{ esConductor ? 'Seleccionar…' : 'Todos (tarifa general de la ruta)' }}</option>
                        <option v-for="t in listas.terceros" :key="t.id" :value="t.id">
                            {{ t.nombre_completo ?? [t.nombre, t.apellido].filter(Boolean).join(' ') }}
                        </option>
                    </select>
                </div>

                <div v-if="esConductor" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="etiqueta">Valor base <span class="text-[var(--color-critico)]">*</span></label>
                        <input v-model="nueva.datos.valor_base" type="number" min="0" step="1" inputmode="numeric" class="campo cifra" required>
                    </div>
                    <div>
                        <label class="etiqueta">Porcentaje <span class="text-[var(--color-critico)]">*</span></label>
                        <input v-model="nueva.datos.porcentaje" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="campo cifra" required>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_140px]">
                    <div>
                        <label class="etiqueta">Valor en pesos <span class="text-[var(--color-critico)]">*</span></label>
                        <input v-model="nueva.datos.valor" type="number" min="0" step="1" inputmode="numeric" class="campo cifra" required>
                    </div>
                    <div>
                        <label class="etiqueta">Por</label>
                        <select v-model="nueva.datos.unidad" class="campo">
                            <option value="m3">m³</option>
                            <option value="viaje">viaje</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="etiqueta">Aplica desde</label>
                    <input v-model="nueva.datos.fecha_inicio" type="date" class="campo" required>
                </div>
            </div>

            <div class="flex gap-2 border-t border-[var(--color-borde)] px-5 py-4" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
                <button type="button" class="boton-secundario flex-1" @click="nueva.abierta = false">Cancelar</button>
                <button type="submit" class="boton-primario flex-1" :disabled="nueva.guardando">
                    {{ nueva.guardando ? 'Guardando…' : 'Crear tarifa' }}
                </button>
            </div>
        </form>
    </div>
</template>
