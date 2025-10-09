import { SvelteMounter } from '@/services/Mounter';
import type { ComponentConfig } from '@/types';

const configs: ComponentConfig[] = [
  { selector: '#app', component: await import('@/app.svelte') },
];

new SvelteMounter().mount(configs);
