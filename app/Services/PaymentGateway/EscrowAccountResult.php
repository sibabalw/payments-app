<?php

namespace App\Services\PaymentGateway;

class EscrowAccountResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $accountNumber = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {
    }

    public static function success(string $accountNumber, array $metadata = []): self
    {
        return new self(true, $accountNumber, null, $metadata);
    }

    public static function failure(string $errorMessage, array $metadata = []): self
    {
        return new self(false, null, $errorMessage, $metadata);
    }
}
