
export type ApiErrorType = 'network' | 'timeout' | 'http' | 'unknown';

export class ApiError extends Error {
  status: number;
  response?: any;
  body?: any;
  url?: string;
  method?: string;
  payload?: any;
  code?: string | number;
  type: ApiErrorType;
  userMessage?: string;

  constructor(
    message: string,
    status: number = 0,
    response?: any,
    body?: any,
    url?: string,
    method?: string,
    payload?: any,
    code?: string | number,
    type: ApiErrorType = 'unknown',
    userMessage?: string
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.response = response;
    this.body = body;
    this.url = url;
    this.method = method;
    this.payload = payload;
    this.code = code;
    this.type = type;
    this.userMessage = userMessage;
    if (typeof (Error as any).captureStackTrace === 'function') {
      (Error as any).captureStackTrace(this, ApiError);
    }
  }
}

export class TimeoutError extends ApiError {
  constructor(
    message: string = 'Request timed out',
    url?: string,
    method?: string,
    payload?: any
  ) {
    super(message, 0, undefined, undefined, url, method, payload, 'ETIMEDOUT', 'timeout', 'Permintaan melebihi batas waktu. Silakan coba lagi.');
    this.name = 'TimeoutError';
  }
}

export class NetworkError extends ApiError {
  constructor(
    message: string = 'Network error',
    url?: string,
    method?: string,
    payload?: any
  ) {
    super(message, 0, undefined, undefined, url, method, payload, 'ENETWORK', 'network', 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
    this.name = 'NetworkError';
  }
}