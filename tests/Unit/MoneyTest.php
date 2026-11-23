<?php

use App\Support\Money;

it('переводит сумму в тенге и обратно', function () {
    expect(Money::toMinor('1 234,50'))->toBe(123450)
        ->and(Money::toMinor('85'))->toBe(8500)
        ->and(Money::format(123450))->toBe('1 234,50 TMT');
});

it('считает процент с округлением до тенге', function () {
    expect(Money::percentOf(27500, 30))->toBe(8250)
        ->and(Money::percentOf(999, 10))->toBe(100);
});
