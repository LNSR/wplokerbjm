import { svelteMounter } from '@/services/Mounter';
import type { ComponentConfig } from '@/types';
import { isAppEl } from '@/utils';
import app from '@/app.svelte';
import "@css/app.css";

const configs: ComponentConfig[] = [
  { selector: isAppEl, component: app },
];

svelteMounter.mount(configs);
