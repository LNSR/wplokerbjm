<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Controllers\REST\{LowonganIngestController, LowonganIngestOptionsController};
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Services\REST\LowonganIngestRoute;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

class LowonganIngestRestTest extends WplokerbjmTestCase
{
    // ----------------------------------------------------------------
    //  Route registration
    // ----------------------------------------------------------------

    public function testRouteIsRegisteredForLowonganIngestPost(): void
    {
        $registeredRoutes = [];

        \Brain\Monkey\Functions\when('register_rest_route')->alias(function ($namespace, $route, $args) use (&$registeredRoutes) {
            $registeredRoutes[] = compact('namespace', 'route', 'args');
            return true;
        });

        $controller = new LowonganIngestController();
        $optionsController = new LowonganIngestOptionsController();
        $route = new LowonganIngestRoute($controller, $optionsController);
        $route->registerRoutes();

        // registerRoutes() registers two routes: OPTIONS first, then POST.
        $post = $registeredRoutes[1] ?? null;
        $this->assertNotNull($post, 'Second registration should be the POST ingest route');
        $this->assertSame('wplokerbjm/v1', $post['namespace']);
        $this->assertSame('/lowongan/ingest', $post['route']);
        $this->assertSame('POST', $post['args']['methods']);
        $this->assertIsCallable($post['args']['callback']);
        $this->assertIsCallable($post['args']['permission_callback']);
    }

    public function testRouteIsRegisteredForIngestOptions(): void
    {
        $registeredRoutes = [];

        \Brain\Monkey\Functions\when('register_rest_route')->alias(function ($namespace, $route, $args) use (&$registeredRoutes) {
            $registeredRoutes[] = compact('namespace', 'route', 'args');
            return true;
        });

        $optionController = new LowonganIngestOptionsController();
        $controller = new LowonganIngestController();
        $route = new LowonganIngestRoute($controller, $optionController);
        $route->registerRoutes();

        // registerRoutes() registers two routes: OPTIONS first, then POST.
        $options = $registeredRoutes[0] ?? null;
        $this->assertNotNull($options, 'First registration should be the OPTIONS route');
        $this->assertSame('wplokerbjm/v1', $options['namespace']);
        $this->assertSame('/lowongan/ingest/options', $options['route']);
        $this->assertArrayHasKey('methods', $options['args']);
        $this->assertArrayHasKey('callback', $options['args']);
        $this->assertArrayHasKey('permission_callback', $options['args']);
        $this->assertIsCallable($options['args']['callback']);
        $this->assertIsCallable($options['args']['permission_callback']);
    }

    // ----------------------------------------------------------------
    //  Permission checks
    // ----------------------------------------------------------------

    public function testPermissionStatusRequiresBearerJwtAndEditPosts(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->alias(fn($capability) => $capability === 'edit_posts');

        $controller = new LowonganIngestController();

        $this->assertSame(401, $controller->getPermissionErrorStatus($this->requestWithBearer('')));
        $this->assertNull($controller->getPermissionErrorStatus($this->requestWithBearer()));
    }

    public function testPermissionStatusRequiresAuthenticatedJwtUser(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);

        $controller = new LowonganIngestOptionsController();

        $this->assertSame(401, $controller->getPermissionErrorStatus($this->requestWithBearer()));
    }

    public function testPermissionStatusRejectsLoggedInCookieWithoutBearerJwt(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);

        $controller = new LowonganIngestOptionsController();

        $this->assertSame(401, $controller->getPermissionErrorStatus($this->requestWithBearer('')));
    }

    public function testPermissionStatusRequiresEditPostsCapability(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->alias(
            fn($capability) => $capability === 'edit_posts' ? false : true
        );

        $controller = new LowonganIngestOptionsController();

        $this->assertSame(403, $controller->getPermissionErrorStatus($this->requestWithBearer()));
    }

    public function testPermissionStatusAllowsAuthenticatedEditor(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        \Brain\Monkey\Functions\when('current_user_can')->alias(
            fn($capability) => $capability === 'edit_posts'
        );

        $controller = new LowonganIngestOptionsController();

        $this->assertNull($controller->getPermissionErrorStatus($this->requestWithBearer()));
    }

    // ----------------------------------------------------------------
    //  Ingest validation
    // ----------------------------------------------------------------

    public function testMissingTitleReturnsBadRequest(): void
    {
        $controller = new LowonganIngestController();

        $result = $controller->createDraftFromPayload([
            CustomFields::NAMA_PERUSAHAAN => 'PT. Gracia Guna Medika',
            CustomFields::CARA_MELAMAR => '<p>Kirim CV via email.</p>',
        ], $this->fixtureUpload());

        $this->assertSame(400, $result['status']);
        $this->assertSame('missing_title', $result['data']['code']);
    }

    public function testPayloadWithoutMeaningfulDetailReturnsBadRequest(): void
    {
        $controller = new LowonganIngestController();

        $result = $controller->createDraftFromPayload([
            'title' => 'Marketing Alat Kesehatan | PT. Gracia Guna Medika',
            CustomFields::NAMA_PERUSAHAAN => 'PT. Gracia Guna Medika',
            Taxonomies::LOKASI_PEKERJAAN => 'Banjarmasin',
        ], $this->fixtureUpload());

        $this->assertSame(400, $result['status']);
        $this->assertSame('missing_meaningful_detail', $result['data']['code']);
    }

    // ----------------------------------------------------------------
    //  Ingest creation
    // ----------------------------------------------------------------

    public function testValidPayloadCreatesDraftPostAndFeaturedImage(): void
    {
        $insertedPosts = [];
        $metaUpdates = [];
        $termAssignments = [];
        $thumbnailAssignments = [];

        $this->mockCommonWordPressFunctions();
        \Brain\Monkey\Functions\when('get_posts')->justReturn([]);
        \Brain\Monkey\Functions\when('wp_insert_post')->alias(function ($postData) use (&$insertedPosts) {
            $insertedPosts[] = $postData;
            return 123;
        });
        \Brain\Monkey\Functions\when('update_post_meta')->alias(function ($postId, $key, $value) use (&$metaUpdates) {
            $metaUpdates[$postId][$key] = $value;
            return true;
        });
        \Brain\Monkey\Functions\when('get_terms')->alias(fn($args) => $this->termsForTaxonomy($args['taxonomy'] ?? ''));
        \Brain\Monkey\Functions\when('wp_set_object_terms')->alias(function ($postId, $termIds, $taxonomy) use (&$termAssignments) {
            $termAssignments[$taxonomy] = $termIds;
            return $termIds;
        });
        \Brain\Monkey\Functions\when('media_handle_upload')->justReturn(77);
        \Brain\Monkey\Functions\when('set_post_thumbnail')->alias(function ($postId, $attachmentId) use (&$thumbnailAssignments) {
            $thumbnailAssignments[] = compact('postId', 'attachmentId');
            return true;
        });
        \Brain\Monkey\Functions\when('get_edit_post_link')->alias(fn($postId) => 'https://example.test/wp-admin/post.php?post=' . $postId . '&action=edit');
        \Brain\Monkey\Functions\when('get_permalink')->alias(fn($postId) => 'https://example.test/lowongan/marketing-alat-kesehatan/');

        $controller = new LowonganIngestController();
        $result = $controller->createDraftFromPayload($this->validPayload(), $this->fixtureUpload());

        $this->assertSame(201, $result['status']);
        $this->assertSame(123, $result['data']['id']);
        $this->assertSame('draft', $result['data']['status']);
        $this->assertSame('https://example.test/wp-admin/post.php?post=123&action=edit', $result['data']['edit_url']);
        $this->assertSame('https://example.test/lowongan/marketing-alat-kesehatan/', $result['data']['permalink']);

        $this->assertSame([
            'post_type' => PostTypes::POST_TYPE_LOWONGAN,
            'post_status' => 'draft',
            'post_title' => 'Marketing Alat Kesehatan | PT. Gracia Guna Medika',
            'post_content' => '',
        ], $insertedPosts[0]);

        $this->assertSame('PT. Gracia Guna Medika', $metaUpdates[123][CustomFields::NAMA_PERUSAHAAN]);
        $this->assertSame(['+62811289976'], $metaUpdates[123][CustomFields::NOMOR_KONTAK]);
        $this->assertSame([
            [
                'WhatsApp' => '+62811289976',
            ],
        ], $metaUpdates[123][CustomFields::SOCIAL_MEDIA]);
        $this->assertArrayHasKey('_wplokerbjm_ingest_hash', $metaUpdates[123]);
        $this->assertSame('2026-06-05_10-56-28_UTC.webp', $metaUpdates[123]['_wplokerbjm_ingest_source']);

        $this->assertArrayNotHasKey(Taxonomies::PERUSAHAAN, $termAssignments);
        $this->assertSame([35, 111], $termAssignments[Taxonomies::LOKASI_PEKERJAAN]);
        $this->assertSame([62, 25], $termAssignments[Taxonomies::PENDIDIKAN]);

        $this->assertSame([
            ['postId' => 123, 'attachmentId' => 77],
        ], $thumbnailAssignments);
        $this->assertContains(
            'perusahaan taxonomy is reserved for manual review and was not assigned.',
            $result['data']['warnings']
        );
    }

    public function testDuplicateFlyerHashReturnsConflict(): void
    {
        $this->mockCommonWordPressFunctions();
        \Brain\Monkey\Functions\when('get_posts')->justReturn([555]);

        $controller = new LowonganIngestController();
        $result = $controller->createDraftFromPayload($this->validPayload(), $this->fixtureUpload());

        $this->assertSame(409, $result['status']);
        $this->assertSame('duplicate_flyer', $result['data']['code']);
        $this->assertSame(555, $result['data']['existing_id']);
    }

    public function testUnknownControlledTaxonomyIsSkippedWithWarning(): void
    {
        $this->mockCommonWordPressFunctions();
        \Brain\Monkey\Functions\when('get_posts')->justReturn([]);
        \Brain\Monkey\Functions\when('wp_insert_post')->justReturn(123);
        \Brain\Monkey\Functions\when('update_post_meta')->justReturn(true);
        \Brain\Monkey\Functions\when('get_terms')->alias(fn($args) => $this->termsForTaxonomy($args['taxonomy'] ?? ''));
        \Brain\Monkey\Functions\when('wp_set_object_terms')->justReturn([]);
        \Brain\Monkey\Functions\when('media_handle_upload')->justReturn(77);
        \Brain\Monkey\Functions\when('set_post_thumbnail')->justReturn(true);
        \Brain\Monkey\Functions\when('get_edit_post_link')->justReturn('edit-url');
        \Brain\Monkey\Functions\when('get_permalink')->justReturn('permalink');

        $payload = $this->validPayload();
        $payload[Taxonomies::JENIS_PEKERJAAN] = 'Super Shift';

        $controller = new LowonganIngestController();
        $result = $controller->createDraftFromPayload($payload, $this->fixtureUpload());

        $this->assertSame(201, $result['status']);
        $this->assertContains(
            'Unknown jenis_pekerjaan term skipped: Super Shift',
            $result['data']['warnings']
        );
    }

    public function testSocialMediaFieldsetPayloadCreatesCloneMeta(): void
    {
        $metaUpdates = [];

        $this->mockCommonWordPressFunctions();
        \Brain\Monkey\Functions\when('get_posts')->justReturn([]);
        \Brain\Monkey\Functions\when('wp_insert_post')->justReturn(123);
        \Brain\Monkey\Functions\when('update_post_meta')->alias(function ($postId, $key, $value) use (&$metaUpdates) {
            $metaUpdates[$postId][$key] = $value;
            return true;
        });
        \Brain\Monkey\Functions\when('get_terms')->alias(fn($args) => $this->termsForTaxonomy($args['taxonomy'] ?? ''));
        \Brain\Monkey\Functions\when('wp_set_object_terms')->justReturn([]);
        \Brain\Monkey\Functions\when('media_handle_upload')->justReturn(77);
        \Brain\Monkey\Functions\when('set_post_thumbnail')->justReturn(true);
        \Brain\Monkey\Functions\when('get_edit_post_link')->justReturn('edit-url');
        \Brain\Monkey\Functions\when('get_permalink')->justReturn('permalink');

        $payload = $this->validPayload();
        $payload[CustomFields::SOCIAL_MEDIA] = [
            [
                'WhatsApp' => '+62811289976',
                'Instagram' => 'gracia.guna.medika',
                'Unknown' => 'skip-me',
            ],
        ];

        $controller = new LowonganIngestController();
        $result = $controller->createDraftFromPayload($payload, $this->fixtureUpload());

        $this->assertSame(201, $result['status']);
        $this->assertSame([
            [
                'WhatsApp' => '+62811289976',
                'Instagram' => 'gracia.guna.medika',
            ],
        ], $metaUpdates[123][CustomFields::SOCIAL_MEDIA]);
    }

    // ----------------------------------------------------------------
    //  Options data
    // ----------------------------------------------------------------

    public function testOptionsExposeAgentSafeTaxonomiesAndStatuses(): void
    {
        \Brain\Monkey\Functions\when('is_wp_error')->alias(fn($value) => false);
        \Brain\Monkey\Functions\when('get_terms')->alias(function ($args) {
            $taxonomy = $args['taxonomy'] ?? '';
            return match ($taxonomy) {
                Taxonomies::KATEGORI_LOWONGAN => [
                    $this->term(10, 'Admin', 'admin'),
                ],
                Taxonomies::LOKASI_PEKERJAAN => [
                    $this->term(20, 'Banjarmasin', 'banjarmasin', 46),
                ],
                Taxonomies::JENIS_PEKERJAAN => [
                    $this->term(30, 'Full Time', 'fulltime'),
                ],
                Taxonomies::GENDER => [
                    $this->term(40, 'Pria', 'pria'),
                    $this->term(41, 'Wanita', 'wanita'),
                ],
                Taxonomies::PENDIDIKAN => [
                    $this->term(50, 'SMA/SMU/SMK/MA', 'sma-smk'),
                ],
                default => [],
            };
        });

        $controller = new LowonganIngestOptionsController();
        $options = $controller->getOptionsData();

        $this->assertSame(['perusahaan'], $options['reserved_taxonomies']);
        $this->assertArrayNotHasKey(Taxonomies::PERUSAHAAN, $options['taxonomies']);

        foreach ([
            Taxonomies::KATEGORI_LOWONGAN,
            Taxonomies::LOKASI_PEKERJAAN,
            Taxonomies::JENIS_PEKERJAAN,
            Taxonomies::GENDER,
            Taxonomies::PENDIDIKAN,
        ] as $taxonomy) {
            $this->assertArrayHasKey($taxonomy, $options['taxonomies']);
            $this->assertNotEmpty($options['taxonomies'][$taxonomy]);
        }

        $this->assertSame([
            ['value' => CustomFields::STATUS_PEKERJAAN_NORMAL, 'label' => 'Normal'],
            ['value' => CustomFields::STATUS_PEKERJAAN_URGENT, 'label' => 'Urgent'],
            ['value' => CustomFields::STATUS_PEKERJAAN_PINNED, 'label' => 'Pinned'],
        ], $options['status_pekerjaan']);

        $this->assertSame([
            'id' => 20,
            'name' => 'Banjarmasin',
            'slug' => 'banjarmasin',
            'parent' => 46,
        ], $options['taxonomies'][Taxonomies::LOKASI_PEKERJAAN][0]);
    }

    public function testOptionsAreExpandableWithSchemaMetadata(): void
    {
        \Brain\Monkey\Functions\when('is_wp_error')->alias(fn($value) => false);
        \Brain\Monkey\Functions\when('get_terms')->justReturn([]);

        $controller = new LowonganIngestOptionsController();
        $options = $controller->getOptionsData();

        $this->assertSame('lowongan_ingest_options.v1', $options['schema']);
        $this->assertArrayHasKey('taxonomies', $options);
        $this->assertArrayHasKey('status_pekerjaan', $options);
        $this->assertArrayHasKey('reserved_taxonomies', $options);
    }

    public function testAgentIngestEnvContractUsesDevNames(): void
    {
        foreach ([
            'WPLBJM_API_BASE_URL_DEV',
            'WPLBJM_JWT_DEV',
        ] as $key) {
            $this->assertTrue(defined($key), "{$key} should be loaded by configs/wp-config-extra.php.");
        }

        $devBaseUrl = constant('WPLBJM_API_BASE_URL_DEV');
        if (is_string($devBaseUrl) && $devBaseUrl !== '') {
            $this->assertContains(parse_url($devBaseUrl, PHP_URL_SCHEME), ['http', 'https']);
        }

        $devJwt = constant('WPLBJM_JWT_DEV');
        if (is_string($devJwt) && $devJwt !== '') {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $devJwt);
        }
    }

    // ----------------------------------------------------------------
    //  Shared helpers
    // ----------------------------------------------------------------

    private function validPayload(): array
    {
        return [
            'title' => 'Marketing Alat Kesehatan | PT. Gracia Guna Medika',
            CustomFields::NAMA_PERUSAHAAN => 'PT. Gracia Guna Medika',
            Taxonomies::PERUSAHAAN => 'PT. Gracia Guna Medika',
            Taxonomies::LOKASI_PEKERJAAN => 'Banjarmasin, Kalimantan',
            Taxonomies::PENDIDIKAN => 'D3, S1',
            CustomFields::STATUS_PEKERJAAN => 0,
            CustomFields::TENTANG_PERUSAHAAN => '<p>PT. Gracia Guna Medika adalah distributor alat kesehatan.</p>',
            CustomFields::PERSYARATAN => '<ul><li>Pendidikan minimal D3/S1.</li></ul>',
            CustomFields::CARA_MELAMAR => '<p>Kirim CV melalui WhatsApp.</p>',
            CustomFields::EMAIL_KONTAK => 'gracia.guna.medika@gmail.com',
            CustomFields::NOMOR_KONTAK => '+62811289976',
            CustomFields::SOCIAL_MEDIA => 'WhatsApp: +62811289976',
        ];
    }

    private function fixtureUpload(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'wplbjm-flyer-');
        file_put_contents($path, 'flyer-bytes');

        return [
            'name' => '2026-06-05_10-56-28_UTC.webp',
            'type' => 'image/webp',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ];
    }

    private function mockCommonWordPressFunctions(): void
    {
        \Brain\Monkey\Functions\when('is_wp_error')->alias(fn($value) => false);
    }

    private function termsForTaxonomy(string $taxonomy): array
    {
        return match ($taxonomy) {
            Taxonomies::LOKASI_PEKERJAAN => [
                $this->term(35, 'Banjarmasin', 'banjarmasin'),
                $this->term(111, 'Kalimantan', 'kalimantan'),
            ],
            Taxonomies::PENDIDIKAN => [
                $this->term(62, 'D3', 'd3'),
                $this->term(25, 'S1', 's1'),
            ],
            Taxonomies::JENIS_PEKERJAAN => [
                $this->term(40, 'Full Time', 'fulltime'),
            ],
            Taxonomies::GENDER => [
                $this->term(18, 'Pria', 'pria'),
                $this->term(19, 'Wanita', 'wanita'),
            ],
            Taxonomies::KATEGORI_LOWONGAN => [
                $this->term(80, 'Marketing', 'marketing'),
            ],
            default => [],
        };
    }

    private function term(int $id, string $name, string $slug, int $parent = 0): object
    {
        return (object) [
            'term_id' => $id,
            'name' => $name,
            'slug' => $slug,
            'parent' => $parent,
        ];
    }

    private function requestWithBearer(string $token = 'test.jwt.token'): object
    {
        return new class ($token) {
            public function __construct(private string $token)
            {
            }

            public function get_header(string $header): string
            {
                if (strtolower($header) !== 'authorization' || $this->token === '') {
                    return '';
                }

                return 'Bearer ' . $this->token;
            }
        };
    }
}
