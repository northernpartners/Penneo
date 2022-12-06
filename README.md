# Penneo API wrapper for PHP

## Install

```shell
composer require np-digital/api
```

## Configure

Copy `dist/.env` to the your root folder and add your Penneo key, secret and API endpoint.

	PENNEO_API_KEY=
	PENNEO_API_SECRET=
	PENNEO_API_URI=

Endpoints `sandbox.penneo.com/api/v3/`or `app.penneo.com/api/v3/`.

## Usage

Create a new casefile comtaining 2 documents and with 2 signers, who are contacted via email for document signing.

```php
require_once ( $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

use Symfony\Component\Dotenv\Dotenv;
use NPDigital\Api\Penneo;

$dotenv = new Dotenv();
$dotenv->load( $_SERVER['DOCUMENT_ROOT'] .'/.env');

$signers = [
	['name' => 'Wendy Willard', 'representing' => 'Self', 'email' => 'wendy.willard@yahoo.com'],
	['name' => 'Sam Samson', 'representing' => 'Acme Inc', 'email' => 'sam@acme.io']
];
$documents = [
	['title' => 'Start Pebble employment agreement', 'filename' => __DIR__.'/contract.pdf', 'documentTypeId' => 0],
	['title' => 'Start Pebble - general terms', 'filename' => __DIR__.'/appendix.pdf', 'documentTypeId' => 1],
];

$response = Penneo::casefile()->create(
	title: 'Start Pebble employment contract', 
	documents: $documents, 
	signers: $signers
)->send();

echo $response;
```

Create a draft with the same data.

```php
Penneo::casefile()->create(
	title: 'Start Pebble employment contract', 
	documents: $documents, 
	signers: $signers
)
```

Get a casefile `json` digest using a casefile id.

```php
$response = Penneo::casefile( casefileId: 7466435 );

echo $response;
```

Send a draft.

```php
Penneo::casefile( casefileId: 7466435 )->send();
```
