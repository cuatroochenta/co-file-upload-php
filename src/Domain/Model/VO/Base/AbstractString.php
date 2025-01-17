<?php

namespace Cuatroochenta\FileUpload\Domain\Model\VO\Base;

use JsonSerializable;
use Stringable;

abstract class AbstractString implements Stringable, JsonSerializable
{
    public function __construct(
        protected string $value
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
