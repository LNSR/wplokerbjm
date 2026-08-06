<?php

declare(strict_types=1);

namespace WPLokerBJM\Services\REST\Route;

use WPLokerBJM\Controllers\REST\LowonganIngestController;
use WPLokerBJM\Controllers\REST\LowonganIngestOptionsController;
use WPLokerBJM\Core\Container\Attributes\Action;
use DI\Attribute\Injectable;

final class LowonganIngestRoute
{
    public const NAMESPACE = 'wplokerbjm/v1';
    public const ROUTE = '/lowongan/ingest';
    public const ROUTE_OPTIONS = self::ROUTE . '/options';

    public function __construct(
        private readonly LowonganIngestController $controller,
        private readonly LowonganIngestOptionsController $optionsController
    ) {
    }

    #[Action('rest_api_init', acceptedArgs: 0)]
    public function registerRoutes(): void
    {

        register_rest_route(self::NAMESPACE , self::ROUTE_OPTIONS, [
        'methods' => 'GET',
            'callback' => $this->optionsController->options(...),
            'permission_callback' => $this->optionsController->permissionsCheck(...),
        ]);
        register_rest_route(self::NAMESPACE , self::ROUTE, [
            'methods' => 'POST',
            'callback' => $this->controller->ingest(...),
            'permission_callback' => $this->controller->permissionsCheck(...),
        ]);
    }
}