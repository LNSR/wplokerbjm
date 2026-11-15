import { defineEnvVars } from '@sveltejs/kit/env';

export const variables = defineEnvVars({
	PUBLIC_CMS_ORIGIN: { public: true, static: true },
	PUBLIC_CMS_ORIGIN_DEV: { public: true, static: true }
});