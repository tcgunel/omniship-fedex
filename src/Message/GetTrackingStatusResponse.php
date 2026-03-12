<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\TrackingResponse;
use Omniship\Common\TrackingEvent;
use Omniship\Common\TrackingInfo;

class GetTrackingStatusResponse extends AbstractResponse implements TrackingResponse
{
    /**
     * FedEx derived status code mapping.
     */
    private const STATUS_CODE_MAP = [
        'PU' => ShipmentStatus::PICKED_UP,
        'OC' => ShipmentStatus::PRE_TRANSIT,
        'IT' => ShipmentStatus::IN_TRANSIT,
        'IX' => ShipmentStatus::IN_TRANSIT,
        'AR' => ShipmentStatus::IN_TRANSIT,
        'DP' => ShipmentStatus::IN_TRANSIT,
        'OF' => ShipmentStatus::IN_TRANSIT,
        'FD' => ShipmentStatus::IN_TRANSIT,
        'HP' => ShipmentStatus::IN_TRANSIT,
        'OD' => ShipmentStatus::OUT_FOR_DELIVERY,
        'DL' => ShipmentStatus::DELIVERED,
        'DE' => ShipmentStatus::FAILURE,
        'CA' => ShipmentStatus::CANCELLED,
        'RS' => ShipmentStatus::RETURNED,
        'SE' => ShipmentStatus::FAILURE,
    ];

    public function isSuccessful(): bool
    {
        if (!is_array($this->data)) {
            return false;
        }

        // Check for errors
        if (isset($this->data['errors']) && is_array($this->data['errors']) && $this->data['errors'] !== []) {
            return false;
        }

        return isset($this->data['output']['completeTrackResults'])
            && is_array($this->data['output']['completeTrackResults'])
            && $this->data['output']['completeTrackResults'] !== [];
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

    public function getTrackingInfo(): TrackingInfo
    {
        if (!$this->isSuccessful() || !is_array($this->data)) {
            return new TrackingInfo(
                trackingNumber: '',
                status: ShipmentStatus::UNKNOWN,
                carrier: 'FedEx',
            );
        }

        /** @var array<int, array<string, mixed>> $completeResults */
        $completeResults = $this->data['output']['completeTrackResults'] ?? [];

        if ($completeResults === []) {
            return new TrackingInfo(
                trackingNumber: '',
                status: ShipmentStatus::UNKNOWN,
                carrier: 'FedEx',
            );
        }

        $trackingNumber = (string) ($completeResults[0]['trackingNumber'] ?? '');

        /** @var array<int, array<string, mixed>> $trackResults */
        $trackResults = $completeResults[0]['trackResults'] ?? [];

        if ($trackResults === []) {
            return new TrackingInfo(
                trackingNumber: $trackingNumber,
                status: ShipmentStatus::UNKNOWN,
                carrier: 'FedEx',
            );
        }

        /** @var array<string, mixed> $result */
        $result = $trackResults[0];

        // Parse latest status
        /** @var array{code?: string, statusByLocale?: string, description?: string} $latestStatus */
        $latestStatus = $result['latestStatusDetail'] ?? [];
        $statusCode = $latestStatus['code'] ?? '';
        $status = self::STATUS_CODE_MAP[$statusCode] ?? ShipmentStatus::UNKNOWN;

        // Parse events
        $events = $this->parseEvents($result);

        // If we have events, use the latest event's status
        if ($events !== []) {
            $status = $events[0]->status;
        }

        // Get signed by
        $signedBy = null;
        /** @var array{receivedByName?: string} $deliveryDetails */
        $deliveryDetails = $result['deliveryDetails'] ?? [];
        if (isset($deliveryDetails['receivedByName']) && $deliveryDetails['receivedByName'] !== '') {
            $signedBy = $deliveryDetails['receivedByName'];
        }

        // Get service name
        $serviceName = null;
        /** @var array{description?: string} $serviceDetail */
        $serviceDetail = $result['serviceDetail'] ?? [];
        if (isset($serviceDetail['description'])) {
            $serviceName = $serviceDetail['description'];
        }

        // Get estimated delivery
        $estimatedDelivery = null;
        /** @var array{window?: array{ends?: string}} $estimatedWindow */
        $estimatedWindow = $result['estimatedDeliveryTimeWindow'] ?? [];
        if (isset($estimatedWindow['window']['ends'])) {
            $estimatedDelivery = $this->parseDate($estimatedWindow['window']['ends']);
        }

        return new TrackingInfo(
            trackingNumber: $trackingNumber,
            status: $status,
            events: $events,
            carrier: 'FedEx',
            serviceName: $serviceName,
            estimatedDelivery: $estimatedDelivery,
            signedBy: $signedBy,
        );
    }

    /**
     * @param array<string, mixed> $result
     * @return TrackingEvent[]
     */
    private function parseEvents(array $result): array
    {
        /** @var array<int, array<string, mixed>> $scanEvents */
        $scanEvents = $result['scanEvents'] ?? [];

        $events = [];

        foreach ($scanEvents as $scanEvent) {
            $derivedStatusCode = (string) ($scanEvent['derivedStatusCode'] ?? '');
            $eventDescription = (string) ($scanEvent['eventDescription'] ?? '');
            $dateStr = (string) ($scanEvent['date'] ?? '');

            $occurredAt = $this->parseDate($dateStr);
            if ($occurredAt === null) {
                continue;
            }

            $eventStatus = self::STATUS_CODE_MAP[$derivedStatusCode] ?? ShipmentStatus::UNKNOWN;

            // Parse location
            $location = null;
            $city = null;
            $country = null;

            /** @var array{city?: string, stateOrProvinceCode?: string, countryCode?: string} $scanLocation */
            $scanLocation = $scanEvent['scanLocation'] ?? [];

            if (isset($scanLocation['city'])) {
                $city = $scanLocation['city'];
                $stateCode = $scanLocation['stateOrProvinceCode'] ?? null;
                $location = $stateCode !== null ? $city . ', ' . $stateCode : $city;
            }

            if (isset($scanLocation['countryCode'])) {
                $country = $scanLocation['countryCode'];
            }

            $events[] = new TrackingEvent(
                status: $eventStatus,
                description: $eventDescription,
                occurredAt: $occurredAt,
                location: $location,
                city: $city,
                country: $country,
            );
        }

        return $events;
    }

    private function parseDate(string $dateStr): ?\DateTimeImmutable
    {
        if ($dateStr === '') {
            return null;
        }

        // Try ISO 8601 with timezone
        try {
            return new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            return null;
        }
    }
}
