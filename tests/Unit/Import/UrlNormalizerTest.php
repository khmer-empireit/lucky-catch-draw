<?php

use App\Services\Import\UrlNormalizer;

it('validates http and https urls with a host', function () {
    expect(UrlNormalizer::isValid('https://facebook.com/sok.dara'))->toBeTrue();
    expect(UrlNormalizer::isValid('http://facebook.com/sok.dara'))->toBeTrue();
    expect(UrlNormalizer::isValid('ftp://facebook.com/sok.dara'))->toBeFalse();
    expect(UrlNormalizer::isValid('not a url'))->toBeFalse();
    expect(UrlNormalizer::isValid(''))->toBeFalse();
    expect(UrlNormalizer::isValid(null))->toBeFalse();
    expect(UrlNormalizer::isValid('https:///no-host'))->toBeFalse();
});

it('lowercases scheme and host', function () {
    expect(UrlNormalizer::normalize('HTTPS://FACEBOOK.COM/Sok.Dara'))
        ->toBe('https://facebook.com/Sok.Dara');
});

it('strips a leading www or m subdomain', function () {
    expect(UrlNormalizer::normalize('https://www.facebook.com/sok.dara'))
        ->toBe('https://facebook.com/sok.dara');

    expect(UrlNormalizer::normalize('https://m.facebook.com/sok.dara'))
        ->toBe('https://facebook.com/sok.dara');
});

it('removes a trailing slash', function () {
    expect(UrlNormalizer::normalize('https://facebook.com/sok.dara/'))
        ->toBe('https://facebook.com/sok.dara');
});

it('removes the fragment entirely', function () {
    expect(UrlNormalizer::normalize('https://facebook.com/sok.dara#bio'))
        ->toBe('https://facebook.com/sok.dara');
});

it('strips utm_ and known tracking query params but keeps the rest', function () {
    $normalized = UrlNormalizer::normalize(
        'https://www.facebook.com/sok.dara?utm_source=fb&utm_campaign=x&fbclid=abc&igshid=xyz&si=1&ref=share&tab=about'
    );

    expect($normalized)->toBe('https://facebook.com/sok.dara?tab=about');
});

it('treats equivalent urls as the same normalized value regardless of decoration', function () {
    $a = UrlNormalizer::normalize('https://www.facebook.com/sok.dara/?utm_source=ig&fbclid=123');
    $b = UrlNormalizer::normalize('HTTPS://M.FACEBOOK.COM/sok.dara#section');

    expect($a)->toBe($b);
});

it('keeps the original url completely untouched by normalization', function () {
    $original = 'https://WWW.Facebook.com/Sok.Dara/?utm_source=fb#bio';
    UrlNormalizer::normalize($original);

    expect($original)->toBe('https://WWW.Facebook.com/Sok.Dara/?utm_source=fb#bio');
});
