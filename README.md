# vaultsens/sdk

PHP SDK for VaultSens. API key + secret authentication with file upload and management helpers.

## Install

```bash
composer require vaultsens/sdk
```

## Usage

```php
use VaultSens\VaultSensClient;

$client = new VaultSensClient(
    'https://api.vaultsens.com',
    'fs_xxx',
    'sk_xxx'
);

$response = $client->uploadFile('./photo.png', 'marketing-hero', true);
print_r($response);
```

## API

- `uploadFile($path, $name = null, $transform = null)`
- `uploadFiles(array $paths, $name = null, $transform = null)`
- `listFiles()`
- `getFileMetadata($fileId)`
- `updateFile($fileId, $path, $name = null, $transform = null)`
- `deleteFile($fileId)`
- `getMetrics()`
- `buildFileUrl($fileId, array $options = [])`

## Docs

https://vaultsens.com/ (SDK reference + examples)
