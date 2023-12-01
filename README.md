# Kinoplenka Balancer

A wrapper for managing the balancer API.

## Installation

```bash
composer require kinoplenka/balancer
```

## Basic usage
```php
use Kinoplenka\Balancer\Balancer;

$balancer = new Balancer('token');

$response = $balancer->get('list');
```

## License
Open-sourced software licensed under the [MIT license](https://opensource.org/license/mit/).