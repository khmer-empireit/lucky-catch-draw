<?php

use App\Services\Excel\ExcelSafe;

it('prefixes a leading apostrophe onto formula-injection payloads', function (string $dangerous) {
    $safe = ExcelSafe::cell($dangerous);

    expect($safe)->toStartWith("'");
    expect($safe)->toBe("'".$dangerous);
})->with([
    '=HYPERLINK("http://evil.test","click")',
    '+1+1',
    '-1+1',
    '@SUM(1,1)',
    "\tsneaky",
    "\rsneaky",
]);

it('leaves ordinary values untouched', function () {
    expect(ExcelSafe::cell('Sok Dara'))->toBe('Sok Dara');
    expect(ExcelSafe::cell('sok.dara@example.com'))->toBe('sok.dara@example.com');
    expect(ExcelSafe::cell('https://facebook.com/sok.dara'))->toBe('https://facebook.com/sok.dara');
});

it('handles null and empty values without throwing', function () {
    expect(ExcelSafe::cell(null))->toBe('');
    expect(ExcelSafe::cell(''))->toBe('');
});

it('preserves khmer unicode text untouched', function () {
    $khmer = 'សុខ តារា';
    expect(ExcelSafe::cell($khmer))->toBe($khmer);
});
