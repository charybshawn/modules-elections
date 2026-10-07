<?php

/*
 * This module's tests. They run inside the host app (it provides the User
 * model, the Event log, the admin layout and the database), which loads this
 * file from vendor/cultpantry/elections/tests -- see the host's tests/Pest.php
 * and phpunit.xml. Run them from the host: `php artisan test --testsuite=Modules`.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__);
