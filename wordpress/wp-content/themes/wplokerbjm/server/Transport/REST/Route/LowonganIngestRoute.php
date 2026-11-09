<?php

declare(strict_types=1);

namespace WPLokerBJM\Transport\REST\Route;

use WPLokerBJM\Transport\REST\Controllers\Ingest\LowonganIngestController;
use WPLokerBJM\Transport\REST\Controllers\Ingest\LowonganIngestOptionsController;
use WPLokerBJM\Core\Container\Attributes\Action;
use \WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\WPRestJWTHooks;

final class LowonganIngestRoute
{
    public const string NAMESPACE = 'wplokerbjm/v1';
    public const string ROUTE = '/lowongan/ingest';
    public const string ROUTE_OPTIONS = self::ROUTE . '/options';

    public function __construct(
        private readonly LowonganIngestController $controllerIngest,
        private readonly LowonganIngestOptionsController $optionsControllerIngest
    ) {}

    #[Action(
        'rest_api_init',
        acceptedArgs: 0,
        deferRegisterUntilHook: 'parse_request',
        registerIf: static function (): bool {
            return WPRestJWTHooks::isActive() &&
                isset($_SERVER['REQUEST_URI']) && \str_contains($_SERVER['REQUEST_URI'], rest_get_url_prefix() . '/' . self::NAMESPACE);
        }
    )]
    public function registerRoutes(): void
    {

        register_rest_route(self::NAMESPACE, self::ROUTE_OPTIONS, [
            'methods' => 'GET',
            'callback' => $this->optionsControllerIngest->options(...),
            'permission_callback' => $this->optionsControllerIngest->permissionsCheck(...),
        ]);
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods' => 'POST',
            'callback' => $this->controllerIngest->ingest(...),
            'permission_callback' => $this->controllerIngest->permissionsCheck(...),
        ]);
    }
}
