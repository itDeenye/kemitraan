import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createAppVuetify } from '@/shared/plugins/vuetify'
import router from './router'
import App from './App.vue'
import '../../../resources/css/member.css'

const app = createApp(App)

app.use(createPinia())
app.use(createAppVuetify())
app.use(router)

app.mount('#app')

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', {
            scope: '/member/',
            updateViaCache: 'none',
        })
            .then((registration) => registration.update())
            .catch((error: unknown) => console.error('Pendaftaran service worker gagal.', error))
    })
}
