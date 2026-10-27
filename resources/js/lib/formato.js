/**
 * Los valores viajan como enteros de pesos, sin decimales. Aquí solo se les
 * pone el separador de miles: nunca se hacen cuentas en el front, porque la
 * plata la calcula el backend con los valores congelados en cada viaje.
 */
export function pesos(valor, conSigno = false) {
    if (valor === null || valor === undefined) return '—'
    const texto = new Intl.NumberFormat('es-CO').format(valor)
    return conSigno ? `$ ${texto}` : texto
}

export function numero(valor) {
    if (valor === null || valor === undefined) return '—'
    return new Intl.NumberFormat('es-CO').format(valor)
}

export function fecha(iso, formato = 'corta') {
    if (!iso) return '—'
    const d = new Date(`${iso}`.slice(0, 10) + 'T12:00:00')

    if (formato === 'corta') {
        return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit' })
    }

    return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' })
}

export function hoy() {
    const ahora = new Date()
    const mes = String(ahora.getMonth() + 1).padStart(2, '0')
    const dia = String(ahora.getDate()).padStart(2, '0')
    return `${ahora.getFullYear()}-${mes}-${dia}`
}

export const ESTADOS = {
    por_confirmar: { texto: 'Por confirmar', clase: 'bg-[var(--color-pendiente-fondo)] text-[var(--color-pendiente)]' },
    confirmado: { texto: 'Confirmado', clase: 'bg-[var(--color-confirmado-fondo)] text-[var(--color-confirmado)]' },
    anulado: { texto: 'Anulado', clase: 'bg-[var(--color-anulado-fondo)] text-[var(--color-anulado)]' },
    abierta: { texto: 'Abierta', clase: 'bg-[var(--color-pendiente-fondo)] text-[var(--color-pendiente)]' },
    cerrada: { texto: 'Cerrada', clase: 'bg-[var(--color-info-fondo)] text-[var(--color-info)]' },
    pagada: { texto: 'Pagada', clase: 'bg-[var(--color-confirmado-fondo)] text-[var(--color-confirmado)]' },
}
