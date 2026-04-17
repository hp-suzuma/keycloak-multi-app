// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui'
  ],

  devtools: {
    enabled: true
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      apApiBase: 'https://ap-backend-fpm.example.com/api',
      apApiBearerToken: '',
      apUserManagementMode: 'mock',
      apGlobalLoginUrl: 'https://global.example.com/login',
      apFrontendBaseUrl: 'https://ap.example.com',
      apKeycloakIssuer: 'https://keycloak.example.com/realms/myapp',
      apKeycloakClientId: 'ap-frontend'
    }
  },

  routeRules: {
    '/': { prerender: true }
  },

  compatibilityDate: '2025-01-15',

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  }
})
