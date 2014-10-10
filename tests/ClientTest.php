<?php

use Behance\Client;

class ClientTest extends PHPUnit_Framework_TestCase {

  const API_KEY = 'abcdegh1234567';

  /**
   * @test
   */
  public function getProject() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getProject( 1234 );

  } // getProject

  /**
   * @test
   */
  public function getProjectAssoc() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getProject( 1234, true );

  } // getProjectAssoc

  /**
   * @test
   */
  public function getProjectEmptyResult() {

    $client = $this->getMock( 'Behance\Client', array( '_get' ), array( self::API_KEY ) );

    $client->expects( $this->once() )
      ->method( '_get' )
      ->will( $this->returnValue( null ) );

    $this->assertFalse( $client->getProject( 1234 ) );

  } // getProjectEmptyResult

  /**
   * @test
   */
  public function getProjectNon200ResponseCode() {

    $client = $this->getMock( 'Behance\Client', array( '_executeCurl' ), array( self::API_KEY ) );

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( [ '{}', $this->_getMockRequestInfo(), 403 ] ) );

    $this->assertFalse( $client->getProject( 1234 ) );

  } // getProjectNon200ResponseCode

  /**
   * @test
   */
  public function getProjectFailureDebugMode() {

    $client = $this->getMock( 'Behance\Client', array( '_executeCurl' ), array( self::API_KEY, true ) );

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( [ '{}', $this->_getMockRequestInfo(), 403 ] ) );

    ob_start();
    $response = $client->getProject( 1234 );
    $message = ob_get_clean();

    $this->assertFalse( $response );
    $this->assertContains( "403", $message );

  } // getProjectFailureDebugMode

  /**
   * @test
   */
  public function getProjectCurlNotLoaded() {

    $client = $this->getMock( 'Behance\Client', array( '_isCurlLoaded' ), array( self::API_KEY ) );

    $client->expects( $this->once() )
      ->method( '_isCurlLoaded' )
      ->will( $this->returnValue( false ) );

    $this->assertFalse( $client->getProject( 1234 ) );

  } // getProjectCurlNotLoaded

  /**
   * @test
   */
  public function getProjectComments() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getProjectComments( 1234 );

  } // getProjectComments

  /**
   * @test
   */
  public function getUser() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUser( 1234 );

  } // getUser

  /**
   * @test
   */
  public function getUserProjects() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserProjects( 1234 );

  } // getUserProjects

  /**
   * @test
   */
  public function getUserAppreciations() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserAppreciations( 1234 );

  } // getUserAppreciations

  /**
   * @test
   */
  public function getUserFollows() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserFollows( 1234 );

  } // getUserFollows

  /**
   * @test
   */
  public function getUserFollowers() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserFollowers( 1234 );

  } // getUserFollowers

  /**
   * @test
   */
  public function getUserWips() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserWips( 1234 );

  } // getUserWips

  /**
   * @test
   */
  public function getWorkInProgress() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getWorkInProgress( 1234 );

  } // getWorkInProgress

  /**
   * @test
   */
  public function getCollection() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getCollection( 1234 );

  } // getCollection

  /**
   * @test
   */
  public function getCollectionProjects() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getCollectionProjects( 1234 );

  } // getCollectionProjects

  /**
   * @test
   */
  public function getUserCollections() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserCollections( 1234 );

  } // getUserCollections

  /**
   * @test
   */
  public function getUserStats() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getUserStats( 1234 );

  } // getUserStats

  /**
   * @test
   */
  public function searchProjects() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->searchProjects();

  } // searchProjects

  /**
   * @test
   */
  public function searchUsers() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->searchUsers();

  } // searchUsers

  /**
   * @test
   */
  public function searchWips() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->searchWips();

  } // searchWips

  /**
   * @test
   */
  public function searchCollections() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->searchCollections();

  } // searchCollections

  /**
   * @test
   */
  public function getFields() {

    $client = $this->_getMockClient();

    $client->expects( $this->once() )
      ->method( '_executeCurl' )
      ->will( $this->returnValue( array( '{}', $this->_getMockRequestInfo(), 200 ) ) );

    $client->getFields( 1234 );

  } // getFields

  private function _getMockClient( array $extra_methods = array() ) {

    $default_methods = array( '_isCurlLoaded', '_executeCurl' );
    $methods = array_merge( $default_methods, $extra_methods );

    $client = $this->getMock( 'Behance\Client', $methods, array( self::API_KEY ) );

    $client->expects( $this->once() )
      ->method( '_isCurlLoaded' )
      ->will( $this->returnValue( true ) );

    return $client;

  } // _getMockClient

  private function _getMockRequestInfo() {

    return array(
        'download_content_length' => 0,
    );

  } // _getMockRequestInfo

} // ClientTest
