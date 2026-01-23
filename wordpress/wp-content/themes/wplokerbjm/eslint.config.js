import tsPlugin from "@typescript-eslint/eslint-plugin";
import tsParser from "@typescript-eslint/parser";
import svelteParser from "svelte-eslint-parser";
import sveltePlugin from "eslint-plugin-svelte";
import { fileURLToPath } from "url";

// Minimal flat config for ESLint v9 to detect un-awaited promises and unused async
const __dirname = fileURLToPath(new URL(".", import.meta.url));

export default [
  {
    ignores: [
      "assets/dist/**",
      ".vite/**",
      "public/**",
      "vendor/**",
      "node_modules/**",
      "stats.html",
      "svelte.config.js",
      "vite.config.ts",
      "eslint.config.js",
      "eslint.config.cjs",
      ".eslintrc.cjs",
      "vite-plugins/**/*.d.ts",
      "vite-plugins/**/*.js",
    ],
  },
  {
    files: ["**/*.{ts,js}"],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        project: ["./tsconfig.app.json", "./tsconfig.node.json"],
        tsconfigRootDir: __dirname,
        sourceType: "module",
      },
    },
    plugins: { "@typescript-eslint": tsPlugin },
    rules: {
      "@typescript-eslint/no-floating-promises": "error",
      "@typescript-eslint/require-await": "warn",
      "@typescript-eslint/no-unused-vars": "error",
      "prefer-const": "error",
      "no-var": "error",
      "eqeqeq": "error",
    },
  },
  {
    files: ["**/*.svelte", "**/*.svelte.ts"],
    languageOptions: {
      parser: svelteParser,
      parserOptions: {
        parser: tsParser,
        tsconfigRootDir: __dirname,
        extraFileExtensions: [".svelte"],
      },
    },
    // Register plugins used in this override so rules resolve here
    plugins: { "@typescript-eslint": tsPlugin, svelte: sveltePlugin },
    rules: {
      "@typescript-eslint/no-unused-vars": "error",
      "svelte/valid-compile": "error",
      "svelte/infinite-reactive-loop": "error",
      "svelte/no-target-blank": "error",
      "svelte/no-svelte-internal": "error",
      "svelte/no-reactive-literals": "error",
      "prefer-const": "error",
      "no-var": "error",
      "eqeqeq": "error",
    },
  },
];
