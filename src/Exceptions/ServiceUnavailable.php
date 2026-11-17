<?php

namespace Splitfire\ApiGeo\Exceptions;

use GuzzleHttp\Exception\TransferException;
use Splitfire\ApiGeo\Data\ResponseMeta;
use Throwable;

final class ServiceUnavailable extends RequestFailed
{
    public static function unreachable(Throwable $failure, ?string $requestId): self
    {
        // Guzzle's message ends with the full URL, searched address included, so neither it nor the exception is kept.
        $transfer = $failure instanceof TransferException ? $failure : $failure->getPrevious();
        $context = is_object($transfer) && method_exists($transfer, 'getHandlerContext') ? $transfer->getHandlerContext() : [];
        $reason = isset($context['errno'], $context['error']) ? ": cURL error {$context['errno']}: {$context['error']}" : '';

        return new self("api-geo is unreachable{$reason}.", meta: new ResponseMeta(requestId: $requestId));
    }

    public function isTransient(): bool
    {
        // service_disabled means the service is switched off in the gateway, not an outage.
        return $this->errorCode !== 'service_disabled';
    }
}
