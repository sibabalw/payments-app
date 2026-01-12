<?php

namespace App\Services\PaymentGateway;

class EscrowOperationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {
    }

    public static function success(string $transactionId, array $metadata = []): self
    {
        return new self(true, $transactionId, null, $metadata);
    }

    public static function failure(string $errorMessage, array $metadata = []): self
    {
        return new self(false, null, $errorMessage, $metadata);
    }
}
