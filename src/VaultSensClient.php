<?php

namespace VaultSens;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

class VaultSensError extends \Exception
{
    public mixed $data;
    public string $errorCode;

    /** Error codes returned by the VaultSens API */
    public const FILE_TOO_LARGE          = 'FILE_TOO_LARGE';
    public const STORAGE_LIMIT           = 'STORAGE_LIMIT';
    public const FILE_COUNT_LIMIT        = 'FILE_COUNT_LIMIT';
    public const MIME_TYPE_NOT_ALLOWED   = 'MIME_TYPE_NOT_ALLOWED';
    public const COMPRESSION_NOT_ALLOWED = 'COMPRESSION_NOT_ALLOWED';
    public const SUBSCRIPTION_INACTIVE   = 'SUBSCRIPTION_INACTIVE';
    public const FOLDER_COUNT_LIMIT      = 'FOLDER_COUNT_LIMIT';
    public const EMAIL_ALREADY_REGISTERED = 'EMAIL_ALREADY_REGISTERED';
    public const EMAIL_NOT_VERIFIED      = 'EMAIL_NOT_VERIFIED';
    public const INVALID_CREDENTIALS     = 'INVALID_CREDENTIALS';
    public const INVALID_OTP             = 'INVALID_OTP';
    public const UNAUTHORIZED            = 'UNAUTHORIZED';
    public const NOT_FOUND               = 'NOT_FOUND';
    public const UNKNOWN                 = 'UNKNOWN';

    public function __construct(string $message, int $code, mixed $data = null)
    {
        parent::__construct($message, $code);
        $this->data = $data;
        $this->errorCode = self::resolveCode($code, $message);
    }

    private static function resolveCode(int $status, string $message): string
    {
        $m = strtolower($message);
        if ($status === 413 && str_contains($m, 'storage limit')) return self::STORAGE_LIMIT;
        if ($status === 413) return self::FILE_TOO_LARGE;
        if ($status === 415) return self::MIME_TYPE_NOT_ALLOWED;
        if ($status === 402) return self::SUBSCRIPTION_INACTIVE;
        if ($status === 403 && str_contains($m, 'compression')) return self::COMPRESSION_NOT_ALLOWED;
        if ($status === 403 && str_contains($m, 'folder')) return self::FOLDER_COUNT_LIMIT;
        if ($status === 403 && (str_contains($m, 'file') || str_contains($m, 'maximum'))) return self::FILE_COUNT_LIMIT;
        if ($status === 403 && str_contains($m, 'email')) return self::EMAIL_NOT_VERIFIED;
        if ($status === 400 && str_contains($m, 'already registered')) return self::EMAIL_ALREADY_REGISTERED;
        if ($status === 400 && (str_contains($m, 'invalid email or password') || str_contains($m, 'invalid credentials'))) return self::INVALID_CREDENTIALS;
        if ($status === 400 && str_contains($m, 'otp')) return self::INVALID_OTP;
        if ($status === 401) return self::UNAUTHORIZED;
        if ($status === 404) return self::NOT_FOUND;
        return self::UNKNOWN;
    }
}

class VaultSensClient
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $apiSecret;
    private Client $client;

    public function __construct(string $baseUrl, ?string $apiKey = null, ?string $apiSecret = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->client = new Client();
    }

    public function setAuth(string $apiKey, string $apiSecret): void
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    private function headers(): array
    {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new VaultSensError('API key and secret are required', 401);
        }

        return [
            'x-api-key' => $this->apiKey,
            'x-api-secret' => $this->apiSecret,
        ];
    }

    private function request(string $method, string $path, array $options = []): array
    {
        $options['headers'] = array_merge($this->headers(), $options['headers'] ?? []);
        try {
            $response = $this->client->request($method, $this->baseUrl . $path, $options);
            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            return $data ?? ['status' => $response->getStatusCode(), 'message' => $body];
        } catch (BadResponseException $e) {
            $body = (string) $e->getResponse()->getBody();
            $payload = json_decode($body, true);
            $message = is_array($payload) ? ($payload['message'] ?? $e->getMessage()) : $e->getMessage();
            $code = $e->getResponse()->getStatusCode();
            throw new VaultSensError($message, $code, $payload);
        } catch (GuzzleException $e) {
            throw new VaultSensError($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function uploadFile(string $filePath, ?string $name = null, ?string $compression = null, ?string $folderId = null): array
    {
        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ];

        if ($name) {
            $multipart[] = ['name' => 'name', 'contents' => $name];
        }
        if ($compression !== null) {
            $multipart[] = ['name' => 'compression', 'contents' => $compression];
        }
        if ($folderId !== null) {
            $multipart[] = ['name' => 'folderId', 'contents' => $folderId];
        }

        return $this->request('POST', '/api/v1/files/upload', ['multipart' => $multipart]);
    }

    public function uploadFiles(array $filePaths, ?string $name = null, ?string $compression = null, ?string $folderId = null): array
    {
        $multipart = [];
        foreach ($filePaths as $path) {
            $multipart[] = [
                'name' => 'files',
                'contents' => fopen($path, 'r'),
                'filename' => basename($path),
            ];
        }

        if ($name) {
            $multipart[] = ['name' => 'name', 'contents' => $name];
        }
        if ($compression !== null) {
            $multipart[] = ['name' => 'compression', 'contents' => $compression];
        }
        if ($folderId !== null) {
            $multipart[] = ['name' => 'folderId', 'contents' => $folderId];
        }

        return $this->request('POST', '/api/v1/files/upload', ['multipart' => $multipart]);
    }

    public function listFiles(?string $folderId = null): array
    {
        $path = $folderId !== null ? '/api/v1/files?folderId=' . urlencode($folderId) : '/api/v1/files';
        return $this->request('GET', $path);
    }

    public function listFolders(): array
    {
        return $this->request('GET', '/api/v1/folders');
    }

    public function createFolder(string $name, ?string $parentId = null): array
    {
        $body = ['name' => $name];
        if ($parentId !== null) {
            $body['parentId'] = $parentId;
        }
        return $this->request('POST', '/api/v1/folders', ['json' => $body]);
    }

    public function renameFolder(string $folderId, string $name): array
    {
        return $this->request('PATCH', "/api/v1/folders/{$folderId}", ['json' => ['name' => $name]]);
    }

    public function deleteFolder(string $folderId): array
    {
        return $this->request('DELETE', "/api/v1/folders/{$folderId}");
    }

    public function getFileMetadata(string $fileId): array
    {
        return $this->request('GET', "/api/v1/files/metadata/{$fileId}");
    }

    public function updateFile(string $fileId, string $filePath, ?string $name = null, ?string $compression = null): array
    {
        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ];

        if ($name) {
            $multipart[] = ['name' => 'name', 'contents' => $name];
        }
        if ($compression !== null) {
            $multipart[] = ['name' => 'compression', 'contents' => $compression];
        }

        return $this->request('PUT', "/api/v1/files/{$fileId}", ['multipart' => $multipart]);
    }

    public function deleteFile(string $fileId): array
    {
        return $this->request('DELETE', "/api/v1/files/{$fileId}");
    }

    public function getMetrics(): array
    {
        return $this->request('GET', '/api/v1/metrics');
    }

    public function buildFileUrl(string $fileId, array $options = []): string
    {
        if (!$options) {
            return $this->baseUrl . "/api/v1/files/{$fileId}";
        }
        $query = http_build_query($options);
        return $this->baseUrl . "/api/v1/files/{$fileId}?{$query}";
    }
}
