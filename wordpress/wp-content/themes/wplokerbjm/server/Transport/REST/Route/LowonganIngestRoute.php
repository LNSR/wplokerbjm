<?php

declare(strict_types=1);

namespace WPLokerBJM\Transport\REST\Route;

use WPLokerBJM\Transport\REST\Controllers\Ingest\LowonganIngestController;
use WPLokerBJM\Transport\REST\Controllers\Ingest\LowonganIngestOptionsController;
use WPLokerBJM\Core\Container\Attributes\Action;

final class LowonganIngestRoute
{
    public const string NAMESPACE = 'wplokerbjm/v1';
    public const string ROUTE = '/lowongan/ingest';
    public const string ROUTE_OPTIONS = self::ROUTE . '/options';

    public function __construct(
        private readonly LowonganIngestController $controllerIngestIngest,
        private readonly LowonganIngestOptionsController $optionsControllerIngestIngest
    ) {
    }

    #[Action('rest_api_init', acceptedArgs: 0,
        deferRegisterUntilHook: 'parse_request',
        registerIf: static function (): bool {
                return isset($_SERVER['REQUEST_URI']) && \str_contains($_SERVER['REQUEST_URI'], rest_get_url_prefix() . '/' . self::NAMESPACE);
                }
    )]
    public function registerRoutes(): void
    {

        register_rest_route(self::NAMESPACE , self::ROUTE_OPTIONS, [
            'methods' => 'GET',
            'callback' => $this->optionsControllerIngestIngest->options(...),
            'permission_callback' => $this->optionsControllerIngestIngest->permissionsCheck(...),
        ]);
        register_rest_route(self::NAMESPACE , self::ROUTE, [
            'methods' => 'POST',
            'callback' => $this->controllerIngestIngest->ingest(...),
            'permission_callback' => $this->controllerIngestIngest->permissionsCheck(...),
        ]);
    }
}