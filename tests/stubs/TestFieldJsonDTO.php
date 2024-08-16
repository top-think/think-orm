<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\contracts\FieldTypeTransform;

class TestFieldJsonDTO implements FieldTypeTransform, \JsonSerializable
{
    public function __construct(
        public int $num1,
        public string $str1
    )
    {
    }

    public static function fromData(array|string $data): static
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return new self(...$data);
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    public function __toString(): string
    {
        return json_encode($this);
    }

    public static function get(mixed $value, Model $model): static
    {
        return static::fromData($value);
    }

    public static function set($value, $model): string
    {
        return (string) $value;
    }
}

