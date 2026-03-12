<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\CancelResponse;

class CancelShipmentResponse extends AbstractResponse implements CancelResponse
{
    public function isSuccessful(): bool
    {
        if (!is_array($this->data)) {
            return false;
        }

        // Check for errors
        if (isset($this->data['errors']) && is_array($this->data['errors']) && $this->data['errors'] !== []) {
            return false;
        }

        // Check for successful cancellation
        if (isset($this->data['output']['cancelledShipment']) && $this->data['output']['cancelledShipment'] === true) {
            return true;
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return $this->isSuccessful();
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data)) {
            return null;
        }

        if (isset($this->data['errors']) && is_array($this->data['errors']) && $this->data['errors'] !== []) {
            /** @var array{message?: string} $firstError */
            $firstError = $this->data['errors'][0];

            return $firstError['message'] ?? null;
        }

        if (isset($this->data['output']['successMessage'])) {
            return (string) $this->data['output']['successMessage'];
        }

        return null;
    }

    public function getCode(): ?string
    {
        if (!is_array($this->data)) {
            return null;
        }

        if (isset($this->data['errors']) && is_array($this->data['errors']) && $this->data['errors'] !== []) {
            /** @var array{code?: string} $firstError */
            $firstError = $this->data['errors'][0];

            return $firstError['code'] ?? null;
        }

        return $this->isSuccessful() ? '200' : null;
    }
}
