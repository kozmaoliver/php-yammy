<?php

namespace Yammy;

use Exception;

class Repository
{
    /**
     * Clone a Git repository
     * @throws Exception
     */
    public function clone(string $gitUrl, string $targetDir): void
    {
        if (!preg_match('/^(https?:\/\/|git@)[\w\-\.]+/', $gitUrl)) {
            throw new Exception("Invalid Git URL format");
        }
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0700, true);
        }

        $escapedUrl = escapeshellarg($gitUrl);
        $escapedDir = escapeshellarg($targetDir);
        
        $cmd = "git clone --depth 1 --single-branch $escapedUrl $escapedDir 2>&1";
        $output = shell_exec($cmd);
        
        if (!file_exists($targetDir . '/yammy.yaml')) {
            throw new Exception("Failed to clone repo or yammy.yaml not found: $output");
        }
        
        $this->recursiveRemove($targetDir . '/.git');
        
        echo "Downloaded from $gitUrl\n";
    }

    /**
     * Recursively remove a directory and its contents
     */
    public function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->recursiveRemove($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Check if a directory exists
     */
    public function exists(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Create directory with permissions
     */
    public function createDir(string $path, int $permissions = 0700): void
    {
        if (!is_dir($path)) {
            mkdir($path, $permissions, true);
        }
    }
}
