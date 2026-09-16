<?php

declare(strict_types=1);

namespace WPLokerBJM\Configs\Credential;

use WPLokerBJM\Shared\Utilities\DTO\AbstractDTO;

/**
 * @phpstan-type RedisCredType array{
 *     host: ?string,
 *     port: ?int,
 *     password: ?string,
 *     database: ?int,
 *     sock: ?string
 * }
 * @extends parent<RedisCredType>
 */
final readonly class RedisCred extends AbstractDTO
{
    public function __construct(
        public ?string $host = null,
        public ?int $port = null,
        public ?string $password = null,
        public ?int $database = null,
        public ?string $sock = null
    ) {}
}
/**
 * @phpstan-type R2CFCredType array{
 *     key: ?string,
 *     secret: ?string,
 *     bucket: ?string,
 *     domain: ?string,
 *     endpoint: ?string
 * }
 * @extends parent<R2CFCredType>
 */
final readonly class R2CFCred extends AbstractDTO
{
    public function __construct(
        public ?string $key = null,
        public ?string $secret = null,
        public ?string $bucket = null,
        public ?string $domain = null,
        public ?string $endpoint = null
    ) {}
}

/**
 * @phpstan-type CloudflareCredType array{
 *     token: ?string,
 *     zone: ?string
 * }
 * @extends parent<CloudflareCredType>
 */
final readonly class CloudflareCred extends AbstractDTO
{
    public function __construct(
        public ?string $token = null,
        public ?string $zone = null
    ) {}
}
