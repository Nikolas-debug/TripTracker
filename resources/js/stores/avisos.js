import { defineStore } from 'pinia'

let secuencia = 0

/**
 * Los avisos que aparecen arriba a la derecha. Un digitador que guarda 40
 * viajes necesita confirmación visible de que quedaron guardados.
 */
export const useAvisos = defineStore('avisos', {
    state: () => ({ lista: [] }),

    actions: {
        mostrar(texto, tipo = 'ok') {
            const id = ++secuencia
            this.lista.push({ id, texto, tipo })
            setTimeout(() => this.quitar(id), tipo === 'error' ? 8000 : 4000)
        },

        ok(texto) {
            this.mostrar(texto, 'ok')
        },

        error(texto) {
            this.mostrar(texto, 'error')
        },

        quitar(id) {
            this.lista = this.lista.filter((a) => a.id !== id)
        },
    },
})
