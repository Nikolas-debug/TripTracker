/**
 * Cargo como app instalable. Guarda la carcasa (HTML, JS, CSS, iconos) para
 * que el menú y los formularios abran al instante y sin red. Los datos (/api)
 * nunca se guardan: siempre van a la red.
 *
 * app.js registra este archivo como /sw.js?v=<hash del build>: cada despliegue
 * cambia la URL, el navegador instala la versión nueva y borra la anterior.
 */
const CACHE = `cargo-${new URL(location).searchParams.get('v')}`

const FIJOS = ['/', '/manifest.webmanifest', '/favicon.svg', '/icons/icon-192.png', '/icons/icon-512.png']

self.addEventListener('install', (evento) => {
    evento.waitUntil((async () => {
        // Todos los chunks del build, no solo los visitados: así cada
        // formulario abre sin red aunque nunca se haya entrado a él.
        const manifiesto = await (await fetch('/build/manifest.json', { cache: 'no-store' })).json()
        const archivos = new Set(FIJOS)

        for (const entrada of Object.values(manifiesto)) {
            for (const archivo of [entrada.file, ...(entrada.css ?? []), ...(entrada.assets ?? [])]) {
                archivos.add(`/build/${archivo}`)
            }
        }

        const cache = await caches.open(CACHE)
        await cache.addAll([...archivos])
        await self.skipWaiting()
    })())
})

self.addEventListener('activate', (evento) => {
    evento.waitUntil((async () => {
        for (const nombre of await caches.keys()) {
            if (nombre !== CACHE) await caches.delete(nombre)
        }
        await self.clients.claim()
    })())
})

self.addEventListener('fetch', (evento) => {
    const { request } = evento
    const url = new URL(request.url)

    if (request.method !== 'GET' || url.pathname.startsWith('/api')) return

    // Páginas: primero la red (para recibir siempre la versión desplegada);
    // sin red, la carcasa guardada y Vue Router resuelve la ruta.
    if (request.mode === 'navigate') {
        evento.respondWith(fetch(request).catch(() => caches.match('/')))
        return
    }

    // Archivos con hash del build, iconos y la fuente Inter: no cambian, así
    // que se sirven del cache y solo se piden a la red la primera vez.
    const estatico = url.origin === location.origin
        ? url.pathname.startsWith('/build/') || FIJOS.includes(url.pathname)
        : url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com'

    if (!estatico) return

    evento.respondWith((async () => {
        const cache = await caches.open(CACHE)
        const guardado = await cache.match(request)
        if (guardado) return guardado

        const respuesta = await fetch(request)
        if (respuesta.ok) cache.put(request, respuesta.clone())
        return respuesta
    })())
})
