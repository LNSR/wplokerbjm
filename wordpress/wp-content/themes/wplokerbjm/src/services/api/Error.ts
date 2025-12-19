
export type ApiErrorType = 'network' | 'timeout' | 'http' | 'unknown' | 'circuit_breaker';

type ErrorWithCaptureStackTrace = ErrorConstructor & {
  captureStackTrace?: (target: object, constructor?: Function) => void;
};

export class ApiError extends Error {
  status: number;
  response?: Response | unknown;
  body?: unknown;
  url?: string;
  method?: string;
  payload?: unknown;
  code?: string | number;
  type: ApiErrorType;
  userMessage?: string;

  constructor(
    message: string,
    status: number = 0,
    response?: Response | unknown,
    body?: unknown,
    url?: string,
    method?: string,
    payload?: unknown,
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
    if (typeof (Error as ErrorWithCaptureStackTrace).captureStackTrace === 'function') {
      (Error as ErrorWithCaptureStackTrace).captureStackTrace!(this, ApiError);
    }
  }
}

export class TimeoutError extends ApiError {
  constructor(
    message: string = 'Request timed out',
    url?: string,
    method?: string,
    payload?: unknown
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
    payload?: unknown
  ) {
    super(message, 0, undefined, undefined, url, method, payload, 'ENETWORK', 'network', 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
    this.name = 'NetworkError';
  }
}