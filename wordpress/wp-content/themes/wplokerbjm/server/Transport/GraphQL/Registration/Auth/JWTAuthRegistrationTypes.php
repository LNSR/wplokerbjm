<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Auth;

use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Resolvers\Auth\JWTDataResolver;

class JWTAuthRegistrationTypes
{
    public function __construct(private readonly JWTDataResolver $jwtDataResolver) {}

    public function __invoke(): void
    {
        $this->registerObjectTypes();
        $this->registerInputTypes();
        $this->registerRootFields();
    }

    public function registerObjectTypes(): void {}

    public function registerInputTypes(): void {}

    public function registerRootFields(): void
    {
        register_graphql_field(GraphQLRegistration::TYPE_ROOT_MUTATION, 'jwt', [
            'type' => GraphQLRegistration::TYPE_STRING,
            'description' => 'Request or validate JWT token (provide username/password or existing token)',
            'args' => [
                'username' => ['type' => GraphQLRegistration::TYPE_STRING],
                'password' => ['type' => GraphQLRegistration::TYPE_STRING],
                'token' => ['type' => GraphQLRegistration::TYPE_STRING],
            ],
            'resolve' => $this->jwtDataResolver->resolveJWTorValidate(...),
        ]);
    }
}
