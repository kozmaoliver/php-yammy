<?php

namespace Yammy\Security\DTO;

class SecurityLogDTO
{
    public \DateTimeImmutable $timestamp;
    public string $type;
    public string $package;
    public string $version;
    public string $details;

    public function __construct(
        \DateTimeImmutable $timestamp,
        string $type, //TODO: enums
        string $package,
        string $version,
        string $details
    )
    {
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->package = $package;
        $this->version = $version;
        $this->details = $details;
    }
}