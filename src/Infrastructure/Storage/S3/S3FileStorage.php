<?php

namespace Cuatroochenta\FileUpload\Infrastructure\Storage\S3;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Cuatroochenta\FileUpload\Domain\Model\Storage\FileStorage;
use Cuatroochenta\FileUpload\Domain\Model\VO\FileKey;
use Cuatroochenta\FileUpload\Domain\Model\VO\FileName;
use Cuatroochenta\FileUpload\Domain\Model\VO\Prefix;
use Cuatroochenta\FileUpload\Infrastructure\Exception\DeleteFileException;
use Cuatroochenta\FileUpload\Infrastructure\Exception\FileNotFoundException;
use Cuatroochenta\FileUpload\Infrastructure\Exception\GetFileKeyException;
use Cuatroochenta\FileUpload\Infrastructure\Exception\GetUrlException;
use Cuatroochenta\FileUpload\Infrastructure\Exception\ListFilesException;
use Cuatroochenta\FileUpload\Infrastructure\Exception\UploadFileException;
use Cuatroochenta\FileUpload\Infrastructure\Exception\UploadUrlException;
use Exception;
use Safe\Exceptions\FilesystemException;
use function Safe\fopen;
use function Safe\fclose;

class S3FileStorage implements FileStorage
{
    private S3Client $s3Client;

    /** @param array<string, string> $config */
    public function __construct(
        private readonly S3ClientFactory $s3ClientFactory,
        private readonly array $config,
    ) {
        $this->s3Client = $this->s3ClientFactory->getInstance($this->config);
    }

    /* @throws UploadUrlException */
    public function generatePresignedUploadFileUrl(
        FileName $fileName,
        ?Prefix $prefix = null
    ): string {
        $fileKey = $prefix ? $prefix . '/' . $fileName : $fileName;
        try {
            $command = $this->s3Client->getCommand('PutObject', [
                'Bucket' => $this->config['bucket'],
                'Key' => (string)$fileKey,
            ]);
            $request = $this->s3Client->createPresignedRequest($command, '+60 minutes');

            return (string)$request->getUri();
        } catch (S3Exception) {
            throw new UploadUrlException();
        }
    }

    /* @throws GetUrlException */
    public function generatePresignedGetFileUrl(FileKey $fileKey): string
    {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->config['bucket'],
                'Key' => (string)$fileKey,
            ]);
            $request = $this->s3Client->createPresignedRequest($command, '+60 minutes');

            return (string)$request->getUri();
        } catch (S3Exception) {
            throw new GetUrlException();
        }
    }

    /* @throws UploadFileException
     * @throws FilesystemException
     */
    public function uploadFile(
        string $filePath,
        FileName $fileName,
        ?Prefix $prefix = null
    ): string {
        $fileKey = $prefix ? $prefix . '/' . $fileName : $fileName;
        $fileStream = fopen($filePath, 'rb');

        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => (string)$fileKey,
                'Body' => $fileStream,
            ]);

            return $result['ObjectURL'] ?? throw new UploadFileException();
        } catch (S3Exception) {
            throw new UploadFileException();
        } finally {
            fclose($fileStream);
        }
    }

    /* @throws DeleteFileException | FileNotFoundException */
    public function deleteFile(FileKey $fileKey): bool
    {
        try {
            $this->s3Client->headObject([ //check if exists
                'Bucket' => $this->config['bucket'],
                'Key' => (string)$fileKey,
            ]);

            $this->s3Client->deleteObject([
                'Bucket' => $this->config['bucket'],
                'Key' => (string)$fileKey,
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() == 'NotFound') {
                throw new FileNotFoundException();
            } else {
                throw new DeleteFileException();
            }
        }
    }

    public function deleteFilesDirectory(Prefix $prefix): bool
    {
        try {
            $continuationToken = null;
            $deletedAny = false;
            $params = [
                'Bucket' => $this->config['bucket'],
                'Prefix' => (string)$prefix,
                'MaxKeys' => 1000
            ];

            do {
                if ($continuationToken !== null) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $this->s3Client->listObjectsV2($params);

                $contents = $result['Contents'] ?? [];

                if (!empty($contents)) {
                    $toDelete = array_map(function ($obj) {
                        return ['Key' => $obj['Key']];
                    }, $contents);

                    $this->s3Client->deleteObjects([
                        'Bucket' => $this->config['bucket'],
                        'Delete' => [
                            'Objects' => $toDelete,
                        ],
                    ]);

                    $deletedAny = true;
                }

                $continuationToken = ($result['IsTruncated'] ?? false)
                    ? ($result['NextContinuationToken'] ?? null)
                    : null;

            } while ($continuationToken !== null);

            return $deletedAny;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() == 'NotFound') {
                throw new FileNotFoundException();
            } else {
                throw new DeleteFileException();
            }
        }
    }

    /**
     * @throws ListFilesException
     * @return array<string, mixed>
     */
    public function listFiles(?Prefix $prefix = null): array
    {
        try {
            $files = [];
            $continuationToken = null;

            $params = [
                'Bucket' => $this->config['bucket'],
                'Prefix' => (string)$prefix
            ];

            do {
                if ($continuationToken !== null) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $this->s3Client->listObjectsV2($params);

                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $file) {
                        $files[] = [
                            'Key' => $file['Key'],
                            'Size' => $file['Size']
                        ];
                    }
                }

                $continuationToken = ($result['IsTruncated'] ?? false)
                    ? ($result['NextContinuationToken'] ?? null)
                    : null;

            } while ($continuationToken !== null);

            return $files;
        } catch (S3Exception) {
            throw new ListFilesException();
        }
    }

    /**
     * @param array<string, mixed> $webhookData
     * @throws GetFileKeyException
     */
    public function getFileKeyFromWebhookData(array $webhookData): string
    {
        try {
            $key = $webhookData['Records'][0]['s3']['object']['key'];

            if (!isset($key)) {
                throw new GetFileKeyException();
            }

            return $key;
        } catch (Exception) {
            throw new GetFileKeyException();
        }
    }
}
