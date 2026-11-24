<?php

use App\Support\Phone;

it('приводит туркменские номера к виду +993XXXXXXXX', function (string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['+993 61 23-45-67', '+99361234567'],
    ['99361234567', '+99361234567'],
    ['8 61 234567', '+99361234567'],
    ['61234567', '+99361234567'],
    ['6123456', null],
    ['+7 916 123 45 67', null],
    ['', null],
]);

it('форматирует номер для показа', function () {
    expect(Phone::format('+99361234567'))->toBe('+993 61 234567');
});
