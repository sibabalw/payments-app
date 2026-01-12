<?php

namespace App\Services\PaymentGateway;

class EscrowDepositResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $reference = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {
    }

    public static function success(string $reference, array $metadata = []): self
    {
        return new self(true, $reference, null, $metadata);
    }

    public static function failure(string $errorMessage, array $metadata = []): self
    {
        return new self(false, null, $errorMessage, $metadata);
    }
}
