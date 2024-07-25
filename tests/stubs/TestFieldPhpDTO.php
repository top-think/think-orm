<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\contracts\FieldTypeTransform;

class TestFieldPhpDTO implements FieldTypeTransform
{
    public function __construct(
        public int $num1,
        public string $str1
    )
    {
    }

    public static function fromData(string $data): static
    {
        return unserialize($data);
    }

    public function __serialize(): array
    {
        return get_object_vars($this);
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function __toString(): string
    {
        return serialize($this);
    }

    public static function modelReadValue(mixed $value, Model $model): static
    {
        return static::fromData($value);
    }

    public static function modelWriteValue($value, $model): string
    {
        return (string) $value;
    }
}

