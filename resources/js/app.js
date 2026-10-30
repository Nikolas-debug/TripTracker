import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuth } from './stores/auth'

const app = createApp(App)
app.use(createPinia())

// La sesión se restaura ANTES de montar el router: si no, la primera ruta
// protegida rebota al login aunque el token siga siendo válido.
const auth = useAuth()
await auth.restaurar()

app.use(router)
app.mount('#app')

// App instalable (solo en build: en desarrollo los archivos los sirve Vite).
// El nombre de este archivo lleva el hash del build, y con él la versión del
// service worker: cada despliegue lo actualiza solo, sin avisos.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    const version = new URL(import.meta.url).pathname.split('/').pop()
    navigator.serviceWorker.register(`/sw.js?v=${version}`)
}
