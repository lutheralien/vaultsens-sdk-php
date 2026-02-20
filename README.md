# vaultsens/sdk

PHP SDK for VaultSens. API key + secret authentication with file upload, folder management, and image transform helpers.

## Install

```bash
composer require vaultsens/sdk
```

## Quick start

```php
use VaultSens\VaultSensClient;

$client = new VaultSensClient(
    'https://api.vaultsens.com',
    'your-api-key',
    'your-api-secret'
);

$result = $client->uploadFile('./photo.png', 'hero', 'low');
echo $result['data']['_id'];  // file ID
echo $result['data']['url'];  // public URL
```

---

## API reference

### `new VaultSensClient(baseUrl, apiKey, apiSecret)`

| Parameter | Type | Description |
|---|---|---|
| `$baseUrl` | `string` | Your VaultSens API base URL |
| `$apiKey` | `string\|null` | API key |
| `$apiSecret` | `string\|null` | API secret |

Use `setAuth($apiKey, $apiSecret)` to set credentials after construction.

---

### Files

#### `uploadFile($filePath, $name = null, $compression = null, $folderId = null)`

Upload a single file.

```php
$result = $client->uploadFile(
    './photo.png',
    'my-image',       // optional display name
    'medium',         // optional: 'none' | 'low' | 'medium' | 'high'
    'folder-id'       // optional folder to place the file in
);
```

#### `uploadFiles(array $filePaths, $name = null, $compression = null, $folderId = null)`

Upload multiple files in one request.

```php
$result = $client->uploadFiles(
    ['./a.png', './b.jpg'],
    null,
    'low',
    'folder-id'
);
```

#### `listFiles($folderId = null)`

List all files. Pass `$folderId` to filter by folder, or `"root"` for files not in any folder.

```php
$all     = $client->listFiles();
$inDir   = $client->listFiles('folder-id');
$atRoot  = $client->listFiles('root');
```

#### `getFileMetadata($fileId)`

```php
$meta = $client->getFileMetadata('file-id');
```

#### `updateFile($fileId, $filePath, $name = null, $compression = null)`

Replace a file's content.

```php
$client->updateFile('file-id', './new-photo.png', null, 'high');
```

#### `deleteFile($fileId)`

```php
$client->deleteFile('file-id');
```

#### `buildFileUrl($fileId, array $options = [])`

Build a URL for dynamic image transforms.

```php
$url = $client->buildFileUrl('file-id', [
    'width'   => 800,
    'height'  => 600,
    'format'  => 'webp',
    'quality' => 80,
]);
```

---

### Folders

#### `listFolders()`

```php
$result  = $client->listFolders();
$folders = $result['data'];
```

#### `createFolder($name, $parentId = null)`

```php
$result   = $client->createFolder('Marketing');
$folderId = $result['data']['_id'];

// nested folder
$client->createFolder('2024', $folderId);
```

#### `renameFolder($folderId, $name)`

```php
$client->renameFolder('folder-id', 'New Name');
```

#### `deleteFolder($folderId)`

Deletes the folder and moves all its files back to root.

```php
$client->deleteFolder('folder-id');
```

---

### Metrics

```php
$result = $client->getMetrics();
$data   = $result['data'];
// $data['totalFiles'], $data['totalStorageBytes'], $data['storageUsedPercent'], ...
```

---

## Error handling

All API errors throw a `VaultSensError` with an `$errorCode`, HTTP status code, and message.

```php
use VaultSens\VaultSensError;

try {
    $client->uploadFile('./photo.png');
} catch (VaultSensError $e) {
    echo $e->getMessage();   // human-readable message
    echo $e->getCode();      // HTTP status code
    echo $e->errorCode;      // machine-readable error code

    switch ($e->errorCode) {
        case VaultSensError::FILE_TOO_LARGE:
            echo 'File exceeds your plan limit';
            break;
        case VaultSensError::STORAGE_LIMIT:
            echo 'Storage quota exceeded';
            break;
        case VaultSensError::MIME_TYPE_NOT_ALLOWED:
            echo 'File type not allowed on your plan';
            break;
        case VaultSensError::COMPRESSION_NOT_ALLOWED:
            echo 'Compression level not permitted on your plan';
            break;
        case VaultSensError::FILE_COUNT_LIMIT:
            echo 'File count limit reached';
            break;
        case VaultSensError::FOLDER_COUNT_LIMIT:
            echo 'Folder count limit reached';
            break;
        case VaultSensError::SUBSCRIPTION_INACTIVE:
            echo 'Subscription is not active';
            break;
    }
}
```

### Error codes

| Constant | Status | Description |
|---|---|---|
| `FILE_TOO_LARGE` | 413 | File exceeds plan's `maxFileSizeBytes` |
| `STORAGE_LIMIT` | 413 | Total storage quota exceeded |
| `FILE_COUNT_LIMIT` | 403 | Plan's `maxFilesCount` reached |
| `MIME_TYPE_NOT_ALLOWED` | 415 | File type blocked by plan |
| `COMPRESSION_NOT_ALLOWED` | 403 | Compression level not permitted by plan |
| `SUBSCRIPTION_INACTIVE` | 402 | User subscription is not active |
| `FOLDER_COUNT_LIMIT` | 403 | Plan's `maxFoldersCount` reached |
| `EMAIL_ALREADY_REGISTERED` | 400 | Duplicate email on register |
| `EMAIL_NOT_VERIFIED` | 403 | Login attempted before verifying email |
| `INVALID_CREDENTIALS` | 400 | Wrong email or password |
| `INVALID_OTP` | 400 | Bad or expired verification code |
| `UNAUTHORIZED` | 401 | Missing or invalid credentials |
| `NOT_FOUND` | 404 | Resource not found |
| `UNKNOWN` | — | Any other error |

---

## License

MIT
