<?php

namespace Yammy;

use Exception;

class PackageManager
{
    private string $repoPath;
    private array $installed = [];
    private array $projectPackages = [];
    private Hash $hasher;
    private Manifest $manifest;
    private Security $security;
    private Repository $repository;
    private string $lockFile;

    public function __construct(
        string $repoPath,
        array $projectPackages,
        string $lockFile,
        string $securityLogFile
    ) {
        $this->hasher = new Hash();
        $this->manifest = new Manifest();
        $this->security = new Security($repoPath, $securityLogFile);
        $this->repository = new Repository();
        
        $this->repoPath = $repoPath;
        $this->projectPackages = $projectPackages;
        $this->lockFile = $lockFile;
    }

    public function installPackage(string $packageName, string $version): void
    {
        $key = "$packageName:$version";
        if (isset($this->installed[$key])) {
            echo "$packageName ($version) already processed in this session.\n";
            return;
        }

        $packageDir = $this->repoPath . "/$packageName";
        
        if (isset($this->projectPackages[$packageName]['src'])) {
            $src = $this->projectPackages[$packageName]['src'];
            $expectedHash = $this->projectPackages[$packageName]['hash'] ?? null;
            
            try {
                $quarantineDir = $this->security->generateQuarantineDir($packageName);
                echo "Downloading $packageName to quarantine...\n";
                
                $this->repository->clone($src, $quarantineDir);
                
                $manifestData = $this->manifest->read($quarantineDir . '/yammy.yaml');
                
                if ($manifestData['name'] !== $packageName) {
                    throw new Exception("Package name mismatch: manifest says '{$manifestData['name']}', expected '$packageName'");
                }
                
                $actualHash = $this->hasher->generatePackageDataHash($quarantineDir);
                
                if ($expectedHash) {
                    echo "Verifying package integrity...\n";
                    
                    if ($actualHash !== $expectedHash) {
                        // SECURITY: Keep in quarantine for analysis
                        $this->security->logEvent(
                            'HASH_MISMATCH',
                            $packageName,
                            $version,
                            "Expected: $expectedHash, Got: $actualHash"
                        );
                        
                        throw new Exception(sprintf(
                            "SECURITY: Hash mismatch for %s (%s)\n" .
                            "   Expected: %s\n" .
                            "   Got:      %s\n" .
                            "   Package kept in quarantine: %s\n" .
                            "   DO NOT USE THIS PACKAGE - it may be compromised!",
                            $packageName,
                            $version,
                            $expectedHash,
                            $actualHash,
                            $quarantineDir
                        ));
                    }
                    
                    echo "Hash verification passed\n";
                } else {
                    $this->handleMissingHash($packageName, $quarantineDir, $actualHash);
                }
                
                if ($this->repository->exists($packageDir)) {
                    $this->repository->recursiveRemove($packageDir);
                }

                $this->repository->createDir($packageDir);
                
                if (!rename($quarantineDir, $packageDir)) {
                    throw new Exception("Failed to move package from quarantine to production");
                }
                
                echo "Installed {$manifestData['name']} ($version) from $src\n";
                
                $this->security->logEvent('INSTALL_SUCCESS', $packageName, $version, "Hash: $actualHash");

            } catch (Exception $e) {
                echo $e->getMessage() . "\n";
                
                if (is_dir($quarantineDir)) {
                    echo "Quarantine directory preserved for inspection: $quarantineDir\n";
                }
                
                return;
            }
        } else {
            $packageFile = "{$this->repoPath}/{$packageName}/{$version}/yammy.yaml";
            if (!file_exists($packageFile)) {
                echo "Package $packageName ($version) not found in local repository.\n";
                return;
            }
            $manifestData = $this->manifest->read($packageFile);
            echo "Loaded {$manifestData['name']} ({$manifestData['version']}) from local repo\n";
        }

        $this->installed[$key] = true;

        if (isset($manifestData['require'])) {
            echo "Installing dependencies for $packageName...\n";
            foreach ($manifestData['require'] as $dep => $depVersion) {
                $this->installPackage($dep, $depVersion);
            }
        }
    }

    private function handleMissingHash(string $packageName, string $quarantineDir, string $actualHash): void
    {
        echo "WARNING: No hash specified for $packageName - cannot verify integrity!\n";
        echo "   Generate hash with: yammy generate-hash $quarantineDir\n";
        echo "   Computed hash: $actualHash\n";
        echo "   Add this to your yammy.yaml:\n";
        echo "   packages:\n";
        echo "     $packageName:\n";
        echo "       hash: \"$actualHash\"\n\n";
        
        if (php_sapi_name() === 'cli' && !getenv('YAMMY_AUTO_APPROVE')) {
            echo "   Continue without hash verification? [y/N]: ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 'y') {
                throw new Exception("Installation cancelled by user");
            }
        }
    }

    public function isPackageInstalled(string $packageName, string $version): bool
    {
        $key = "$packageName:$version";
        if (isset($this->installed[$key])) {
            return true;
        }
        $packageDir = $this->repoPath . "/$packageName";
        return $this->repository->exists($packageDir) && file_exists($packageDir . '/yammy.yaml');
    }

    public function installPackages(array $projectManifest): void
    {
        if (isset($projectManifest['require'])) {
            echo "Starting package installation...\n\n";
            foreach ($projectManifest['require'] as $pkg => $ver) {
                if (!$this->isPackageInstalled($pkg, $ver)) {
                    $this->installPackage($pkg, $ver);
                } else {
                    echo "$pkg ($ver) is already installed.\n";
                }
            }
            $this->saveLockFile();
            $this->security->saveLog();
            echo "\nInstallation complete!\n";
        } else {
            echo "No packages to install (no 'require' section in yammy.yaml)\n";
        }
    }

    public function saveLockFile(): void
    {
        $lockData = [
            'generated' => date('Y-m-d H:i:s'),
            'yammy-version' => '1.0.0',
            'packages' => []
        ];
        
        foreach ($this->installed as $key => $value) {
            [$packageName, $version] = explode(':', $key);
            
            $packageDir = $this->repoPath . "/$packageName";
            $hash = '';
            if ($this->repository->exists($packageDir)) {
                $hash = $this->hasher->generatePackageDataHash($packageDir);
            }
            
            $lockData['packages'][$packageName] = [
                'version' => $version,
                'hash' => $hash,
            ];
        }
        
        file_put_contents($this->lockFile, yaml_emit($lockData));
        echo "Lock file saved: yammy.lock\n";
    }

    public function generatePackageHash(string $dir): void
    {
        if (!is_dir($dir)) {
            echo "Directory not found: $dir\n";
            return;
        }
        
        $hash = $this->hasher->generatePackageDataHash($dir);
        echo "$hash\n";
    }

    public function checkIntegrity(array $projectManifest): void
    {
        echo "Checking package integrity...\n\n";
        $packages = $projectManifest['packages'] ?? [];
        $allOk = true;
        
        foreach ($packages as $packageName => $packageData) {
            $packageDir = $this->repoPath . "/$packageName";
            
            if (!$this->repository->exists($packageDir)) {
                echo "$packageName: not installed (skipping)\n";
                continue;
            }
            
            if (!isset($packageData['hash'])) {
                echo "$packageName: no hash specified\n";
                $actualHash = $this->hasher->generatePackageDataHash($packageDir);
                echo "   Current hash: $actualHash\n";
                continue;
            }

            $expectedHash = $packageData['hash'];
            $actualHash = $this->hasher->generatePackageDataHash($packageDir);
            
            if ($actualHash === $expectedHash) {
                echo "$packageName: integrity OK\n";
            } else {
                echo "$packageName: INTEGRITY VIOLATION!\n";
                echo "Expected: $expectedHash\n";
                echo "Got:      $actualHash\n";
                echo "DO NOT USE THIS PACKAGE!\n";
                $allOk = false;
                
                $this->security->logEvent('INTEGRITY_CHECK_FAILED', $packageName, 'unknown', 
                    "Expected: $expectedHash, Got: $actualHash");
            }
        }
        
        echo "\n";
        if ($allOk) {
            echo "All packages passed integrity check\n";
        } else {
            echo "SECURITY WARNING: Some packages failed integrity check!\n";
            echo "Run 'yammy install' to reinstall packages from trusted sources.\n";
            exit(1);
        }
    }

    public function getSecurity(): Security
    {
        return $this->security;
    }
}
