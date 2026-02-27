<?php

/**
 * Yammy - Secure PHP Package Manager
 * 
 * Bootstrap file - delegates to src/Yammy classes
 */

use Yammy\CLI;
use Yammy\PackageManager;

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'Yammy\\') === 0) {
        $classPath = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($classPath)) {
            require_once $classPath;
        }
    }
});

CLI::ensureCLI();

try {
    $manifestFile = __DIR__ . '/yammy.yaml';
    $projectManifest = CLI::loadProjectManifest($manifestFile);

    $repoPath = __DIR__ . '/yammies';
    $lockFile = __DIR__ . '/yammy.lock';
    $securityLogFile = __DIR__ . '/yammy-security.log';
    $projectPackages = $projectManifest['packages'] ?? [];
    
    $packageManager = new PackageManager(
        $repoPath,
        $projectPackages,
        $lockFile,
        $securityLogFile
    );

    $cli = new CLI($packageManager, $projectManifest);
    $cli->run($argv);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
