<?php

namespace Tests\Feature\Api;

use Tests\Concerns\InteractsWithScopedIndexValidation;

abstract class ScopedIndexValidationApiTestCase extends UpsertAuthorizationApiTestCase
{
    use InteractsWithScopedIndexValidation;
}
