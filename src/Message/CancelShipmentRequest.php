<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Message\ResponseInterface;

class CancelShipmentRequest extends AbstractFedExRequest
{
    public function getDeletionControl(): string
    {
        return (string) ($this->getParameter('deletionControl') ?? 'DELETE_ALL_PACKAGES');
    }

    public function setDeletionControl(string $deletionControl): static
    {
        return $this->setParameter('deletionControl', $deletionControl);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('clientId', 'clientSecret', 'accountNumber', 'trackingNumber');

        return [
            'accountNumber' => [
                'value' => $this->getAccountNumber() ?? '',
            ],
            'trackingNumber' => $this->getTrackingNumber() ?? '',
            'deletionControl' => $this->getDeletionControl(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/ship/v1/shipments/cancel';

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('PUT', $url, $data);

        return $this->response = new CancelShipmentResponse($this, $result);
    }
}
