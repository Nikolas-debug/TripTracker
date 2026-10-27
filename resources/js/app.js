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
