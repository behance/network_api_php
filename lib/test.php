<?php

require "Be/Api.php";

$id  = 5;
//$key = "cvkWgUbdooljvzyo8hxCiqU67DjsZX5x";
$key = "9Ix7FC9W7c2gK5t7wahziEzlbhvfn535";

class Be_Api_Test extends Be_Api {

  //const API_ROOT = 'http://www.behancemanage.com/v2';
  const API_ROOT = 'http://net.dev2.be.lan/v2';

} // Be_Api_Test

$obj = new Be_Api_Test( $id, $key, true );

// $wip     = $obj->getWorkInProgress( 355 );
// $user    = $obj->getUser( 5550000 );
// $project = $obj->getProject( 6055000 );


// var_dump( $wip );
// var_dump( $user );
// var_dump( $project );

$projects = $obj->getProjectComments( 60000 );

var_dump( $projects );