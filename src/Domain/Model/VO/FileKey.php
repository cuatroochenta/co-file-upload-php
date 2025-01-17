<?php

namespace Cuatroochenta\FileUpload\Domain\Model\VO;

use Cuatroochenta\FileUpload\Domain\Exception\VO\FileKeyException;
use Cuatroochenta\FileUpload\Domain\Model\VO\Base\AbstractString;

class FileKey extends AbstractString
{
    /**
     * @throws FileKeyException
     */
    public function __construct(string $value)
    {
        if (strlen($value) < 1) {
            throw new FileKeyException($value);
        }
        parent::__construct($value);
    }
}
