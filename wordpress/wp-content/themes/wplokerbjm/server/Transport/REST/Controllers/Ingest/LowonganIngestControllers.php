<?php

declare(strict_types=1);

namespace WPLokerBJM\Transport\REST\Controllers\Ingest;

use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Repositories\TaxonomyRepository;
use WPLokerBJM\QueryBuilders\TaxonomyQuery;
use WPLokerBJM\Services\REST\Ingest\LowonganIngestService;
use WPLokerBJM\Shared\Log\Logger;

trait IngestControllerTrait
{

    /**
     * @param \WP_REST_Request|mixed $request
     * @return int|null 401|403|null
     */
    public function getPermissionErrorStatus($request = null): ?int
    {
        if (!$this->hasBearerAuthorization($request)) {
            return 401;
        }

        if (!is_user_logged_in()) {
            return 401;
        }

        if (!current_user_can('edit_posts')) {
            return 403;
        }

        return null;
    }

    /**
     * @param \WP_REST_Request|mixed $request
     * @return bool
     */
    private function hasBearerAuthorization($request): bool
    {
        $authorization = '';

        if (is_object($request) && method_exists($request, 'get_header')) {
            $authorization = (string) $request->get_header('authorization');
        }

        if ($authorization === '') {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        return $authorization
        |> (static fn($auth) => trim((string) $auth))
        |> (static fn($auth) => preg_match('/^Bearer\s+\S+$/i', $auth) === 1);
    }

    /**
     * Permission callback for the REST route.
     * @param \WP_REST_Request|null $request
     * @return true|\WP_Error
     */
    abstract public function permissionsCheck($request = null);
}

class LowonganIngestController
{
    use IngestControllerTrait;

    public function __construct(
        private readonly LowonganIngestService $service,
    ) {}

    /**
     * @return true|\WP_Error
     */
    public function permissionsCheck($request = null)
    {
        $status = $this->getPermissionErrorStatus($request);
        if ($status === null) {
            return true;
        }

        $code = $status === 401 ? 'wplokerbjm_rest_unauthorized' : 'wplokerbjm_rest_forbidden';
        $message = $status === 401
            ? 'Authentication required.'
            : 'You do not have permission to ingest lowongan drafts.';

        Logger::warning('LowonganIngest', 'Ingest permission check failed.', [
            'status' => $status,
            'code' => $code,
        ]);

        return new \WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function ingest(\WP_REST_Request $request)
    {
        $payloadJson = $request->get_param('payload');
        $files = $request->get_file_params();

        $payload = json_decode((string) $payloadJson, true);
        if (!is_array($payload)) {
            Logger::warning('LowonganIngest', 'Rejected ingest request with invalid JSON payload.', [
                'json_error' => json_last_error_msg(),
                'has_featured_image' => isset($files['featured_image']),
            ]);

            return new \WP_REST_Response([
                'code' => 'invalid_payload',
                'message' => 'payload must be a valid JSON object.',
                'warnings' => [],
            ], 400);
        }

        $result = $this->service->createDraftFromPayload($payload, $files['featured_image'] ?? null);

        return new \WP_REST_Response($result['data'], $result['status']);
    }
}

class LowonganIngestOptionsController
{

    use IngestControllerTrait;
    public function __construct(
        private readonly LowonganIngestService $service,
    ) {}

    /**
     * Permission callback for the REST route.
     * @param \WP_REST_Request|null $request
     * @return true|\WP_Error
     */
    public function permissionsCheck($request = null)
    {
        $status = $this->getPermissionErrorStatus($request);
        if ($status === null) {
            return true;
        }

        $code = $status === 401 ? 'wplokerbjm_rest_unauthorized' : 'wplokerbjm_rest_forbidden';
        $message = $status === 401
            ? 'Authentication required.'
            : 'You do not have permission to access ingest options.';

        return new \WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function options()
    {
        return new \WP_REST_Response($this->service->getTaxonomyOptionsData(), 200);
    }
}
