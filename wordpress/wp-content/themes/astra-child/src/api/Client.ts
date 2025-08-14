import { injectable } from "inversify";
import { ApiError, TimeoutError, NetworkError } from "./Error";
import { AuthService } from "@/services/AuthService";

@injectable()
export class ApiClient {
  private baseUrl: string
  private timeout = 15000; // 15 seconds

  constructor() {
    this.baseUrl = `${window.location.origin}/wp-json/astra-child/v1`
  }

  // Helper type for request options
  private isBodyAllowed(method?: string) {
    const m = (method || '').toUpperCase();
    return m === 'POST' || m === 'PUT' || m === 'PATCH' || m === 'DELETE' && false; // DELETE usually has no body for compatibility
  }

  private isFormData(value: any): value is FormData {
    return typeof FormData !== 'undefined' && value instanceof FormData;
  }

  private async fetchWithTimeout(resource: string, options: RequestInit = {}) {
    const controller = (typeof AbortController !== 'undefined') ? new AbortController() : undefined;
    const id = controller ? setTimeout(() => controller.abort(), this.timeout) : undefined;
    try {
      const response = await fetch(resource, { 
        ...options, 
        ...(controller ? { signal: controller.signal } : {}),
        credentials: 'include'
      });
      if (id) clearTimeout(id);
      return response;
    } catch (error: any) {
      if (id) clearTimeout(id);
      const method = options && (options as any).method;
      if (error && error.name === 'AbortError') {
        throw new TimeoutError('Request timed out', resource, method, (options as any).body);
      }
      // Browser network failure yields TypeError in many runtimes
      if (error instanceof TypeError) {
        throw new NetworkError(error.message || 'Network error', resource, method, (options as any).body);
      }
      throw new ApiError(
        error instanceof Error ? error.message : 'Unknown error',
        0,
        undefined,
        undefined,
        resource,
        method,
        (options as any).body,
        undefined,
        'unknown',
        'Terjadi kesalahan tak terduga.'
      );
    }
  }

  private async handleResponse(response: Response, url?: string, method?: string, payload?: any) {
    const contentType = response.headers.get('content-type') || '';
    let data: any = undefined;
    try {
      if (contentType.includes('application/json')) {
        data = await response.json();
      } else {
        data = await response.text();
      }
    } catch (e) {
      console.error('Error parsing response:', e);
      try {
        data = await response.text();
      } catch {
        data = undefined;
      }
    }

    if (!response.ok) {
      const message = (data && (data.message || data.error || JSON.stringify(data))) || response.statusText || `HTTP ${response.status}`;
      throw new ApiError(
        message,
        response.status,
        response,
        data,
        url,
        method,
        payload
      );
    }
    return data;
  }

  private buildHeaders(headers: Record<string, string> = {}) {
    const normalized: Record<string, string> = {};
    Object.entries(headers || {}).forEach(([k, v]) => {
      normalized[k] = v;
    });

    const base: Record<string, string> = {
      'X-Requested-With': 'XMLHttpRequest',
      ...normalized
    };

    // Add nonce if not provided (case-insensitive)
    const hasNonce = Object.keys(base).some(k => k.toLowerCase() === 'x-wp-nonce');
    if (!hasNonce) {
      const nonce = AuthService.getRestNonce();
      if (nonce) base['X-WP-Nonce'] = nonce;
    }

    return base;
  }

  private async request<T = any>(
    method: string,
    endpoint: string,
    { params, data, headers }: { params?: Record<string, string | number>, data?: any, headers?: Record<string, string> } = {}
  ): Promise<T> {
    let url = new URL(`${this.baseUrl}${endpoint}`);
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          url.searchParams.append(key, String(value));
        }
      });
    }
    try {
      const builtHeaders = this.buildHeaders(headers);
      let body: BodyInit | undefined = undefined;
      if (data !== undefined && this.isBodyAllowed(method)) {
        if (this.isFormData(data)) {
          Object.keys(builtHeaders).forEach(k => {
            if (k.toLowerCase() === 'content-type') delete builtHeaders[k];
          });
          body = data;
        } else {
          builtHeaders['Content-Type'] = builtHeaders['Content-Type'] || 'application/json';
          body = JSON.stringify(data);
        }
      }

      const response = await this.fetchWithTimeout(url.toString(), {
        method,
        headers: builtHeaders,
        body,
      });
      return await this.handleResponse(response, url.toString(), method, data);
    } catch (error: any) {
      if (error instanceof ApiError) throw error;
      if (error instanceof TimeoutError) throw error;
      if (error instanceof NetworkError) throw error;
      throw new ApiError(
        error instanceof Error ? error.message : 'Network error',
        0,
        undefined,
        undefined,
        url.toString(),
        method,
        data,
        undefined,
        'unknown',
        'Terjadi kesalahan tak terduga.'
      );
    }
  }

  async get<T = any>(endpoint: string, params?: Record<string, string | number>, headers: Record<string, string> = {}): Promise<T> {
    return this.request<T>('GET', endpoint, { params, headers });
  }

  async post<T = any>(endpoint: string, data?: Record<string, any>, headers: Record<string, string> = {}): Promise<T> {
    return this.request<T>('POST', endpoint, { data, headers });
  }

  async put<T = any>(endpoint: string, data?: Record<string, any>, headers: Record<string, string> = {}): Promise<T> {
    return this.request<T>('PUT', endpoint, { data, headers });
  }

  async delete<T = any>(endpoint: string, headers: Record<string, string> = {}): Promise<T> {
    return this.request<T>('DELETE', endpoint, { headers });
  }
}