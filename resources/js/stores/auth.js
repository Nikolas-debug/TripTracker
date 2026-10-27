import { defineStore } from 'pinia'
import api, { ponerToken } from '../lib/api'

const CLAVE = 'gt_token'

export const useAuth = defineStore('auth', {
    state: () => ({
        usuario: null,
        token: localStorage.getItem(CLAVE) || null,
        cargando: false,
    }),

    getters: {
        autenticado: (estado) => estado.usuario !== null,
        esAdministrador: (estado) => estado.usuario?.rol === 'administrador',
    },

    actions: {
        /** Al abrir la app: si hay token guardado, se verifica contra el backend. */
        async restaurar() {
            if (!this.token) return

            ponerToken(this.token)

            try {
                const { data } = await api.get('me')
                this.usuario = data
            } catch {
                this.limpiar()
            }
        },

        async entrar(email, password) {
            this.cargando = true

            try {
                const { data } = await api.post('login', { email, password, dispositivo: 'web' })
                this.token = data.token
                this.usuario = data.usuario
                localStorage.setItem(CLAVE, data.token)
                ponerToken(data.token)
            } finally {
                this.cargando = false
            }
        },

        async salir() {
            try {
                await api.post('logout')
            } catch {
                // Si el token ya no sirve, da igual: se limpia local.
            }

            this.limpiar()
        },

        limpiar() {
            this.usuario = null
            this.token = null
            localStorage.removeItem(CLAVE)
            ponerToken(null)
        },
    },
})
