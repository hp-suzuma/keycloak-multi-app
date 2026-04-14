<?php

namespace Tests\Concerns;

trait InteractsWithScopedIndexValidation
{
    protected function assertIndexRejectsInvalidFilters(string $uri, string $keycloakSub = 'keycloak-user-index-validation'): void
    {
        $this->assignRole($keycloakSub, 'server_admin');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken($keycloakSub))
            ->getJson($uri.'?scope_id=invalid&sort=scope_id&page=0&per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_id', 'sort', 'page', 'per_page']);
    }
}
