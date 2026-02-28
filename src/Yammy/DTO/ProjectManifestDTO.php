<?php

namespace Yammy\DTO;

class ProjectManifestDTO
{
    public string $name;
    public ?string $description;
    public ?array $require;
    public ?array $packages;
    public ?array $security;

    public function __construct(
        string $name,
        ?string $description,
        ?array $require,
        ?array $packages,
        ?array $security
    )
    {
        $this->name = $name;
        $this->description = $description;
        $this->require = $require;
        $this->packages = $packages;
        $this->security = $security;
    }
}