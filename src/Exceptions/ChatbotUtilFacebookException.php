<?php

namespace Iquesters\Integration\Exceptions;

use RuntimeException;

class ChatbotUtilFacebookException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $status = 500,
        protected ?string $errorCode = null,
        protected ?string $requestId = null
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
