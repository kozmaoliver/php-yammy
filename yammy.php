<?php

use Integrity\Hash;

require_once 'Integrity/Hash.php';

class Yammy
{
    private string $repoPath;
    private array $installed = [];
    private array $projectPackages = [];

    private Hash $hasher;

    public function __construct($repoPath, $projectPackages)
    {
        $this->hasher = new Hash();
        $this->repoPath = $repoPath;
        $this->projectPackages = $projectPackages;
    }

    /**
     * @throws Exception
     */
    public function readManifest($file)
    {
        if (!file_exists($file)) {
            throw new Exception("Manifest file not found: $file");
        }
        $data = yaml_parse_file($file);
        if ($data === false) {
            throw new Exception("Failed to parse YAML file: $file");
        }
        return $data;
    }

    public function installPackage($packageName, $version): void
    {
        $key = "$packageName:$version";
        if (isset($this->installed[$key])) {
            return;
        }

        $manifest = null;
        $packageDir = __DIR__ . "/yammies/$packageName"; //TODO: use $this repo path
        if (isset($this->projectPackages[$packageName]['src'])) {
            $src = $this->projectPackages[$packageName]['src'];
            try {
                // if hash clone in a quarantine first then check for integrity and copy
                $this->cloneRepo($src, $packageDir);
                $manifest = $this->readManifest($packageDir . '/yammy.yaml');
                echo "✅ Loaded manifest for $packageName ($version)\n";

                if (isset($this->projectPackages[$packageName]['hash'])) {
                    $actualHash = $this->hasher->generatePackageDataHash($packageDir);

                    if ($actualHash === $this->projectPackages[$packageName]['hash']) {
                        echo "✅ $packageName integrity OK\n";
                    } else {
                        throw new Exception(sprintf("Failed to match integrity hash for project %s (%s). Expected: %s, got: %s",
                            $packageName,
                            $version,
                            $this->projectPackages[$packageName]['hash'],
                            $actualHash,
                        ));
                    }
                }

            } catch (Exception $e) {
                echo "❌ " . $e->getMessage() . "\n";
                return;
            }
        } else {
            $packageFile = "{$this->repoPath}/{$packageName}/{$version}/yammy.yaml";
            if (!file_exists($packageFile)) {
                echo "❌ Package $packageName ($version) not found.\n";
                return;
            }
            $manifest = $this->readManifest($packageFile);
            echo "✅ Installed {$manifest['name']} ({$manifest['version']}) from local repo\n";
        }

        $this->installed[$key] = true;

        // Recursively install dependencies
        if (isset($manifest['require'])) {
            foreach ($manifest['require'] as $dep => $depVersion) {
                $this->installPackage($dep, $depVersion);
            }
        }
    }

    public function isPackageInstalled($packageName, $version): bool
    {
        $key = "$packageName:$version";
        if (isset($this->installed[$key])) {
            return true;
        }
        $packageFile = "{$this->repoPath}/{$packageName}/{$version}/yammy.yaml";
        return file_exists($packageFile);
    }

    public function installPackages($projectManifest): void
    {
        if (isset($projectManifest['require'])) {
            foreach ($projectManifest['require'] as $pkg => $ver) {
                if (!$this->isPackageInstalled($pkg, $ver)) {
                    $this->installPackage($pkg, $ver);
                } else {
                    echo "$pkg ($ver) is already installed.\n";
                }
            }
            $this->saveLockFile();
        }
    }

    public function cloneRepo($gitUrl, $packageDir): void
    {
        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0777, true);
        }

        $cmd = "git clone --depth 1 $gitUrl $packageDir";
        $output = shell_exec($cmd . " 2>&1");
        if (!file_exists($packageDir . '/yammy.yaml')) {
            throw new Exception("Failed to clone repo or yammy.yaml not found: $output");
        }
        echo "✅ Cloned package from $gitUrl to $packageDir\n";
    }

    public function saveLockFile(): void
    {
        $lockData = [];
        foreach ($this->installed as $key => $value) {
            [$packageName, $version] = explode(':', $key);
            $lockData['packages'][$packageName] = [
                'version' => $version,
            ];
        }
        $lockFile = __DIR__ . '/yammy.lock';
        file_put_contents($lockFile, yaml_emit($lockData));
        echo "Saved lock file: yammy.lock\n";
    }

    public function generatePackageHash(string $dir = __DIR__): void
    {
        echo $this->hasher->generatePackageDataHash($dir);
    }

    public function checkIntegrity($projectManifest): void
    {
        $packages = $projectManifest['packages'] ?? [];
        foreach ($packages as $packageName => $packageData) {
            if (!isset($packageData['hash'])) {
                continue;
            }

            $expectedHash = $packageData['hash'];
            $packageHash = $this->hasher->generatePackageDataHash(__DIR__ . "/yammies/$packageName");
            if ($packageHash === $expectedHash) {
                echo "✅ $packageName integrity OK\n";
            } else {
                echo "❌ $packageName integrity FAIL: expected $expectedHash, got $packageHash\n";
            }
        }
    }
}

$repoPath = __DIR__ . '/yammies';

try {
    $projectManifest = yaml_parse_file(__DIR__ . '/yammy.yaml');
    if ($projectManifest === false) {
        throw new Exception("Failed to parse yammy.yaml");
    }
    $projectPackages = $projectManifest['packages'] ?? [];
    $yammy = new Yammy($repoPath, $projectPackages);
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}

if ($argc > 1 && $argv[1] === 'install') {
    $yammy->installPackages($projectManifest);
} elseif ($argv[1] === 'generate-hash') {
    if (isset($argv[2])) {
        $yammy->generatePackageHash($argv[2]);
    } else {
        $yammy->generatePackageHash();
    }
} elseif ($argc > 1 && $argv[1] === 'check-integrity') {
    $yammy->checkIntegrity($projectManifest);
} else {
    echo "Usage: php yammy.php install | generate-hash | check-integrity\n";
}