export default defineNuxtConfig({
  devtools: { enabled: false },
  app: {
    head: {
      title: 'Global Login',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' }
      ]
    }
  },
  css: ['~/assets/app.css']
})
