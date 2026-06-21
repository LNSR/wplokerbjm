import path from 'path';
import { fileURLToPath } from 'url';
import { includeIgnoreFile } from '@eslint/config-helpers';
import svelte from 'eslint-plugin-svelte';
import svelteParser from 'svelte-eslint-parser';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import svelteConfig from './svelte.config.ts';

const themeRootPath = path.dirname(fileURLToPath(import.meta.url));
const gitignorePath = path.resolve(themeRootPath, '../../../../.gitignore');

export default defineConfig(
	includeIgnoreFile(gitignorePath),
	...svelte.configs.recommended,
	...tseslint.configs.recommended,

	// project ignore patterns (match root repo)
	{
		ignores: [
			"assets/dist/**",
			".vite/**",
			"public/**",
			"**/*.d.ts",
			"vendor/**",
			"node_modules/**",
			"stats.html",
			".gitignore",
			"svelte.config.ts",
			"vite.config.ts",
			"eslint.config.ts",
			"eslint.config.cjs",
			".eslintrc.cjs",
			"vite-plugins/**/*.d.ts",
			"vite-plugins/**/*.js",
			".svelte-kit/**",
			"static/**",
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
		files: [ "**/*.{ts,js,svelte}" ],
		languageOptions: {
			parser: tseslint.parser,
			parserOptions: {
				projectService: true,
				tsconfigRootDir: themeRootPath,
				sourceType: 'module'
			}
		},
		rules: {
			"@typescript-eslint/no-explicit-any": 'off', // allow any when necessary, but prefer explicit types
			"@typescript-eslint/no-unsafe-function-type": 'off',
			"@typescript-eslint/no-floating-promises": "off",
			"@typescript-eslint/require-await": "warn",

			"@typescript-eslint/no-empty-object-type": "warn",
			"@typescript-eslint/no-unused-vars": [ "warn", {
				argsIgnorePattern: "^_",
				caughtErrorsIgnorePattern: "^_",
				varsIgnorePattern: "^_",
			} ],
			"@typescript-eslint/no-unused-expressions": "off",
			"@typescript-eslint/no-non-null-assertion": "off", // allow non-null assertion when necessary, but prefer proper null checks
			"prefer-const": "off",
			"no-var": "error",
			"eqeqeq": "error",
		}
	},

	// Svelte file override — keep projectService and add root rules
	{
		files: [ '**/*.svelte', '**/*.svelte.ts', '**/*.svelte.js' ],
		languageOptions: {
			parser: svelteParser,
			parserOptions: {
				projectService: true,
				extraFileExtensions: [ '.svelte' ],
				parser: tseslint.parser,
				svelteConfig
			}
		},
		rules: {
			"@typescript-eslint/no-unused-vars": [ "warn", {
				argsIgnorePattern: "^_",
				caughtErrorsIgnorePattern: "^_",
				varsIgnorePattern: "^_",
			} ],
			"svelte/prefer-svelte-reactive": "warn",
			"svelte/valid-compile": "error",
			"svelte/no-inspect": "warn",
			"svelte/no-at-html-tags": "off",
			"svelte/require-each-key": "off",
			"svelte/infinite-reactive-loop": "error",
			"svelte/no-target-blank": "error",
			"svelte/no-svelte-internal": "error",
			"svelte/no-reactive-literals": "error",
			"svelte/no-navigation-without-resolve": "off",
			"svelte/valid-prop-names-in-kit-pages": "warn"
		}
	}
);
