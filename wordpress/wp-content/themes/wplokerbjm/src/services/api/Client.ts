import { AuthService } from "@/services/AuthService";
import { ApiError, NetworkError, TimeoutError } from "@/services/api";
import type { ApiResponse, ApiMeta } from "@/types";

export class ApiClient {
  private readonly baseUrl: string
  private readonly timeout = 15000; // 15 seconds

  constructor() {
    this.baseUrl = `${window.location.origin}/wp-json/wplokerbjm/v1`
  }

  // Helper type for request options
  private isBodyAllowed(method?: string): boolean {
    const m = (method || '').toUpperCase();
    return m === 'POST' || m === 'PUT' || m === 'PATCH' || m === 'DELETE' && false; // DELETE usually has no body for compatibility
  }

  private isFormData(value: unknown): value is FormData {
    return typeof FormData !== 'undefined' && value instanceof FormData;
  }

  private async fetchWithTimeout(resource: string, options: RequestInit = {}, externalSignal?: AbortSignal): Promise<Response> {
    const controller = (typeof AbortController !== 'undefined') ? new AbortController() : undefined;
    const id = controller ? setTimeout(() => controller.abort(), this.timeout) : undefined;
    
    // Combine external signal with timeout signal
    let combinedSignal: AbortSignal | undefined;
    if (externalSignal && controller) {
      if (externalSignal.aborted) {
        controller.abort();
      } else {
        externalSignal.addEventListener('abort', () => controller?.abort());
        combinedSignal = controller.signal;
      }
    } else if (controller) {
      combinedSignal = controller.signal;
    } else if (externalSignal) {
      combinedSignal = externalSignal;
    }
    
    try {
      const response = await fetch(resource, { 
        ...options, 
        ...(combinedSignal ? { signal: combinedSignal } : {}),
        credentials: 'include'
      });
      if (id) clearTimeout(id);
      return response;
    } catch (error: unknown) {
      if (id) clearTimeout(id);
      const method = options.method;
      if (error && typeof error === 'object' && 'name' in error && error.name === 'AbortError') {
        if (externalSignal?.aborted) {
          throw externalSignal.reason || error;
        }
        throw new TimeoutError('Request timed out', resource, method, options.body);
      }
      // Browser network failure yields TypeError in many runtimes
      if (error instanceof TypeError) {
        throw new NetworkError(error.message || 'Network error', resource, method, options.body);
      }
      throw new ApiError(
        error instanceof Error ? error.message : 'Unknown error',
        0,
        undefined,
        undefined,
        resource,
        method,
        options.body,
        undefined,
        'unknown',
        'Terjadi kesalahan tak terduga.'
      );
    }
  }

  private async handleResponse(response: Response, url?: string, method?: string, payload?: unknown): Promise<{ data: unknown, meta: ApiMeta }> {
    const contentType = response.headers.get('content-type') || '';
    let data: unknown = undefined;
    try {
      if (contentType.includes('application/json')) {
        data = await response.json();
      } else {
        data = await response.text();
      }
    } catch {
      try {
        data = await response.text();
      } catch {
        data = undefined;
      }
    }

    // Extract pagination metadata from headers
    const total = response.headers.get('x-wp-total');
    const totalPages = response.headers.get('x-wp-totalpages');
    const linkHeader = response.headers.get('link');
    const links: Record<string, string> = {};
    if (linkHeader) {
      const linkParts = linkHeader.split(',');
      linkParts.forEach(part => {
        const match = part.trim().match(/<([^>]+)>;\s*rel="([^"]+)"/);
        if (match && match[1] && match[2]) {
          links[match[2]] = match[1];
        }
      });
    }
    const meta: ApiMeta = {
      total: total ? parseInt(total, 10) : undefined,
      totalPages: totalPages ? parseInt(totalPages, 10) : undefined,
      links: Object.keys(links).length > 0 ? links : undefined,
    };

    // Update nonce from response header if present
    const newNonce = response.headers.get('x-wp-nonce');
    if (newNonce) {
      AuthService.setRestNonce(newNonce);
    }

    if (!response.ok) {
      let message: string;
      if (data && typeof data === 'object' && data !== null) {
        const d = data as Record<string, unknown>;
        message = String(d['message'] || d['error'] || JSON.stringify(data));
      } else {
        message = String(data);
      }
      message = message || response.statusText || `HTTP ${response.status}`;
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
    return { data, meta };
  }

  private buildHeaders(headers: Record<string, string> = {}): Record<string, string> {
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

  private async request<T = unknown>(
    method: string,
    endpoint: string,
    { params, data, headers, signal }: { params?: Record<string, string | number>, data?: unknown, headers?: Record<string, string>, signal?: AbortSignal } = {}
  ): Promise<ApiResponse<T>> {
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
      }, signal);
      const { data: responseData, meta } = await this.handleResponse(response, url.toString(), method, data);
      return { data: responseData as T, meta };
    } catch (error: unknown) {
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

  async get<T = unknown>(endpoint: string, params?: Record<string, string | number>, headers: Record<string, string> = {}, signal?: AbortSignal): Promise<ApiResponse<T>> {
    return this.request<T>('GET', endpoint, { params, headers, signal });
  }

  async post<T = unknown>(endpoint: string, data?: Record<string, unknown>, headers: Record<string, string> = {}): Promise<ApiResponse<T>> {
    return this.request<T>('POST', endpoint, { data, headers });
  }

  async put<T = unknown>(endpoint: string, data?: Record<string, unknown>, headers: Record<string, string> = {}): Promise<ApiResponse<T>> {
    return this.request<T>('PUT', endpoint, { data, headers });
  }

  async delete<T = unknown>(endpoint: string, headers: Record<string, string> = {}): Promise<ApiResponse<T>> {
    return this.request<T>('DELETE', endpoint, { headers });
  }
}
export const apiClient = new ApiClient()