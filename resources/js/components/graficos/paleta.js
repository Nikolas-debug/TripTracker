/**
 * La paleta de los gráficos.
 *
 * Azul de enlace, coral quemado y verde azulado: derivados del DESIGN.md
 * (link, signature-coral, la familia mint/forest) llevados a la banda de
 * luminosidad de un gráfico. La primera serie, la que se lee primero, es el
 * azul: el único acento cromático del resto de la interfaz.
 *
 * Validados con scripts/validate_palette.js de la guía de visualización (modo
 * claro): pasan banda de luminosidad, piso de croma, separación para daltonismo
 * (ΔE 11.0) y piso de visión normal (ΔE 26.4), los tres a más de 3:1 contra el
 * blanco. No se cambian a ojo: si hace falta otro color, se revalida el
 * conjunto.
 */
export const SERIES = ['#1b61c9', '#c2410c', '#0a8f72']

/** Rampa de un solo tono para magnitud (más grande, más oscuro). */
export const RAMPA_AZUL = ['#dbe7fb', '#b4cdf5', '#86aeec', '#5a8ee0', '#1b61c9', '#174fa3', '#1a3866']

export const TINTA = {
    primaria: '#181d26',    // ink — igual que --color-tinta
    secundaria: '#333840',  // body — igual que --color-cuerpo
    apagada: '#646973',     // igual que --color-tenue
    rejilla: '#e4e5e8',     // igual que --color-borde
    eje: '#d3d5da',         // igual que --color-borde-fuerte
    superficie: '#FFFFFF',
}

/** Verde y rojo de estado. Nunca se usan como color de serie. */
export const ESTADO = {
    bueno: '#006400',
    critico: '#b42318',
}

/**
 * Ticks en números redondos. Un eje que dice 0 / 5.000.000 / 10.000.000 se
 * lee; uno que dice 0 / 4.387.219 / 8.774.438 no.
 */
export function escalaBonita(maximo, cantidad = 4) {
    if (!maximo || maximo <= 0) return { tope: 1, ticks: [0, 1] }

    const bruto = maximo / cantidad
    const magnitud = 10 ** Math.floor(Math.log10(bruto))
    const normalizado = bruto / magnitud

    const paso = (normalizado <= 1 ? 1 : normalizado <= 2 ? 2 : normalizado <= 5 ? 5 : 10) * magnitud
    const tope = Math.ceil(maximo / paso) * paso

    const ticks = []
    for (let v = 0; v <= tope + paso / 2; v += paso) ticks.push(v)

    return { tope, ticks }
}

/** 1.250.000 -> "1,25 M". Para ejes y etiquetas, donde el detalle estorba. */
export function compacto(valor) {
    const abs = Math.abs(valor)

    if (abs >= 1_000_000) return `${(valor / 1_000_000).toLocaleString('es-CO', { maximumFractionDigits: 1 })} M`
    if (abs >= 1_000) return `${(valor / 1_000).toLocaleString('es-CO', { maximumFractionDigits: 0 })} K`

    return valor.toLocaleString('es-CO')
}
