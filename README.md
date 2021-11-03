## Aliyun OSS Adapter For Flysystem.

AliYun OSS Storage adapter for flysystem - a PHP filesystem 2.0 abstraction.

## Installation
composer require yzh52521/flysystem-oss 2.0



## Usage

```php
use League\Flysystem\Filesystem;
use yzh52521\Flysystem\Oss\OssAdapter;

$aliyun = new OssAdapter([
    'accessId'       => '<aliyun access id>',
    'accessSecret'   => '<aliyun access secret>',
    'bucket'         => '<bucket name>',
    'endpoint'       => '<endpoint address>',
    // 'timeout'        => 3600,
    // 'connectTimeout' => 10,
    // 'isCName'        => false,
    // 'token'          => '',
    //'proxy' => null,
]);
$filesystem = new Filesystem($aliyun);


// Write Files
$filesystem->write('path/to/file.txt', 'contents');

// Write Use writeStream
$stream = fopen('local/path/to/file.txt', 'r+');
$result = $filesystem->writeStream('path/to/file.txt', $stream);
if (is_resource($stream)) {
    fclose($stream);
}

// Update Files
$filesystem->update('path/to/file.txt', 'new contents');

// Check if a file exists
$exists = $filesystem->has('path/to/file.txt');

// Read Files
$contents = $filesystem->read('path/to/file.txt');

// Delete Files
$filesystem->delete('path/to/file.txt');

// Copy Files
$filesystem->copy('filename.txt', 'duplicate.txt');


// list the contents (not support recursive now)
$filesystem->listContents('path', false);

string $flysystem->getUrl('file.md'); 
```


