<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ToolApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['error' => array_filter([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->details ?: null,
        ], fn ($value) => $value !== null)], $this->status)->header('Cache-Control', 'no-store');
    }
}
