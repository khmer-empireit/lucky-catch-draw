<?php

use App\Services\Audit\Auditor;
use App\Services\Draw\DrawService;

it('distributes 10,000 draws over 10 participants within 9-11% each', function () {
    $service = new DrawService(new Auditor());

    $poolSize = 10;
    $iterations = 10_000;
    $counts = array_fill(0, $poolSize, 0);

    for ($i = 0; $i < $iterations; $i++) {
        $index = $service->pickWinnerIndex($poolSize);
        expect($index)->toBeGreaterThanOrEqual(0)->and($index)->toBeLessThan($poolSize);
        $counts[$index]++;
    }

    foreach ($counts as $index => $count) {
        $share = $count / $iterations;
        expect($share)->toBeGreaterThanOrEqual(0.09)
            ->and($share)->toBeLessThanOrEqual(0.11);
    }
});
