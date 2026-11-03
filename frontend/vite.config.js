import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// En desarrollo, el gateway nginx corre en localhost:8080.
// Las rutas /qa, /tutor y /conciliacion se redirigen a el para replicar producción.
const gateway = 'http://localhost:8080'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    proxy: {
      '/qa': gateway,
      '/tutor': gateway,
      '/conciliacion': gateway,
    },
  },
})
