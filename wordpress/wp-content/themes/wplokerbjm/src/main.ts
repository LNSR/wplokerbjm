import { SvelteMounter } from '@/services/Mounter';
import type { ComponentConfig } from '@/types';
import { isAppEl } from '@/utils/elements';

const configs: ComponentConfig[] = [
  { selector: isAppEl, component: await import('@/app.svelte') },
];

await new SvelteMounter().mount(configs);
