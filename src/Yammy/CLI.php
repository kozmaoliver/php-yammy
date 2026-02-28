<?php

namespace Yammy;

use Exception;
use Yammy\DTO\ProjectManifestDTO;

class CLI
{
    private PackageManager $packageManager;
    private ProjectManifestDTO $projectManifest;

    public function __construct(PackageManager $packageManager, ProjectManifestDTO $projectManifest)
    {
        $this->packageManager = $packageManager;
        $this->projectManifest = $projectManifest;
    }

    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';

        switch ($command) {
            case 'install':
                $this->packageManager->installPackages($this->projectManifest);
                break;
                
            case 'generate-hash':
                $dir = $argv[2] ?? getcwd();
                $this->packageManager->generatePackageHash($dir);
                break;
                
            case 'check-integrity':
            case 'verify':
                $this->packageManager->checkIntegrity($this->projectManifest);
                break;
                
            case 'clean-quarantine':
                $this->packageManager->getSecurity()->cleanQuarantine();
                break;
                
            case 'help':
            case '--help':
            case '-h':
                $this->showHelp();
                break;
                
            default:
                echo "Unknown command: $command\n";
                echo "Run 'yammy help' for usage information\n";
                exit(1);
        }
    }

    private function showHelp(): void
    {
        echo <<<HELP
Usage:
  yammy <command> [options]

Commands:
  install              Install packages from yammy.yaml
  check-integrity      Verify integrity of installed packages
  verify               Alias for check-integrity
  generate-hash <dir>  Generate hash for a package directory
  clean-quarantine     Remove all packages from quarantine
  help                 Show this help message

Security Features:
  ✓ Quarantine system - packages verified before installation
  ✓ Hash verification - detects tampering
  ✓ Security logging - audit trail of all operations
  ✓ Input validation - prevents injection attacks
  ✓ Safe defaults - verification required

Examples:
  yammy install
  yammy check-integrity
  yammy generate-hash ./yammies/my-package

HELP;
    }

    public static function ensureCLI(): void
    {
        if (php_sapi_name() !== 'cli') {
            die("Yammy must be run from command line\n");
        }
    }

    public static function loadProjectManifest(string $manifestFile): ProjectManifestDTO
    {
        if (!file_exists($manifestFile)) {
            throw new Exception("yammy.yaml not found in current directory");
        }
        
        $projectManifest = yaml_parse_file($manifestFile);
        if ($projectManifest === false) {
            throw new Exception("Failed to parse yammy.yaml");
        }

        return new ProjectManifestDTO(...$projectManifest);
    }
}
