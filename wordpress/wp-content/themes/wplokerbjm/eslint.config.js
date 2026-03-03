import path from 'node:path';
import { includeIgnoreFile } from '@eslint/compat';
import js from '@eslint/js';
import svelte from 'eslint-plugin-svelte';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import ts from 'typescript-eslint';
import svelteConfig from './svelte.config.js';

const gitignorePath = path.resolve(import.meta.dirname, '.gitignore');
const __dirnamePath = path.resolve(import.meta.dirname);

export default defineConfig(
	includeIgnoreFile(gitignorePath),
	js.configs.recommended,
	...ts.configs.recommended,
	...svelte.configs.recommended,

	// project ignore patterns (match root repo)
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
			".svelte-kit/**",
			"static/**"
		]
	},

	// shared rules + globals
	{
		languageOptions: { globals: { ...globals.browser, ...globals.node } },
		rules: {
			// typescript-eslint strongly recommend that you do not use the no-undef lint rule on TypeScript projects.
			// see: https://typescript-eslint.io/troubleshooting/faqs/eslint/#i-get-errors-from-the-no-undef-rule-about-global-variables-not-being-defined-even-though-there-are-no-typescript-errors
			"no-undef": 'off'
		}
	},

	// TypeScript / JavaScript specific settings (from root project)
	{
		files: ["**/*.{ts,js}"],
		languageOptions: {
			parser: ts.parser,
			parserOptions: {
				tsconfigRootDir: __dirnamePath,
				sourceType: 'module'
			}
		},
		rules: {
			"@typescript-eslint/no-explicit-any": 'off', // allow any when necessary, but prefer explicit types
			"@typescript-eslint/no-floating-promises": "error",
			"@typescript-eslint/require-await": "warn",
			"@typescript-eslint/no-unused-vars": "error",
			"prefer-const": "error",
			"no-var": "error",
			"eqeqeq": "error"
		}
	},

	// Svelte file override — keep projectService and add root rules
	{
		files: ['**/*.svelte', '**/*.svelte.ts', '**/*.svelte.js'],
		languageOptions: {
			parserOptions: {
				projectService: true,
				extraFileExtensions: ['.svelte'],
				parser: ts.parser,
				svelteConfig
			}
		},
		rules: {
			"@typescript-eslint/no-unused-vars": "error",
			"svelte/valid-compile": "error",
			"svelte/infinite-reactive-loop": "error",
			"svelte/no-target-blank": "error",
			"svelte/no-svelte-internal": "error",
			"svelte/no-reactive-literals": "error",
			"prefer-const": "error",
			"no-var": "error",
			"eqeqeq": "error"
		}
	}
);
