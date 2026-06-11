// Flat-config equivalent of the legacy .eslintrc.js — required by ESLint v9+.
const js = require('@eslint/js')
const pluginVue = require('eslint-plugin-vue')
const globals = require('globals')

module.exports = [
    js.configs.recommended,
    // eslint-plugin-vue v10 dropped the `vue3-` prefix; `flat/recommended` is Vue 3.
    ...pluginVue.configs['flat/recommended'],
    {
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.es2021,
                OC: 'readonly',
                OCA: 'readonly',
                OCP: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            // Unused catch bindings are fine — we often catch to swallow/route an error.
            'no-unused-vars': ['error', { caughtErrors: 'none' }],
        },
    },
]
