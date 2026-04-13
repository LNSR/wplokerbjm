import type { SearchTitle, SearchContext, SearchFilters } from './Search';
import type { CardJob } from './Shared';
import type { Component } from 'svelte';

export interface ComponentConfig {
  selector: string;
  component: Component | Promise<Component>;
}


export interface SocialMediaItem {
  platform: string;
  username?: string;
  icon?: Component;
  url: string;
  color?: string;
}

// Props for the JobCard component (shared type)
export interface JobCardProps {
  jobdata?: CardJob;
  variant?: 'featured' | 'carousel' | 'bookmark' | 'detail';
}

export interface JobGridProps {
  jobs?: CardJob[];
  maxNumPages?: number;
  context?: SearchContext;
  filters?: Partial<SearchFilters>;
  title?: SearchTitle;
  total?: number;
} 

export interface CarouselProps {
  jobs: CardJob[];
  totalJobs?: number;
}