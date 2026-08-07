<!-- Context: backend/examples | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Example: REST Endpoint

**Purpose**: Pattern for creating REST API endpoints (based on LowonganIngestController)
**Last Updated**: 2026-07-27

## Use Case
You need a custom REST endpoint that accepts JSON payload, validates permissions via shared `ControllerUtils`, delegates to a service, and returns `WP_REST_Response`.

## Code

```php
<?php
namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Controllers\Utilities\ControllerUtils;

class MyRestController
{
    public function __construct(
        private readonly MyService $service,
    ) {}

    public function permissionsCheck($request = null)
    {
        $status = ControllerUtils::getPermissionErrorStatus($request);
        if ($status === null) return true;
        $code = $status === 401 ? 'wplokerbjm_rest_unauthorized' : 'wplokerbjm_rest_forbidden';
        return new \WP_Error($code, 'Permission denied.', ['status' => $status]);
    }

    public function handle(\WP_REST_Request $request)
    {
        $payload = json_decode((string) $request->get_param('payload'), true);
        if (!is_array($payload)) {
            return new \WP_REST_Response([
                'code' => 'invalid_payload',
                'message' => 'payload must be valid JSON.',
            ], 400);
        }
        $result = $this->service->process($payload);
        return new \WP_REST_Response($result['data'], $result['status']);
    }
}
```

## Explanation
1. **Constructor injection** — PHP-DI autowires the service
2. **Permissions** — reuse `ControllerUtils::getPermissionErrorStatus()` pattern
3. **Validation** — check payload shape before delegating
4. **Response** — return `WP_REST_Response(data, status)` with error codes

**Key points**:
- Route registration via `register_rest_route()` in Factory definitions
- Shared permission logic in `ControllerUtils` — don't duplicate
- Always validate payload structure before processing
- Log warnings/errors with `Logger::warning()` / `Logger::error()`

## 📂 Codebase References

**Full Implementation**:
- `server/Controllers/REST/LowonganIngestController.php` — Production REST controller (162 lines)
- `server/Controllers/Utilities/ControllerUtils.php` — Shared permission/schema utilities
- `server/Services/REST/LowonganIngestService.php` — Service layer for ingest logic

**Route Registration**:
- `server/Services/REST/Route/` — REST route definitions registered in container

## Related
- `examples/adding-a-service.md` — Create the service layer first
- `examples/adding-a-hook.md` — Hook pattern (REST doesn't use attribute hooks)
