<?php

declare(strict_types=1);

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\QueryBuilders\TaxonomyQuery;
use WPLokerBJM\Services\REST\LowonganIngestService;
use WPLokerBJM\Shared\Log\Logger;

trait IngestControllerTrait
{
    /**
     * Return an HTTP status code for permission failures, or null when allowed.
     * @param \WP_REST_Request|null $request
     * @return int|null
     */
    public function getPermissionErrorStatus($request = null): ?int
    {
        return ControllerUtils::getPermissionErrorStatus($request);
    }
    
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
        $status = ControllerUtils::getPermissionErrorStatus($request);
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

    private const SCHEMA = 'lowongan_ingest_options.v1';

    /**
     * Taxonomies the agent may choose from during automated ingest.
     *
     * The perusahaan taxonomy is intentionally excluded because it is reserved
     * for human curation.
     *
     * @var string[]
     */
    private const AGENT_TAXONOMIES = [
        Taxonomies::KATEGORI_LOWONGAN,
        Taxonomies::LOKASI_PEKERJAAN,
        Taxonomies::JENIS_PEKERJAAN,
        Taxonomies::GENDER,
        Taxonomies::PENDIDIKAN,
    ];

    /**
     * Permission callback for the REST route.
     * @param \WP_REST_Request|null $request
     * @return true|\WP_Error
     */
    public function permissionsCheck($request = null)
    {
        $status = ControllerUtils::getPermissionErrorStatus($request);
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
        return new \WP_REST_Response($this->getOptionsData(), 200);
    }

    /**
     * @return array{schema: string, taxonomies: array<string, list<array{id: int, name: string, slug: string, parent: int}>>, reserved_taxonomies: string[], status_pekerjaan: list<array{value: int, label: string}>}
     */
    public function getOptionsData(): array
    {
        return [
            'schema' => self::SCHEMA,
            'taxonomies' => TaxonomyQuery::getTaxonomyOptions(self::AGENT_TAXONOMIES),
            'reserved_taxonomies' => [
                Taxonomies::PERUSAHAAN,
            ],
            CustomFields::STATUS_PEKERJAAN => [
                [
                    'value' => CustomFields::STATUS_PEKERJAAN_NORMAL,
                    'label' => 'Normal',
                ],
                [
                    'value' => CustomFields::STATUS_PEKERJAAN_URGENT,
                    'label' => 'Urgent',
                ],
                [
                    'value' => CustomFields::STATUS_PEKERJAAN_PINNED,
                    'label' => 'Pinned',
                ],
            ],
        ];
    }
}
