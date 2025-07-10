module.exports = {
  root: true,
  env: {
    browser: true,
    es2024: true,
    node: true,
  },
  extends: [
    "eslint:recommended",
    "plugin:vue/vue3-recommended",
    "plugin:@typescript-eslint/recommended",
  ],
  parser: "vue-eslint-parser",
  parserOptions: {
    parser: "@typescript-eslint/parser",
    ecmaVersion: "latest",
    sourceType: "module",
  },
  plugins: ["vue", "@typescript-eslint"],
  rules: {
    "no-unused-vars": "on",
    "@typescript-eslint/no-unused-vars": ["warn"],
    "vue/multi-word-component-names": "on",
  },
  ignore: [
    "node_modules",
    "dist",
    "build",
    "coverage",
    ".nuxt",
    ".nuxt-devtools",
    ".nuxt/dist",
    ".nuxt/dist/client",
    ".nuxt/dist/server",
    "public",
    "out",
  ],
};
