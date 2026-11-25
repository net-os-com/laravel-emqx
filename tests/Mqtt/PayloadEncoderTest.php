<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Jsonable;
use NetOs\Emqx\Mqtt\PayloadEncoder;

it('encodes payloads to JSON', function (mixed $payload, string $expected) {
    expect((new PayloadEncoder)->encode($payload))->toBe($expected);
})->with([
    'a string as is' => ['plain text', 'plain text'],
    'an array' => [['bookingId' => 1, 'lock' => true], '{"bookingId":1,"lock":true}'],
    'slashes and unicode unescaped' => [['topic' => 'a/b', 'name' => 'Zoë'], '{"topic":"a/b","name":"Zoë"}'],
    'a JsonSerializable' => [new class implements JsonSerializable
    {
        public function jsonSerialize(): array
        {
            return ['a' => 1];
        }
    }, '{"a":1}'],
    'a Jsonable' => [new class implements Jsonable
    {
        public function toJson($options = 0): string
        {
            return '{"from":"jsonable"}';
        }
    }, '{"from":"jsonable"}'],
]);
