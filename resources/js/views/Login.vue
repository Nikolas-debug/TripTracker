<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'
import { mensajeDeError } from '../lib/api'
import Icono from '../components/Icono.vue'

const auth = useAuth()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref('')

const pasos = ['Se registra el viaje', 'Llega el vale firmado', 'Entra en una cuenta', 'Se liquida']

async function entrar() {
    error.value = ''

    try {
        await auth.entrar(email.value, password.value)
        router.push(route.query.volverA || { name: 'inicio' })
    } catch (e) {
        error.value = mensajeDeError(e, 'No se pudo iniciar sesión.')
    }
}
</script>

<template>
    <div class="flex min-h-dvh bg-white p-3 lg:p-4">
        <!-- Superficie de firma: el bosque, la tierra de donde sale el material -->
        <div class="firma-bosque relative hidden w-[min(44vw,620px)] shrink-0 flex-col overflow-hidden p-12 lg:flex">
            <div class="flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-xl bg-white text-[var(--color-bosque)]">
                    <Icono nombre="viajes" :tamano="22" />
                </div>
                <div class="leading-tight">
                    <div class="text-[20px]">Cargo</div>
                    <div class="text-[13px] text-white/65">Logística de viajes y materiales</div>
                </div>
            </div>

            <!-- La ruta de un viaje, de la cantera a la cuenta -->
            <ol class="mt-auto space-y-3 text-[14px]">
                <li v-for="(paso, i) in pasos" :key="paso" class="flex items-center gap-3">
                    <span class="flex size-7 items-center justify-center rounded-full text-[12px]" :class="i === pasos.length - 1 ? 'bg-[var(--color-menta)] text-[var(--color-bosque)]' : 'border border-white/30 text-white/80'">{{ i + 1 }}</span>
                    <span class="text-white/85">{{ paso }}</span>
                </li>
            </ol>

            <p class="mt-10 max-w-md text-[32px] leading-[1.2] tracking-[-0.01em]" style="text-wrap: pretty">
                Cada viaje registrado, cada vale confirmado y cada cuenta liquidada en un solo lugar.
            </p>
        </div>

        <!-- Formulario -->
        <div class="flex flex-1 items-center justify-center px-3 py-10">
            <form class="w-full max-w-[380px]" @submit.prevent="entrar">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-[var(--color-tinta)] text-white">
                        <Icono nombre="viajes" :tamano="20" />
                    </div>
                    <span class="text-[18px]">Cargo</span>
                </div>

                <h1 class="text-[32px] font-normal leading-tight tracking-[-0.01em]">Iniciar sesión</h1>
                <p class="mt-2 text-[14px] text-[var(--color-apagado)]">Entra con tu cuenta de Grup Tous.</p>

                <div class="mt-8 space-y-5">
                    <div>
                        <label class="etiqueta" for="email">Correo</label>
                        <input id="email" v-model="email" type="email" class="campo h-11" autocomplete="username" autocapitalize="none" autocorrect="off" required autofocus>
                    </div>

                    <div>
                        <label class="etiqueta" for="password">Contraseña</label>
                        <input id="password" v-model="password" type="password" class="campo h-11" autocomplete="current-password" required>
                    </div>
                </div>

                <p v-if="error" class="mt-5 flex items-start gap-2 rounded-lg bg-[var(--color-critico-fondo)] px-3 py-2.5 text-[14px] text-[var(--color-critico)]" role="alert">
                    <Icono nombre="alerta" :tamano="16" class="mt-0.5" />
                    {{ error }}
                </p>

                <button type="submit" class="boton-primario mt-7 h-12 w-full text-[16px]" :disabled="auth.cargando">
                    {{ auth.cargando ? 'Entrando…' : 'Entrar' }}
                </button>

                <p class="mt-6 text-center text-[13px] text-[var(--color-tenue)]">
                    ¿Problemas para entrar? Habla con el administrador.
                </p>

                <p class="mt-10 text-center text-[12px] text-[var(--color-tenue)]">
                    Cargo para Grup Tous · Desarrollado por <span class="font-medium text-[var(--color-apagado)]">Stonemills</span>
                </p>
            </form>
        </div>
    </div>
</template>

