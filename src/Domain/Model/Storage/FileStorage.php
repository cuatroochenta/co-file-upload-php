<?php

namespace Cuatroochenta\FileUpload\Domain\Model\Storage;

use Cuatroochenta\FileUpload\Domain\Model\VO\FileKey;
use Cuatroochenta\FileUpload\Domain\Model\VO\FileName;
use Cuatroochenta\FileUpload\Domain\Model\VO\Prefix;

interface FileStorage
{
    public function generatePresignedUploadFileUrl(
        FileName $fileName,
        ?Prefix $prefix = null
    ): string;

    public function generatePresignedGetFileUrl(
        FileKey $fileKey,
    ): string;

    public function uploadFile(
        string $filePath,
        FileName $fileName,
        ?Prefix $prefix = null
    ): string;

    public function deleteFile(
        FileKey $fileKey,
    ): bool;

    /** @param array<string, mixed> $webhookData */
    public function getFileKeyFromWebhookData(
        array $webhookData
    ): string;

    /** @return array<array<string, string>> */
    public function listFiles(
        ?Prefix $prefix = null
    ): array;
}
