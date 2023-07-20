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

$casefile = Penneo::casefile();
$folder = $casefile->getFolder('Temp');
$template = $casefile->getTemplate('Ansættelsesaftale');

$signers = [
	['name' => 'Wendy Willard', 'representing' => 'Self', 'email' => 'wendy.willard@yahoo.com', 'signerTypeId' => 0],
	['name' => 'Sam Samson', 'representing' => 'Acme Inc', 'email' => 'sam@acme.io', 'signerTypeId' => 1]
];
$documents = [
	['title' => 'StartPepple employment agreement', 'filename' => __DIR__.'/contract.pdf', 'documentTypeId' => 0],
	['title' => 'StartPepple Aps - general terms', 'filename' => __DIR__.'/terms.pdf', 'documentTypeId' => 1],
	['title' => 'StartPepple Aps - appendix', 'filename' => __DIR__.'/appendix.pdf', 'documentTypeId' => 2],
];

$response = $casefile->create(
	title: 'StartPeople employment contract ' . date('ymd-Hi'), 
	documents: $documents, 
	signers: $signers,
	template: $template,
	folder: $folder
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

Get a casefile digest using a casefile id.

```php
$casefile = Penneo::casefile( casefileId: 447255 )->parse();
print_r($casefile->response);
```

Send a draft.

```php
Penneo::casefile( casefileId: 7466435 )->send();
```

## Entity getters

Return a `Folder` object by name or id.
```php
$folder = $casefile->getFolder([$name | $id]);
```

Return a `CaseFileTemplate` object by name or id.
```php
$template = $casefile->getTemplate([$name | $id]);
```

## Helper functions

Available folders

```php
$folders = Penneo::casefile()->getFolders();
var_dump($folders);
```

Available templates

```php
$template_list = Penneo::casefile()->getTemplates();
var_dump($template_list);
```

Template structure

```php
$templateStructure = Penneo::casefile()->setTemplate($templateId)->showTemplate();
var_dump($templateStructure);
```
