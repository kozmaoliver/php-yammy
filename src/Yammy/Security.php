<?php

namespace Yammy;

class Security
{
    private string $quarantinePath;
    private array $securityLog = [];
    private string $logFile;

    public function __construct(string $repoPath, string $logFile)
    {
        $this->quarantinePath = $repoPath . '/.quarantine';
        $this->logFile = $logFile;
        
        // Ensure quarantine directory exists with restricted permissions
        if (!is_dir($this->quarantinePath)) {
            mkdir($this->quarantinePath, 0700, true);
        }
    }

    public function getQuarantinePath(): string
    {
        return $this->quarantinePath;
    }

    public function generateQuarantineDir(string $packageName): string
    {
        return $this->quarantinePath . '/' . basename($packageName) . '_' . time();
    }

    public function logEvent(string $type, string $package, string $version, string $details): void
    {
        $this->securityLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'package' => $package,
            'version' => $version,
            'details' => $details
        ];
    }

    public function saveLog(): void
    {
        if (empty($this->securityLog)) {
            return;
        }
        
        $logContent = '';
        foreach ($this->securityLog as $event) {
            $logContent .= sprintf(
                "[%s] %s - %s@%s: %s\n",
                $event['timestamp'],
                $event['type'],
                $event['package'],
                $event['version'],
                $event['details']
            );
        }
        
        file_put_contents($this->logFile, $logContent, FILE_APPEND);
    }

    public function cleanQuarantine(): void
    {
        if (!is_dir($this->quarantinePath)) {
            echo "Quarantine directory does not exist\n";
            return;
        }
        
        $items = scandir($this->quarantinePath);
        $count = 0;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $this->quarantinePath . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRemove($path);
                $count++;
                echo "Removed: $item\n";
            }
        }
        
        if ($count === 0) {
            echo "Quarantine is already clean\n";
        } else {
            echo "Cleaned $count quarantined package(s)\n";
        }
    }

    private function recursiveRemove(string $dir): void
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
}
