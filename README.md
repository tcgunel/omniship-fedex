# Omniship FedEx

FedEx carrier driver for the [Omniship](https://github.com/tcgunel/omniship) shipping library.

Uses the FedEx REST API with OAuth 2.0 authentication.

## Installation

```bash
composer require tcgunel/omniship-fedex
```

## Usage

### Initialize

```php
use Omniship\Omniship;

$carrier = Omniship::create('FedEx');
$carrier->initialize([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'accountNumber' => 'your-account-number',
    'testMode' => true, // false for production
]);
```

### Create Shipment

```php
use Omniship\Common\Address;
use Omniship\Common\Package;

$response = $carrier->createShipment([
    'shipFrom' => new Address(
        name: 'Ahmet Yilmaz',
        company: 'Sender Company',
        street1: 'Ataturk Cad. No:42',
        city: 'Istanbul',
        district: 'Kadikoy',
        postalCode: '34710',
        country: 'TR',
        phone: '+905551234567',
    ),
    'shipTo' => new Address(
        name: 'John Smith',
        company: 'Receiver Inc',
        street1: '123 Main Street',
        city: 'New York',
        state: 'NY',
        postalCode: '10001',
        country: 'US',
        phone: '+12125551234',
    ),
    'packages' => [
        new Package(weight: 2.5, length: 30, width: 20, height: 15, description: 'Electronics'),
    ],
    'serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY', // optional
])->send();

if ($response->isSuccessful()) {
    echo $response->getTrackingNumber(); // "794644790138"
    echo $response->getShipmentId();     // shipment identifier

    $label = $response->getLabel();
    if ($label !== null) {
        file_put_contents('label.pdf', base64_decode($label->content));
    }

    echo $response->getTotalCharge();    // 85.50
    echo $response->getCurrency();       // "USD"
} else {
    echo $response->getMessage(); // error description
    echo $response->getCode();    // error code
}
```

### Track Shipment

```php
$response = $carrier->getTrackingStatus([
    'trackingNumber' => '794644790138',
])->send();

if ($response->isSuccessful()) {
    $info = $response->getTrackingInfo();
    echo $info->trackingNumber;
    echo $info->status->value;    // "delivered", "in_transit", etc.
    echo $info->carrier;          // "FedEx"
    echo $info->serviceName;      // "FedEx International Priority"
    echo $info->signedBy;         // "J.SMITH"

    foreach ($info->events as $event) {
        echo $event->description; // "Delivered"
        echo $event->city;        // "NEW YORK"
        echo $event->country;     // "US"
        echo $event->occurredAt->format('Y-m-d H:i');
    }
}
```

### Cancel Shipment

```php
$response = $carrier->cancelShipment([
    'trackingNumber' => '794644790138',
])->send();

if ($response->isCancelled()) {
    echo 'Shipment cancelled';
}
```

### Get Rates

```php
$response = $carrier->getRates([
    'shipFrom' => new Address(
        city: 'Istanbul',
        postalCode: '34710',
        country: 'TR',
    ),
    'shipTo' => new Address(
        city: 'New York',
        postalCode: '10001',
        country: 'US',
    ),
    'packages' => [
        new Package(weight: 2.5, length: 30, width: 20, height: 15),
    ],
])->send();

if ($response->isSuccessful()) {
    foreach ($response->getRates() as $rate) {
        echo $rate->serviceCode;       // "FEDEX_INTERNATIONAL_PRIORITY"
        echo $rate->serviceName;       // "FedEx International Priority"
        echo $rate->totalPrice;        // 85.50
        echo $rate->currency;          // "USD"
        echo $rate->transitDays;       // 3
    }
}
```

## Tracking Status Mapping

| FedEx Code | Description | ShipmentStatus |
|------------|-------------|----------------|
| `PU` | Picked up | `PICKED_UP` |
| `OC` | Order created | `PRE_TRANSIT` |
| `IT` | In transit | `IN_TRANSIT` |
| `AR` | Arrived at facility | `IN_TRANSIT` |
| `DP` | Departed facility | `IN_TRANSIT` |
| `OD` | Out for delivery | `OUT_FOR_DELIVERY` |
| `DL` | Delivered | `DELIVERED` |
| `DE` | Delivery exception | `FAILURE` |
| `CA` | Cancelled | `CANCELLED` |
| `RS` | Return to shipper | `RETURNED` |

## API Details

- **Transport**: REST/JSON via PSR-18 HTTP client
- **Auth**: OAuth 2.0 (client credentials grant)
- **Base URL**: `https://apis.fedex.com` (prod), `https://apis-sandbox.fedex.com` (sandbox)
- **Create**: `POST /ship/v1/shipments`
- **Track**: `POST /track/v1/trackingnumbers`
- **Cancel**: `PUT /ship/v1/shipments/cancel`
- **Rates**: `POST /rate/v1/rates/quotes`

## Testing

```bash
vendor/bin/pest
```

## License

MIT
