export class ApiError extends Error {
  status: number;
  response?: Response;

  constructor(
    message: string,
    status: number,
    response?: Response
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.response = response
  }
}

export class ApiClient {
  private baseUrl: string
  private timeout = 15000; // 15 seconds

  constructor() {
    this.baseUrl = `${window.location.origin}/wp-json/astra-child/v1`
  }

  private async fetchWithTimeout(resource: string, options: RequestInit = {}) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), this.timeout);
    try {
      const response = await fetch(resource, { ...options, signal: controller.signal });
      clearTimeout(id);
      return response;
    } catch (error) {
      clearTimeout(id);
      throw error;
    }
  }

  private async handleResponse(response: Response) {
    let data;
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      data = await response.json();
    } else {
      data = await response.text();
    }
    if (!response.ok) {
      throw new ApiError(
        `HTTP ${response.status}: ${response.statusText}`,
        response.status,
        response
      );
    }
    return data;
  }

  private buildHeaders(headers: Record<string, string> = {}) {
    return {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...headers
    };
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
      const response = await this.fetchWithTimeout(url.toString(), {
        method,
        headers: this.buildHeaders(headers),
        body: data ? JSON.stringify(data) : undefined
      });
      return await this.handleResponse(response);
    } catch (error) {
      if (error instanceof ApiError) throw error;
      throw new ApiError(
        error instanceof Error ? error.message : 'Network error',
        0
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

export const apiClient = new ApiClient()