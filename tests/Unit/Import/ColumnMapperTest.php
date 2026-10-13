<?php

use App\Services\Import\ColumnMapper;

it('auto-matches headers case- and space-insensitively', function () {
    $headers = ['  Participant   Name ', 'PROFILE URL', 'Email Address', 'Phone Number', 'Participant ID'];

    $map = ColumnMapper::guess($headers);

    expect($map)->toBe([
        'name' => 0,
        'profile_url' => 1,
        'email' => 2,
        'phone' => 3,
        'external_id' => 4,
    ]);
});

it('only maps the fields it recognizes and ignores unknown columns', function () {
    $headers = ['Name', 'Some Random Column', 'Url'];

    $map = ColumnMapper::guess($headers);

    expect($map)->toBe([
        'name' => 0,
        'profile_url' => 2,
    ]);
});

it('does not map required fields when headers are unrecognized', function () {
    $map = ColumnMapper::guess(['Column A', 'Column B']);

    expect($map)->toBe([]);
});
