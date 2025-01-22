<?php

namespace Cuatroochenta\FileUpload\Domain\Model\VO;

use Cuatroochenta\FileUpload\Domain\Exception\VO\FileNameException;
use Cuatroochenta\FileUpload\Domain\Model\VO\Base\AbstractString;

class FileName extends AbstractString
{
    /**
     * @throws FileNameException
     */
    public function __construct(string $value)
    {
        if (strlen($value) < 1) {
            throw new FileNameException($value);
        }
        parent::__construct($value);
    }
}
