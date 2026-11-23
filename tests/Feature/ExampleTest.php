<?php

use Inertia\Testing\AssertableInertia;

test('the application returns a successful response', function () {
    config(['app.name' => 'Example Application']);
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->where('appName', 'Example Application')
        );
});
