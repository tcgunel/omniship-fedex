<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Message\ResponseInterface;

class GetTrackingStatusRequest extends AbstractFedExRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('clientId', 'clientSecret', 'accountNumber', 'trackingNumber');

        return [
            'includeDetailedScans' => true,
            'trackingInfo' => [
                [
                    'trackingNumberInfo' => [
                        'trackingNumber' => $this->getTrackingNumber() ?? '',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/track/v1/trackingnumbers';

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('POST', $url, $data);

        return $this->response = new GetTrackingStatusResponse($this, $result);
    }
}
