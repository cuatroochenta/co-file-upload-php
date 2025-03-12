# **DOCUMENTACIÓN**
La intención de esta librería es proporcionar un servicio de almacenamiento de archivos reutilizable.

## Instalación
`composer require cuatroochenta/fileupload:dev-develop`

## Ejemplo de configuración
Ahora mismo solamente existe la implementación de almacenamiento en S3. Hay que configurar el servicio con las variables de entorno de AWS:
```
AWS_ACCESS_KEY_ID=
AWS_ACCESS_KEY_SECRET=
AWS_REGION=
AWS_BUCKET=
```

<b>Sugerencia de services.yaml:</b>
```
Cuatroochenta\FileUpload\Domain\Model\Storage\FileStorage: '@file_storage'

s3_client_factory:
    class: Cuatroochenta\FileUpload\Infrastructure\Storage\S3\S3ClientFactory

file_storage:
    class: Cuatroochenta\FileUpload\Infrastructure\Storage\S3\S3FileStorage
    arguments:
        $s3ClientFactory: '@s3_client_factory'
        $config:
            accessKeyId: '%env(AWS_ACCESS_KEY_ID)%'
            accessKeySecret: '%env(AWS_ACCESS_KEY_SECRET)%'
            region: '%env(AWS_REGION)%'
            bucket: '%env(AWS_BUCKET)%'
```

## Uso de la librería:
La interfaz `FileStorage` tiene los métodos necesarios para interactuar con el servicio de almacenamiento de archivos.<br>
La diferencia entre los value object `FileKey` y `FileName` es que `FileKey` incluye el path completo al archivo, mientras que `FileName` solo incluye el nombre del archivo.
Es decir, `FileKey` es la combinación de `Prefix` + `FileName`.<br>
Cuando no se pasa un prefijo, se asume que el archivo se encuentra en la raíz.
```
interface FileStorage
{
    /**
     * Generates a presigned URL to upload a file. The URL is valid for 60 minutes.
     *
     * @param FileName $fileName The name of the file for which the presigned URL is generated.
     * @param Prefix|null $prefix An optional prefix to specify a directory path in the storage.
     * @return string The URL of the uploaded file.
     **/
    public function generatePresignedUploadFileUrl(
        FileName $fileName,
        ?Prefix $prefix = null
    ): string;
    
    /**
     * Generates a presigned URL to retrieve a file. The URL is valid for 60 minutes.
     *
     * @param FileKey $fileKey The key of the file for which the presigned URL is generated.
     * @return string The URL to access the file.
     **/
    public function generatePresignedGetFileUrl(
        FileKey $fileKey,
    ): string;
    
    /**
     * Uploads a file to the storage.
     *
     * @param string $filePath The path to the file to be uploaded.
     * @param FileName $fileName The name of the file.
     * @param Prefix|null $prefix An optional prefix to specify a directory path in the storage.
     * @return string The URL of the uploaded file.
     **/
    public function uploadFile(
        string $filePath,
        FileName $fileName,
        ?Prefix $prefix = null
    ): string;
    
    /**
     * Deletes a file from the storage.
     *
     * @param FileKey $fileKey The key of the file to be deleted.
     * @return bool true if the file was deleted, false otherwise.
     */
    public function deleteFile(
        FileKey $fileKey,
    ): bool;
    
    /**
    * @return string Returns the file key from the webhook data.
    *
    * @param array<string, mixed> $webhookData 
    */
    public function getFileKeyFromWebhookData(
        array $webhookData
    ): string;
    
    /**
     * Returns a list of files in the storage.
     *
     * @param Prefix|null $prefix An optional prefix to specify a directory path in the storage.
     * @return array<array<string, string>> The list of files, where each element is an array containing the key and size of the file.
     */
    /** @return array<array<string, string>> */
    public function listFiles(
        ?Prefix $prefix = null
    ): array;
}

```

<b>Flujo sugerido:</b>
- Usar `generatePresignedUploadFileUrl()` para generar la url de subida y devolverla al frontend. Si se quieren subir archivos pequeños directamente desde backend usar `uploadFile()`. 
- El servicio notificará de la subida del archivo a través del webhook configurado. Usar `getFileKeyFromWebhookData()` para extraer el id de los datos que llegan al webhook y actualizar su estado en BBDD si se desea.
- Usar `generatePresignedGetFileUrl()` para generar la url de acceso al fichero y devolverla al frontend.

## Ejemplo de uso de la generación de URLs para subida de fichero desde frontend:
```
    //Use FileStorage to generate a presigned upload URL
    
    /* var $fileId UUID */
    /* var $prefix string */
    /* var $fileStorage FileStorage */
    try {
        $url = $fileStorage->generatePresignedUploadFileUrl(
            fileName: new FileName($fileId),
            prefix: $prefix ? new Prefix($prefix) : null
        );
    } catch (UploadUrlException | FileNameException) {
        ...
    }
    
    // If necessary, save the file id and status in your database. 
    //In this example, File, FileId, FileStatus and FileRepository are classes in your project.
    
    $file = File::create(
        id: new FileId($fileId),
        status: FileStatus::FILE_PENDING,
    );
    /* var $fileRepository FileRepository */
    $fileRepository->add($file);

    //return file id and url to frontend
    
    return new GenerateFileUrlResponse($fileId, $url);

------------

    class GenerateFileUrlResponse implements JsonSerializable
    {
        public function __construct(
            public string $id,
            public string $url,
        ) {
        }
    
        /**
         * @return array<string, string>
         */
        public function jsonSerialize(): array
        {
            return [
                'id' => $this->id,
                'url' => $this->url,
            ];
        }
    }
    
```