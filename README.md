Behance Network API / PHP (5.3+)
================================

Basic Behance Network API implementation to accessing Users, Projects and Works in Progress data

See [http://be.net/dev](http://be.net/dev) for more information and documentation.


Installation / Usage
--------------------
1. Please register for an application ID + key first: [http://be.net/dev/register](http://be.net/dev/register)
2. Requires PHP 5.3+
3. Usage.

   ``` php
   require_once( './lib/Be/Api.php' );

   $api = new Be_Api( $api_id, $api_key );

   // User data
   $api->getUser( 'bryan' );

   // User's list of projects
   $api->getUserProjects( 'bryan' );

   // User's work in progress
   $api->getUserWips( 'cfaydi' );

   // Project data
   $api->getProject( 2812719 );

   // Project's comments
   $api->getProjectComments( 2812719 );

   // Featured project list
   $api->searchProjects( array() );


   // Search for motorcycles
   $api->searchProjects( array( 'q' => 'motorcycles' ) );

   ```