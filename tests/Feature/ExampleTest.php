<?php

test('the app root redirects guests to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
