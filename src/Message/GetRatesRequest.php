<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

class GetRatesRequest extends AbstractFedExRequest
{
    public function getPickupType(): string
    {
        return (string) ($this->getParameter('pickupType') ?? 'USE_SCHEDULED_PICKUP');
    }

    public function setPickupType(string $pickupType): static
    {
        return $this->setParameter('pickupType', $pickupType);
    }

    public function getPackagingType(): string
    {
        return (string) ($this->getParameter('packagingType') ?? 'YOUR_PACKAGING');
    }

    public function setPackagingType(string $packagingType): static
    {
        return $this->setParameter('packagingType', $packagingType);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('clientId', 'clientSecret', 'accountNumber', 'shipFrom', 'shipTo');

        $shipFrom = $this->getShipFrom();
        assert($shipFrom instanceof Address);

        $shipTo = $this->getShipTo();
        assert($shipTo instanceof Address);

        $packages = $this->getPackages() ?? [];

        $data = [
            'accountNumber' => [
                'value' => $this->getAccountNumber() ?? '',
            ],
            'requestedShipment' => [
                'shipper' => [
                    'address' => $this->buildAddress($shipFrom),
                ],
                'recipient' => [
                    'address' => $this->buildAddress($shipTo),
                ],
                'pickupType' => $this->getPickupType(),
                'packagingType' => $this->getPackagingType(),
                'requestedPackageLineItems' => $this->buildPackageLineItems($packages),
            ],
        ];

        if ($this->getServiceCode() !== null) {
            $data['requestedShipment']['serviceType'] = $this->getServiceCode();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/rate/v1/rates/quotes';

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('POST', $url, $data);

        return $this->response = new GetRatesResponse($this, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddress(Address $address): array
    {
        $postalAddress = [
            'city' => $address->city ?? '',
            'countryCode' => $address->country ?? 'US',
        ];

        if ($address->state !== null && $address->state !== '') {
            $postalAddress['stateOrProvinceCode'] = $address->state;
        }

        if ($address->postalCode !== null && $address->postalCode !== '') {
            $postalAddress['postalCode'] = $address->postalCode;
        }

        if ($address->residential) {
            $postalAddress['residential'] = true;
        }

        return $postalAddress;
    }

    /**
     * @param Package[] $packages
     * @return array<int, array<string, mixed>>
     */
    private function buildPackageLineItems(array $packages): array
    {
        if ($packages === []) {
            return [[
                'weight' => [
                    'value' => 0.5,
                    'units' => 'KG',
                ],
            ]];
        }

        $items = [];

        foreach ($packages as $package) {
            for ($i = 0; $i < $package->quantity; $i++) {
                $item = [
                    'weight' => [
                        'value' => $package->weight,
                        'units' => 'KG',
                    ],
                ];

                if ($package->length !== null && $package->width !== null && $package->height !== null) {
                    $item['dimensions'] = [
                        'length' => (int) $package->length,
                        'width' => (int) $package->width,
                        'height' => (int) $package->height,
                        'units' => 'CM',
                    ];
                }

                $items[] = $item;
            }
        }

        return $items;
    }
}
