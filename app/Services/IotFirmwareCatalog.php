<?php

namespace App\Services;

class IotFirmwareCatalog
{
    public function release(string $id, int $companyId): ?array
    {
        $release = config('iot_installation.releases', [])[$id] ?? null;
        if (! is_array($release) || (isset($release['company_id']) && (int) $release['company_id'] !== $companyId)) {
            return null;
        }

        return $release;
    }

    public function artifact(string $id, string $format, int $companyId): ?string
    {
        $release = $this->release($id, $companyId);
        if (! $release || ! in_array($format, ['ino', 'bin'], true) || ($release['status'] ?? '') !== 'published'
            || ($release['approved'] ?? false) !== true || ($release['secrets_reviewed'] ?? false) !== true
            || empty($release['reviewed_by']) || empty($release['approved_at']) || empty($release['version']) || empty($release['date']) || empty($release['instructions'])
            || ($release['model'] ?? '') !== 'esp32_devkit_v1') {
            return null;
        }
        $artifact = $release['artifacts'][$format] ?? [];
        $filename = $artifact['filename'] ?? '';
        if (! is_string($filename) || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]*\.'.$format.'\z/', $filename)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $artifact['sha256'] ?? '')) {
            return null;
        }
        $root = realpath(config('iot_installation.firmware_root'));
        if (! $root) {
            return null;
        }
        $path = realpath($root.DIRECTORY_SEPARATOR.$filename);
        if (! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path) || ! is_readable($path) || filesize($path) === 0
            || ! hash_equals($artifact['sha256'], hash_file('sha256', $path))) {
            return null;
        }

        return $path;
    }

    public function listing(int $companyId): array
    {
        $result = [];
        foreach (array_keys(config('iot_installation.releases', [])) as $id) {
            $release = $this->release($id, $companyId);
            if (! $release) {
                continue;
            }
            $result[] = array_intersect_key($release, array_flip(['model', 'label', 'version', 'date', 'status', 'instructions'])) + [
                'id' => $id,
                'downloads' => array_values(array_filter(['ino', 'bin'], fn ($format) => $this->artifact($id, $format, $companyId) !== null)),
            ];
        }

        return $result;
    }
}
