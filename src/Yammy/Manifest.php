<?php

namespace Yammy;

use Exception;

class Manifest
{
    /**
     * Read and parse a YAML manifest file
     * @throws Exception
     */
    public function read(string $file): array
    {
        if (!file_exists($file)) {
            throw new Exception("Manifest file not found: $file");
        }
        
        $data = yaml_parse_file($file);
        if ($data === false) {
            throw new Exception("Failed to parse YAML file: $file");
        }
        
        $this->validate($data);
        return $data;
    }

    /**
     * Validate manifest structure for security
     * @throws Exception
     */
    public function validate(array $manifest): void
    {
        if (!isset($manifest['name'])) {
            throw new Exception("Invalid manifest: missing 'name'");
        }
        
        if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $manifest['name'])) {
            throw new Exception("Invalid package name: contains illegal characters");
        }
    }
}
