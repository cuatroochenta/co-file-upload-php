<?php

namespace Cuatroochenta\FileUpload\Infrastructure\Storage\S3;

use Aws\S3\S3Client;

class S3ClientFactory
{
    private ?S3Client $s3Client = null;

    /** @param array<string, string> $config */
    public function getInstance(array $config): S3Client
    {
        if ($this->s3Client === null) {
            $this->s3Client = new S3Client(
                [
                    'credentials' => [
                        'key' => $config['accessKeyId'],
                        'secret' => $config['accessKeySecret']
                    ],
                    'region' => $config['region'],
                    'version' => 'latest',
                ]
            );
        }

        return $this->s3Client;
    }
}
