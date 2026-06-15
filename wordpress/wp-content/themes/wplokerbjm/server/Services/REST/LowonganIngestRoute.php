<?php

declare(strict_types=1);

namespace WPLokerBJM\Services\REST;

use WPLokerBJM\Controllers\REST\LowonganIngestController;
use WPLokerBJM\Controllers\REST\LowonganIngestOptionsController;
use WPLokerBJM\Core\Container\Attributes\Action;

class LowonganIngestRoute
{
    public const NAMESPACE = 'wplokerbjm/v1';
    public const ROUTE = '/lowongan/ingest';
    public const ROUTE_OPTIONS = self::ROUTE . '/options';

    public function __construct(
        private LowonganIngestController $controller,
        private LowonganIngestOptionsController $optionsController
    ) {
    }

    #[Action('rest_api_init', acceptedArgs: 0)]
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE , self::ROUTE, [
            'methods' => 'POST',
            'callback' => [$this->controller, 'ingest'],
            'permission_callback' => [$this->controller, 'permissionsCheck'],
        ]);
    }

    #[Action('rest_api_init', acceptedArgs: 0)]
    public function registerOptionsRoute(): void
    {
        register_rest_route(self::NAMESPACE , self::ROUTE_OPTIONS, [
            'methods' => 'GET',
            'callback' => [$this->optionsController, 'options'],
            'permission_callback' => [$this->optionsController, 'permissionsCheck'],
        ]);
    }
}
