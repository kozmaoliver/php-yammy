<?php

namespace Yammy;

/**
 * Hash - Cryptographic Integrity Verification
 * 
 * Provides fast, collision-resistant hashing for package integrity verification.
 * Uses xxHash64 for performance with strong guarantees.
 * 
 * Security considerations:
 * - xxHash is NOT cryptographically secure (future: add SHA-256 option)
 * - Suitable for detecting accidental corruption and basic tampering
 * - For stronger security, use multi-hash verification (future feature)
 */
class Hash
{
    /**
     * File extensions to include in hash calculation
     * Only code and config files are hashed
     */
    private const HASHED_FILE_EXTENSIONS = [
        'php',
        'phtml',
        'html',
        'js',
        'json',
        'yaml',
        'yml',
        'xml',
        'ini',
        'env',
        'lock',
    ];

    /**
     * Files and directories to exclude from hashing
     * These don't affect package integrity
     */
    private const EXCLUDE_FILES = [
        '.',
        '..',
        '.git',
        '.gitignore',
        '.github',
        '.gitlab-ci.yml',
        'yammy.php',
        'yammy.lock',
        'yammy-security.log',
        'vendor',
        'node_modules',
        '.DS_Store',
        'Thumbs.db',
        '.idea',
        '.vscode',
        '__pycache__',
        '.pytest_cache',
        'composer.lock',
        'package-lock.json',
    ];

    /**
     * Maximum file size to hash (100MB)
     * Protects against DoS via huge files
     */
    private const MAX_FILE_SIZE = 100 * 1024 * 1024;

    /**
     * Get all files in a package directory that should be hashed
     * 
     * @param string $dir Directory to scan
     * @return array List of file paths
     * @throws \Exception On suspicious activity
     */
    private function getPackageFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            throw new \Exception("Directory not found: $dir");
        }

        $files = [];
        $items = @scandir($dir);
        
        if ($items === false) {
            throw new \Exception("Cannot read directory: $dir (permission denied?)");
        }

        foreach ($items as $item) {
            if (in_array($item, self::EXCLUDE_FILES)) {
                continue;
            }

            if (strpos($item, '.') === 0 && $item !== '.env') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_link($path)) {
                error_log("WARNING: Symlink detected and skipped: $path");
                continue;
            }

            if (is_dir($path)) {
                try {
                    $files = array_merge($files, $this->getPackageFiles($path));
                } catch (\Exception $e) {
                    error_log("WARNING: Cannot scan directory $path: " . $e->getMessage());
                }
                continue;
            }

            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($extension, self::HASHED_FILE_EXTENSIONS)) {
                continue;
            }

            $fileSize = @filesize($path);
            if ($fileSize === false) {
                error_log("WARNING: Cannot get size of $path");
                continue;
            }

            if ($fileSize > self::MAX_FILE_SIZE) {
                error_log("WARNING: File too large, skipping: $path (" . 
                    round($fileSize / 1024 / 1024, 2) . "MB)");
                continue;
            }

            if (!is_readable($path)) {
                error_log("WARNING: File not readable, skipping: $path");
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * Generate integrity hash for a package directory
     * 
     * This is the main security function. It computes a hash over all
     * relevant files in a package to detect tampering or corruption.
     * 
     * @param string $packageDirectory Package root directory
     * @return string Uppercase hex hash
     * @throws \Exception On errors
     */
    public function generatePackageDataHash(string $packageDirectory): string
    {
        if (!is_dir($packageDirectory)) {
            throw new \Exception("Package directory does not exist: $packageDirectory");
        }

        $files = $this->getPackageFiles($packageDirectory);
        
        if (empty($files)) {
            throw new \Exception("No hashable files found in package directory: $packageDirectory");
        }

        // Use xxHash64 for speed (todo: support multiple algorithms)
        $context = hash_init('xxh64');
        $fileCount = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            $relativePath = str_replace($packageDirectory . DIRECTORY_SEPARATOR, '', $file);
            hash_update($context, $relativePath);
            
            $success = hash_update_file($context, $file);
            
            if (!$success) {
                throw new \Exception("Failed to hash file: $file");
            }

            $fileCount++;
            $totalSize += filesize($file);
        }

        $hash = strtoupper(hash_final($context));

        if (getenv('YAMMY_DEBUG')) {
            error_log(sprintf(
                "Hash computed: %s (files: %d, size: %s)",
                $hash,
                $fileCount,
                $this->formatBytes($totalSize)
            ));
        }

        return $hash;
    }

    /**
     * Generate unique hash for package identity (name + version)
     *
     * Used for internal tracking, not security verification
     *
     * @param string $packageName Package name (e.g., "vendor/package")
     * @param string $packageVersion Version (e.g., "1.2.3")
     * @return string Uppercase hex hash
     */
    public function generatePackageIdHash(string $packageName, string $packageVersion): string
    {
        if (empty($packageName) || empty($packageVersion)) {
            throw new \Exception("Package name and version cannot be empty");
        }

        $context = hash_init('xxh64');
        hash_update($context, $packageName);
        hash_update($context, $packageVersion);
        return strtoupper(hash_final($context));
    }

    /**
     * Verify package integrity against expected hash
     * 
     * Convenience method that combines generation and comparison
     * 
     * @param string $packageDirectory Package directory
     * @param string $expectedHash Expected hash value
     * @return bool True if hashes match
     */
    public function verifyPackageIntegrity(string $packageDirectory, string $expectedHash): bool
    {
        try {
            $actualHash = $this->generatePackageDataHash($packageDirectory);
            return $actualHash === strtoupper($expectedHash);
        } catch (\Exception $e) {
            error_log("Hash verification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of hashed file extensions
     * 
     * @return array File extensions
     */
    public function getHashedExtensions(): array
    {
        return self::HASHED_FILE_EXTENSIONS;
    }

    /**
     * Get list of excluded files/directories
     * 
     * @return array Excluded items
     */
    public function getExcludedFiles(): array
    {
        return self::EXCLUDE_FILES;
    }

    /**
     * Format bytes into human-readable string
     * 
     * @param int $bytes Byte count
     * @return string Formatted string (e.g., "1.5 MB")
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get detailed file list with metadata (for debugging)
     * 
     * @param string $packageDirectory Package directory
     * @return array File info [path, size, modified]
     */
    public function getPackageFileInfo(string $packageDirectory): array
    {
        $files = $this->getPackageFiles($packageDirectory);
        $info = [];
        
        foreach ($files as $file) {
            $relativePath = str_replace($packageDirectory . DIRECTORY_SEPARATOR, '', $file);
            $info[] = [
                'path' => $relativePath,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'hash' => hash_file('xxh64', $file),
            ];
        }
        
        return $info;
    }

    /**
     * Compare two package directories for differences
     * 
     * Useful for debugging hash mismatches
     * 
     * @param string $dir1 First directory
     * @param string $dir2 Second directory
     * @return array Differences found
     */
    public function comparePackages(string $dir1, string $dir2): array
    {
        $files1 = $this->getPackageFiles($dir1);
        $files2 = $this->getPackageFiles($dir2);
        
        $relFiles1 = array_map(fn($f) => str_replace($dir1 . DIRECTORY_SEPARATOR, '', $f), $files1);
        $relFiles2 = array_map(fn($f) => str_replace($dir2 . DIRECTORY_SEPARATOR, '', $f), $files2);
        
        $differences = [
            'only_in_first' => array_diff($relFiles1, $relFiles2),
            'only_in_second' => array_diff($relFiles2, $relFiles1),
            'modified' => [],
        ];
        
        // Check for modified files
        $commonFiles = array_intersect($relFiles1, $relFiles2);
        foreach ($commonFiles as $file) {
            $hash1 = hash_file('xxh64', $dir1 . DIRECTORY_SEPARATOR . $file);
            $hash2 = hash_file('xxh64', $dir2 . DIRECTORY_SEPARATOR . $file);
            
            if ($hash1 !== $hash2) {
                $differences['modified'][] = $file;
            }
        }
        
        return $differences;
    }
}
