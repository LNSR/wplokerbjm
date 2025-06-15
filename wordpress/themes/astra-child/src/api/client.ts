export interface ApiResponse<T = any> {
  data: T
  status: number
  message?: string
}

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

  async get<T = any>(endpoint: string, params?: Record<string, string | number>): Promise<T> {
    const url = new URL(`${this.baseUrl}${endpoint}`)
    
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          url.searchParams.append(key, String(value))
        }
      })
    }

    try {
      const response = await this.fetchWithTimeout(url.toString(), {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      
      if (!response.ok) {
        throw new ApiError(
          `HTTP ${response.status}: ${response.statusText}`,
          response.status,
          response
        )
      }

      return await response.json()
    } catch (error) {
      if (error instanceof ApiError) {
        throw error
      }
      throw new ApiError(
        error instanceof Error ? error.message : 'Network error',
        0
      )
    }
  }

  async post<T = any>(endpoint: string, data?: Record<string, any>): Promise<T> {
    const url = new URL(`${this.baseUrl}${endpoint}`)

    try {
      const response = await this.fetchWithTimeout(url.toString(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: data ? JSON.stringify(data) : undefined
      })
      
      if (!response.ok) {
        throw new ApiError(
          `HTTP ${response.status}: ${response.statusText}`,
          response.status,
          response
        )
      }

      return await response.json()
    } catch (error) {
      if (error instanceof ApiError) {
        throw error
      }
      throw new ApiError(
        error instanceof Error ? error.message : 'Network error',
        0
      )
    }
  }
}

export const apiClient = new ApiClient()