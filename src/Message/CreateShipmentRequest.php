<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

class CreateShipmentRequest extends AbstractFedExRequest
{
    public function getLabelFormat(): string
    {
        return (string) ($this->getParameter('labelFormat') ?? 'PDF');
    }

    public function setLabelFormat(string $format): static
    {
        return $this->setParameter('labelFormat', $format);
    }

    public function getShipmentDescription(): ?string
    {
        return $this->getParameter('shipmentDescription');
    }

    public function setShipmentDescription(string $description): static
    {
        return $this->setParameter('shipmentDescription', $description);
    }

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
            'labelResponseOptions' => 'LABEL',
            'accountNumber' => [
                'value' => $this->getAccountNumber() ?? '',
            ],
            'requestedShipment' => [
                'shipper' => $this->buildContactAddress($shipFrom),
                'recipients' => [
                    $this->buildContactAddress($shipTo),
                ],
                'pickupType' => $this->getPickupType(),
                'serviceType' => $this->getServiceCode() ?? 'FEDEX_GROUND',
                'packagingType' => $this->getPackagingType(),
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => [
                                'value' => $this->getAccountNumber() ?? '',
                            ],
                        ],
                    ],
                ],
                'labelSpecification' => [
                    'labelFormatType' => 'COMMON2D',
                    'imageType' => strtoupper($this->getLabelFormat()),
                    'labelStockType' => 'PAPER_4X6',
                ],
                'requestedPackageLineItems' => $this->buildPackageLineItems($packages),
            ],
        ];

        $description = $this->getShipmentDescription() ?? $this->buildDescription($packages);
        if ($description !== '') {
            $data['requestedShipment']['shipmentSpecialServices'] = [
                'specialServiceTypes' => [],
            ];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/ship/v1/shipments';

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('POST', $url, $data);

        return $this->response = new CreateShipmentResponse($this, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactAddress(Address $address): array
    {
        $postalAddress = [
            'streetLines' => array_values(array_filter([
                $address->street1 ?? '',
                $address->street2,
            ], fn (?string $line) => $line !== null && $line !== '')),
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

        $contact = [
            'personName' => $address->name ?? '',
            'phoneNumber' => $address->phone ?? '',
        ];

        if ($address->company !== null && $address->company !== '') {
            $contact['companyName'] = $address->company;
        }

        if ($address->email !== null && $address->email !== '') {
            $contact['emailAddress'] = $address->email;
        }

        return [
            'address' => $postalAddress,
            'contact' => $contact,
        ];
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

                if ($package->description !== null && $package->description !== '') {
                    $item['itemDescription'] = $package->description;
                }

                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param Package[] $packages
     */
    private function buildDescription(array $packages): string
    {
        foreach ($packages as $package) {
            if ($package->description !== null && $package->description !== '') {
                return $package->description;
            }
        }

        return 'Shipment';
    }
}
