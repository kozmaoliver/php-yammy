<?php

namespace Integrity;

class Hash
{
    private const HASHED_FILE_EXTENSIONS = [
        'php',
        'phtml',
        'html',
        'js',
        'yaml',
    ];

    private const EXCLUDE_FILES = [
        '.',
        '..',
        '.git',
        'yammy.php',
        'yammy.lock',
    ];

    private function getPackageFiles(string $dir): array
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {

            if (in_array($item, self::EXCLUDE_FILES)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            var_dump($path);

            if (is_link($path)) {
                continue;
            } elseif (is_dir($path)) {
                $files = array_merge($files, $this->getPackageFiles($path));
            } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), self::HASHED_FILE_EXTENSIONS)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    public function generatePackageDataHash(string $packageDirectory): string
    {
        $context = hash_init('xxh64');
        foreach ($this->getPackageFiles($packageDirectory) as $file) {
            hash_update_file($context, $file);
        }
        return strtoupper(hash_final($context));
    }

    public function generatePackageIdHash(string $packageName, string $packageVersion): string
    {
        $context = hash_init('xxh64');
        hash_update($context, $packageName);
        hash_update($context, $packageVersion);
        return strtoupper(hash_final($context));
    }
}
