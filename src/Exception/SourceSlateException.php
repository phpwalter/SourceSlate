<?php

declare(strict_types=1);

namespace SourceSlate\Exception;

class SourceSlateException extends \RuntimeException
{
    public function __construct(
        public readonly string $diagnosticCode,
        string $message,
        public readonly int $exitCode = 1,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function formattedMessage(): string
    {
        return sprintf('%s: %s', $this->diagnosticCode, $this->getMessage());
    }
}
