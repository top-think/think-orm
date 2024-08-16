<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\contracts\FieldTypeTransform;

class TestFieldPhpDTO implements FieldTypeTransform
{
    private ?int $id = null;

    public function __construct(
        public int $num1,
        public string $str1
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public static function fromData(string $data): static
    {
        return unserialize($data);
    }

    public function __serialize(): array
    {
        return ['num1' => $this->num1, 'str1' => $this->str1];
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

    public static function get(mixed $value, Model $model): static
    {
        $d = static::fromData($value);
        $d->id = $model->getData('id');
        return $d;
    }

    public static function set($value, $model): string
    {
        return (string) $value;
    }
}

