<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Controllers\REST\{LowonganIngestOptionsController, LowonganIngestController};
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Services\REST\LowonganIngestRoute;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

class LowonganIngestOptionsRestTest extends WplokerbjmTestCase
{
    public function testRouteIsRegisteredForIngestOptions(): void
    {
        $registered = [];

        \Brain\Monkey\Functions\when('register_rest_route')->alias(function ($namespace, $route, $args) use (&$registered) {
            $registered = compact('namespace', 'route', 'args');
            return true;
        });

        $optionController = new LowonganIngestOptionsController();
        $optionsRoute = new LowonganIngestController();
        $route = new LowonganIngestRoute($optionsRoute, $optionController);
        $route->registerOptionsRoute();

        $this->assertSame('wplokerbjm/v1', $registered['namespace']);
        $this->assertSame('/lowongan/ingest/options', $registered['route']);
        $this->assertArrayHasKey('methods', $registered['args']);
        $this->assertArrayHasKey('callback', $registered['args']);
        $this->assertArrayHasKey('permission_callback', $registered['args']);
        $this->assertIsCallable($registered['args']['callback']);
        $this->assertIsCallable($registered['args']['permission_callback']);
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
