import { SvelteMounter } from '@/services/Mounter';
import type { ComponentConfig } from '@/types';
import { isAppEl } from '@/utils';
import app from '@/app.svelte';

const configs: ComponentConfig[] = [
  { selector: isAppEl, component: app },
];
try {
  SvelteMounter.mount(configs);
} catch (error) {
  console.error('Error mounting Svelte components:', error);
}
