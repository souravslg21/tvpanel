<?php

test('jiotv route returns successful response', function () {
    $response = $this->get('/jiotv');

    $response->assertStatus(200);
});
