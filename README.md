![Behance API Logo](http://assets.behance.net/img/dev/gear.png)

Behance Network API / PHP (5.2+)
================================

Basic implementation to access User, Project, Work in Progress and Collection data

See [http://be.net/dev](http://be.net/dev) for more information and documentation.

Authentication
--------------------
Get an API key by registering your application here: [http://be.net/dev/register](http://be.net/dev/register)


Install via Composer
--------------------

```json
{
   "require": {
     "behance/api-network": "~2.0.0"
   }
}
```

Usage
--------------------

``` php
require_once( './vendor/autoload.php' );

$client = new Behance\Client( $client_id );

// User data
$client->getUser( 'bryan' );

// User's list of projects
$client->getUserProjects( 'bryan' );

// User's work in progress
$client->getUserWips( 'cfaydi' );

// Project data
$client->getProject( 2812719 );

// Project's comments
$client->getProjectComments( 2812719 );

// Featured project list
$client->searchProjects( array() );

// Search for motorcycles
$client->searchProjects( array( 'q' => 'motorcycles' ) );

```

Requirements
------------

1. Requires PHP 5.2+
2. PHP cURL module
