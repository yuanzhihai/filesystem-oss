<?php

namespace yzh52521\Flysystem\Oss;


use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use OSS\Core\OssException;
use OSS\OssClient;
use League\Flysystem\PathPrefixer;

class OssAdapter implements FilesystemAdapter
{

    /**
     * @var OssClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $bucket;

    protected $endpoint;

    /**
     * @var OssOptions
     */
    protected $options;

    /**
     * @var PathPrefixer
     */
    protected PathPrefixer $prefixer;

    /**
     * @var VisibilityConverter
     */
    protected VisibilityConverter $visibility;

    /**
     * @param OssClient $client
     * @param string $bucket
     * @param string $prefix
     * @param array $options
     */
    public function __construct(array $config = [])
    {
        try {
            $this->bucket     = $config['bucket'];
            $accessId         = $config['access_id'];
            $accessSecret     = $config['access_secret'];
            $endpoint         = $config['endpoint'] ?? 'oss-cn-hangzhou.aliyuncs.com';
            $prefix           = $config['prefix'] ?? '';
            $options          = $config['options'];
            $this->client     = new OssClient(
                $accessId,
                $accessSecret,
                $endpoint,
            );
            $this->endpoint   = $endpoint;
            $this->prefixer   = new PathPrefixer($prefix);
            $this->options    = new OssOptions($options);
            $this->visibility = new VisibilityConverter();
        } catch (OssException $e) {
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->prefixer->prefixPath($path), $this->options->getOptions());
        } catch (OssException $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function directoryExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->prefixer->prefixDirectoryPath($path), $this->options->getOptions());
        } catch (OssException $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->client->putObject($this->bucket, $this->prefixer->prefixPath($path), $contents, $this->options->mergeConfig($config, $this->visibility));
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->client->uploadStream($this->bucket, $this->prefixer->prefixPath($path), $contents, $this->options->mergeConfig($config, $this->visibility));
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): string
    {
        try {
            return $this->client->getObject($this->bucket, $this->prefixer->prefixPath($path), $this->options->getOptions());
        } catch (OssException $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readStream(string $path)
    {
        $stream = fopen("php://temp", "w+b");

        try {
            $options = array_merge($this->options->getOptions(), [OssClient::OSS_FILE_DOWNLOAD => $stream]);
            $this->client->getObject($this->bucket, $this->prefixer->prefixPath($path), $options);
        } catch (OssException $exception) {
            fclose($stream);
            throw UnableToReadFile::fromLocation($path, $exception->getErrorCode(), $exception);
        }

        rewind($stream);
        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): void
    {
        try {
            $this->client->deleteObject($this->bucket, $this->prefixer->prefixPath($path), $this->options->getOptions());
        } catch (OssException $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $path): void
    {
        $directory = $this->prefixer->prefixDirectoryPath($path);
        $options   = array_merge(
            $this->options->getOptions(),
            [
                OssClient::OSS_MARKER => '',
                OssClient::OSS_PREFIX => $directory
            ]
        );

        try {
            $bool = true;
            while ($bool) {
                $result  = $this->client->listObjects($this->bucket, $options);
                $objects = array();
                if (count($result->getObjectList()) > 0) {
                    foreach ($result->getObjectList() as $info) {
                        $objects[] = $info->getKey();
                    }
                    $this->client->deleteObjects($this->bucket, $objects);
                }
                if ($result->getIsTruncated() === 'true') {
                    $option[OssClient::OSS_MARKER] = $result->getNextMarker();
                } else {
                    $bool = false;
                }
            }
        } catch (OssException $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->createObjectDir($this->bucket, $this->prefixer->prefixDirectoryPath($path), $this->options->mergeConfig($config, $this->visibility));
        } catch (OssException $exception) {
            throw UnableToCreateDirectory::dueToFailure($path, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->client->putObjectAcl($this->bucket, $this->prefixer->prefixPath($path), $this->visibility->visibilityToAcl($visibility), $this->options->getOptions());
        } catch (OssException $exception) {
            throw UnableToSetVisibility::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            $acl = $this->client->getObjectAcl($this->bucket, $this->prefixer->prefixPath($path), $this->options->getOptions());
        } catch (OssException $exception) {
            throw UnableToRetrieveMetadata::visibility($path, $exception->getErrorCode(), $exception);
        }

        return new FileAttributes($path, null, $this->visibility->aclToVisibility($acl));
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $directory = $this->prefixer->prefixDirectoryPath($path);

        $nextMarker = '';
        while (true) {
            $options = array_merge(
                $this->options->getOptions(),
                [
                    OssClient::OSS_MARKER => $nextMarker,
                    OssClient::OSS_PREFIX => $directory
                ]
            );
            try {
                $listObjectInfo = $this->client->listObjects($this->bucket, $options);
            } catch (OssException $exception) {
                throw new \yzh52521\Flysystem\Oss\OssException("", 0, $exception);
            }
            $nextMarker = $listObjectInfo->getNextMarker();

            $listObject = $listObjectInfo->getObjectList();
            if (!empty($listObject)) {
                foreach ($listObject as $objectInfo) {
                    $objectPath         = $this->prefixer->stripPrefix($objectInfo->getKey());
                    $objectLastModified = strtotime($objectInfo->getLastModified());
                    if (substr($objectPath, 0, -1) == '/') {
                        yield new DirectoryAttributes($objectPath);
                    } else {
                        yield new FileAttributes($objectPath, $objectInfo->getSize(), null, $objectLastModified);
                    }
                }
            }

            if ($deep == true) {
                $prefixList = $listObjectInfo->getPrefixList();
                foreach ($prefixList as $prefixInfo) {
                    $subPath = $this->prefixer->stripDirectoryPrefix($prefixInfo->getPrefix());
                    if ($subPath == $path) {
                        break;
                    }
                    $contents = $this->listContents($subPath, true);
                    foreach ($contents as $content) {
                        yield $content;
                    }
                }
            }

            if ($listObjectInfo->getIsTruncated() !== "true") {
                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    /**
     * Get resource url.
     *
     * @param string $path
     *
     * @return string
     */
    public function getUrl(string $path): string
    {
        return 'https://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($path, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->client->copyObject($this->bucket, $this->prefixer->prefixPath($source), $this->bucket, $this->prefixer->prefixPath($destination), $this->options->getOptions());
        } catch (OssException $exception) {
            UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * @param string $path
     * @return FileAttributes
     */
    protected function metadata(string $path): FileAttributes
    {
        try {
            $result = $this->client->getObjectMeta($this->bucket, $this->prefixer->prefixPath($path), $this->options->getOptions());
        } catch (OssException $exception) {
            UnableToRetrieveMetadata::create($path, "metadata", $exception->getErrorCode(), $exception);
        }

        $size      = isset($result["content-length"]) ? intval($result["content-length"]) : 0;
        $timestamp = isset($result["last-modified"]) ? strtotime($result["last-modified"]) : 0;
        $mimetype  = $result["content-type"] ?? "";
        return new FileAttributes($path, $size, null, $timestamp, $mimetype);
    }

}
