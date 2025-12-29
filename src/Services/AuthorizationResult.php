<?php

namespace Workz\Platform\Services;

class AuthorizationResult
{
    public bool $allowed;
    public string $reason;
    public array $meta;

    public function __construct(bool $allowed, string $reason = '', array $meta = [])
    {
        $this->allowed = $allowed;
        $this->reason = $reason;
        $this->meta = $meta;
    }

    public static function allow(array $meta = []): self
    {
        return new self(true, '', $meta);
    }

    public static function deny(string $reason, array $meta = []): self
    {
        return new self(false, $reason, $meta);
    }
}
