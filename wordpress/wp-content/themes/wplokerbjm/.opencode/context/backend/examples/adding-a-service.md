<!-- Context: backend/examples | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Example: Adding a Service

**Purpose**: How to create a new service class and wire it into the DI container
**Last Updated**: 2026-07-27

## Use Case
You need a new service (e.g., an email notification service) that gets autowired into controllers. The service must be discoverable by PHP-DI's autowiring and listed in container definitions.

## Code

```php
<?php
namespace WPLokerBJM\Services\Notification;

use DI\Attribute\Injectable;
use WPLokerBJM\Shared\Log\Logger;

#[Injectable(lazy: true)]
class EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        $sent = wp_mail($to, $subject, $body);
        Logger::info('EmailService', "Email to {$to}: " . ($sent ? 'sent' : 'failed'));
        return $sent;
    }
}
```

## Explanation
1. **Create class** in `server/Services/Notification/EmailService.php` (matching namespace)
2. **Add `#[Injectable(lazy: true)]`** — PHP-DI discovers and wires it, lazy defers instantiation
3. **Add to definitions** in `Core::getDefinitions()` or `Factory::getDefinitions()` if manual wiring needed
4. **Use via constructor injection** in any controller:

```php
public function __construct(
    private readonly EmailService $emailService,
) {}
```

**Key points**:
- Autowiring works by type-hint — no explicit binding needed for simple services
- Use `#[Injectable(lazy: true)]` to defer instantiation until first use
- For manual wiring, add to `server/Core/Container/Definitions/Factory.php`
- RobotLoader auto-discovers the class (no manual `require`)

## 📂 Codebase References

**Definition Files**:
- `server/Core/Container/Definitions/Core.php` — Core service definitions
- `server/Core/Container/Definitions/Factory.php` — Factory + manual wiring

**Real Service Examples**:
- `server/Services/GraphQL/GraphQLJobData.php` — Lazy-injectable GraphQL data service
- `server/Services/REST/LowonganIngestService.php` — Autowired REST ingest service
- `server/Services/Schema/JobSchemaOrg.php` — Schema.org JSON-LD generator

## Related
- `concepts/architecture.md` — Container + autowiring details
- `examples/adding-a-hook.md` — Use the service in a hook
- `examples/rest-endpoint.md` — Expose the service via REST
