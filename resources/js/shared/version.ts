import { createApp } from 'vue'
import { createAppVuetify } from '@/shared/plugins/vuetify'
import VersionPage from '@/shared/pages/VersionPage.vue'

createApp(VersionPage)
    .use(createAppVuetify())
    .mount('#app')
