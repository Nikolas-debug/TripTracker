import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
})

let alExpirar = null

/** El router registra aquí qué hacer cuando el backend dice 401. */
export function alPerderSesion(callback) {
    alExpirar = callback
}

export function ponerToken(token) {
    if (token) {
        api.defaults.headers.common.Authorization = `Bearer ${token}`
    } else {
        delete api.defaults.headers.common.Authorization
    }
}

api.interceptors.response.use(
    (respuesta) => respuesta,
    (error) => {
        if (error.response?.status === 401 && alExpirar) {
            alExpirar()
        }
        return Promise.reject(error)
    },
)

/**
 * Traduce un error de axios a algo que se le pueda mostrar al digitador.
 * Los errores de validación de Laravel vienen en `errors`; los de dominio
 * (tarifa faltante, viaje bloqueado) traen `message` y ya están redactados.
 */
export function mensajeDeError(error, porDefecto = 'Algo salió mal.') {
    const datos = error.response?.data

    if (datos?.errors) {
        return Object.values(datos.errors).flat().join(' ')
    }

    return datos?.message ?? porDefecto
}

export default api
