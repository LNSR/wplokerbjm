<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Theme;

use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Resolvers\ThemeDataResolver;
use WPLokerBJM\Core\Wordpress\Theme\ThemeHooks;

/**
 * @phpstan-import-type ThemeData from ThemeHooks
 */
class GraphQLThemeRegistrationTypes
{
    public function __construct(private readonly ThemeDataResolver $themeDataResolver) {}
    public const TYPE_LOGO = 'Logo';
    public const TYPE_THEME_DATA = 'ThemeData';

    public function __invoke(): void
    {
        $this->registerObjectTypes();
        $this->registerRootFields();
    }

    private function registerObjectTypes(): void
    {
        /** @var ThemeData['logo'] $fieldLogo */
        $fieldLogo = [
            'logoUrl' => ['type' => GraphQLRegistration::TYPE_STRING],
            'logoSrcset' => ['type' => GraphQLRegistration::TYPE_STRING],
            'logoSizes' => ['type' => GraphQLRegistration::TYPE_STRING],
            'logoDecoding' => ['type' => GraphQLRegistration::TYPE_STRING],
            'logoWidth' => ['type' => GraphQLRegistration::TYPE_INT],
            'logoHeight' => ['type' => GraphQLRegistration::TYPE_INT],
        ];
        register_graphql_object_type(self::TYPE_LOGO, [
            'description' => 'Logo image data',
            'fields' => $fieldLogo,
        ]);

        /** @var ThemeData $fieldThemeData */
        $fieldThemeData = [
            'logo' => ['type' => self::TYPE_LOGO],
            'wpGraphqlNonce' => ['type' => GraphQLRegistration::TYPE_STRING],
            'siteIconTags' => ['type' => GraphQLRegistration::TYPE_STRING],
        ];
        register_graphql_object_type(self::TYPE_THEME_DATA, [
            'description' => 'Theme data object',
            'fields' => $fieldThemeData,
        ]);
    }

    private function registerRootFields(): void
    {
        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'themeData', [
            'type' => self::TYPE_THEME_DATA,
            'description' => 'Get theme data',
            'resolve' => $this->themeDataResolver->resolveThemeData(...),
        ]);
    }
}
