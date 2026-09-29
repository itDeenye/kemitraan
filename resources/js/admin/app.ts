import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createAppVuetify } from '@/shared/plugins/vuetify'
import router from './router'
import App from './App.vue'

const app = createApp(App)

app.use(createPinia())
app.use(createAppVuetify())
app.use(router)

app.mount('#app')
