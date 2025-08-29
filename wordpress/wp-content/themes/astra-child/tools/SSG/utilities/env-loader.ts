import { loadEnv } from 'vite';

/**
 * Environment Loader Utility
 * Loads environment variables using Vite's loadEnv function
 */

export interface SSGConfig {
  concurrency: number;
  maxRetries: number;
  pageTimeout: number;
  continueOnError: boolean;
  minifyHtml: boolean;
}

export class EnvLoader {
  private static config: SSGConfig | null = null;

  /**
   * Load environment variables using Vite's loadEnv
   * This respects Vite's environment loading priority and conventions
   */
  static load(projectRoot: string = process.cwd(), mode: string = 'development'): Record<string, string> {
    // Use Vite's loadEnv to load environment variables
    const env = loadEnv(mode, projectRoot);

    // Set loaded environment variables to process.env
    Object.entries(env).forEach(([key, value]) => {
      if (!(key in process.env)) {
        process.env[key] = value;
      }
    });

    return env;
  }

  /**
   * Get SSG configuration from environment variables
   */
  static getSSGConfig(): SSGConfig {
    if (this.config) return this.config;

    this.config = {
      concurrency: parseInt(process.env['SSG_CONCURRENCY'] || '5'),
      maxRetries: parseInt(process.env['SSG_MAX_RETRIES'] || '3'),
      pageTimeout: parseInt(process.env['SSG_PAGE_TIMEOUT'] || '30000'),
      continueOnError: process.env['SSG_CONTINUE_ON_ERROR'] === 'true',
      minifyHtml: process.env['SSG_MINIFY_HTML'] === 'true'
    };

    return this.config;
  }

  /**
   * Get environment variable with fallback
   */
  static get(key: string, defaultValue: string = ''): string {
    return process.env[key] || defaultValue;
  }

  /**
   * Get environment variable as number with fallback
   */
  static getNumber(key: string, defaultValue: number = 0): number {
    const value = process.env[key];
    return value ? parseInt(value, 10) : defaultValue;
  }

  /**
   * Get environment variable as boolean
   */
  static getBoolean(key: string, defaultValue: boolean = false): boolean {
    const value = process.env[key];
    if (!value) return defaultValue;
    return value.toLowerCase() === 'true';
  }

  /**
   * Load environment and return SSG config
   */
  static loadAndGetConfig(projectRoot?: string, mode?: string): SSGConfig {
    this.load(projectRoot, mode);
    return this.getSSGConfig();
  }

  /**
   * Reset cached config (useful for testing)
   */
  static reset(): void {
    this.config = null;
  }
}

/**
 * Convenience function to load SSG config
 */
export function loadSSGConfig(projectRoot?: string, mode?: string): SSGConfig {
  return EnvLoader.loadAndGetConfig(projectRoot, mode);
}

/**
 * Convenience functions for common environment variables
 */
export const env = {
  get: (key: string, defaultValue: string = '') => EnvLoader.get(key, defaultValue),
  getNumber: (key: string, defaultValue: number = 0) => EnvLoader.getNumber(key, defaultValue),
  getBoolean: (key: string, defaultValue: boolean = false) => EnvLoader.getBoolean(key, defaultValue),
  load: (projectRoot?: string, mode?: string) => EnvLoader.load(projectRoot, mode),
  getSSGConfig: () => EnvLoader.getSSGConfig()
};