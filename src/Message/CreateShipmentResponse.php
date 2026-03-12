<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Enum\LabelFormat;
use Omniship\Common\Label;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ShipmentResponse;

class CreateShipmentResponse extends AbstractResponse implements ShipmentResponse
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

        // Check for successful shipment output
        return isset($this->data['output']['transactionShipments'])
            && is_array($this->data['output']['transactionShipments'])
            && $this->data['output']['transactionShipments'] !== [];
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data)) {
            return null;
        }

        // Check errors array
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

        // Check errors array
        if (isset($this->data['errors']) && is_array($this->data['errors']) && $this->data['errors'] !== []) {
            /** @var array{code?: string} $firstError */
            $firstError = $this->data['errors'][0];

            return $firstError['code'] ?? null;
        }

        return $this->isSuccessful() ? '200' : null;
    }

    public function getShipmentId(): ?string
    {
        return $this->getMasterTrackingNumber();
    }

    public function getTrackingNumber(): ?string
    {
        return $this->getMasterTrackingNumber();
    }

    public function getBarcode(): ?string
    {
        return $this->getTrackingNumber();
    }

    public function getLabel(): ?Label
    {
        if (!$this->isSuccessful() || !is_array($this->data)) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $shipments */
        $shipments = $this->data['output']['transactionShipments'] ?? [];

        foreach ($shipments as $shipment) {
            /** @var array<int, array{packageDocuments?: array<int, array{contentType?: string, content?: string}>}> $pieceResponses */
            $pieceResponses = $shipment['pieceResponses'] ?? [];

            foreach ($pieceResponses as $piece) {
                /** @var array<int, array{contentType?: string, content?: string}> $documents */
                $documents = $piece['packageDocuments'] ?? [];

                foreach ($documents as $document) {
                    if (($document['contentType'] ?? '') === 'LABEL' && isset($document['content'])) {
                        return new Label(
                            trackingNumber: $this->getTrackingNumber() ?? '',
                            content: $document['content'],
                            format: LabelFormat::PDF,
                        );
                    }
                }
            }
        }

        return null;
    }

    public function getTotalCharge(): ?float
    {
        if (!$this->isSuccessful() || !is_array($this->data)) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $shipments */
        $shipments = $this->data['output']['transactionShipments'] ?? [];

        foreach ($shipments as $shipment) {
            /** @var array{shipmentRating?: array{shipmentRateDetails?: array<int, array{totalNetCharge?: float}>}} $detail */
            $detail = $shipment['completedShipmentDetail'] ?? [];

            /** @var array<int, array{totalNetCharge?: float}> $rateDetails */
            $rateDetails = $detail['shipmentRating']['shipmentRateDetails'] ?? [];

            foreach ($rateDetails as $rate) {
                if (isset($rate['totalNetCharge'])) {
                    return (float) $rate['totalNetCharge'];
                }
            }
        }

        return null;
    }

    public function getCurrency(): ?string
    {
        if (!$this->isSuccessful() || !is_array($this->data)) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $shipments */
        $shipments = $this->data['output']['transactionShipments'] ?? [];

        foreach ($shipments as $shipment) {
            /** @var array{shipmentRating?: array{shipmentRateDetails?: array<int, array{currency?: string}>}} $detail */
            $detail = $shipment['completedShipmentDetail'] ?? [];

            /** @var array<int, array{currency?: string}> $rateDetails */
            $rateDetails = $detail['shipmentRating']['shipmentRateDetails'] ?? [];

            foreach ($rateDetails as $rate) {
                if (isset($rate['currency'])) {
                    return (string) $rate['currency'];
                }
            }
        }

        return null;
    }

    private function getMasterTrackingNumber(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['output']['transactionShipments'])) {
            return null;
        }

        /** @var array<int, array{masterTrackingNumber?: string}> $shipments */
        $shipments = $this->data['output']['transactionShipments'];

        if ($shipments === []) {
            return null;
        }

        $trackingNumber = $shipments[0]['masterTrackingNumber'] ?? null;

        return $trackingNumber !== null && $trackingNumber !== '' ? $trackingNumber : null;
    }
}
