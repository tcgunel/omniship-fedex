<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\RateResponse;
use Omniship\Common\Rate;

class GetRatesResponse extends AbstractResponse implements RateResponse
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

        return isset($this->data['output']['rateReplyDetails'])
            && is_array($this->data['output']['rateReplyDetails'])
            && $this->data['output']['rateReplyDetails'] !== [];
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

    /**
     * @return Rate[]
     */
    public function getRates(): array
    {
        if (!$this->isSuccessful() || !is_array($this->data)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rateDetails */
        $rateDetails = $this->data['output']['rateReplyDetails'];

        $rates = [];

        foreach ($rateDetails as $detail) {
            $serviceType = (string) ($detail['serviceType'] ?? '');
            $serviceName = (string) ($detail['serviceName'] ?? '');

            // Get pricing from rated shipment details
            $price = 0.0;
            $currency = 'USD';

            /** @var array<int, array{rateType?: string, totalNetCharge?: float, currency?: string}> $ratedDetails */
            $ratedDetails = $detail['ratedShipmentDetails'] ?? [];

            foreach ($ratedDetails as $rated) {
                if (isset($rated['totalNetCharge'])) {
                    $price = (float) $rated['totalNetCharge'];
                    $currency = (string) ($rated['currency'] ?? 'USD');
                    break;
                }
            }

            // Get transit information
            $transitDays = null;
            $estimatedDelivery = null;

            /** @var array{dateDetail?: array{dayFormat?: string}, transitDays?: array{description?: string}} $commit */
            $commit = $detail['commit'] ?? [];

            if (isset($commit['transitDays']['description'])) {
                $transitDays = (int) $commit['transitDays']['description'];
            }

            if (isset($commit['dateDetail']['dayFormat'])) {
                $estimatedDelivery = $this->parseDate($commit['dateDetail']['dayFormat']);
            }

            $rates[] = new Rate(
                carrier: 'FedEx',
                serviceCode: $serviceType,
                serviceName: $serviceName,
                totalPrice: $price,
                currency: $currency,
                transitDays: $transitDays,
                estimatedDelivery: $estimatedDelivery,
            );
        }

        return $rates;
    }

    private function parseDate(string $dateStr): ?\DateTimeImmutable
    {
        if ($dateStr === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($date !== false) {
            return $date;
        }

        try {
            return new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            return null;
        }
    }
}
