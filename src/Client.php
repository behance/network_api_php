<?php

namespace Behance;

/**
 * Basic Behance Network API implementation to
 * accessing Users, Projects and Works in Progress data
 *
 * Register your application FIRST: http://be.net/dev
 *
 *
 * @author   Bryan Latten  <bryan@behance.com>
 * @author   Michael Klein <michael.klein@behance.com>
 * @author   Mark Dunphy <dunphy@adobe.com>
 * @link     http://be.net/dev
 *
 */
class Client {

  const API_ROOT               = 'https://www.behance.net/v2';
  const ENDPOINT_PROJECTS      = '/projects';
  const ENDPOINT_USERS         = '/users';
  const ENDPOINT_WIPS          = '/wips';
  const ENDPOINT_COLLECTIONS   = '/collections';
  const ENDPOINT_FIELDS        = '/fields';

  const TIMEOUT_DEFAULT_SEC    = 30;
  const VALID                  = 1;
  const INVALID                = 0;

  /**
   * @var string
   */
  protected $_client_id;

  /**
   * Controls printing of exception messages.
   *
   * @var boolean
   */
  protected $_debug;

  /**
   * Information can be found @ http://www.behance.net/dev/apps
   *
   * @param string $client_id
   * @param bool   $debug: OUTPUTS failures if they occur (non-2xx responses)
   */
  public function __construct( $client_id, $debug = false ) {

    $this->_client_id = $client_id;
    $this->_debug     = $debug;

  } // __construct

  /**
   * Retrieves a full Project, by ID
   *
   * @param int  $id              which project to retrieve
   * @param bool $assoc           return object will be converted to an associative array
   *
   * @return array|stdClass|bool  array or stdClass based on $assoc, false on failure
   */
  public function getProject( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS . '/' . $id;

    return $this->_getDecodedJson( $endpoint, array(), 'project', $assoc );

  } // getProject

  /**
   * Retrieves a list of a projects comments, by project ID
   *
   * @param int  $id     which project to retrieve comments for
   * @param bool $assoc  return objects will be converted to associative arrays
   *
   * @return array       stdClass objects or associative arrays, based on $assoc
   */
  public function getProjectComments( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS . "/{$id}/comments";
    $results  = $this->_getDecodedJson( $endpoint, array(), 'comments', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getProjectComments

  /**
   * Retrieves a full User, based on either their ID or Username
   *
   * @param int|string $id_or_username  who to retrieve
   * @param bool $assoc                 return object will be converted to an associative array
   *
   * @return array|stdClass|bool        array or stdClass based on $assoc, false on failure
   */
  public function getUser( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username;

    return $this->_getDecodedJson( $endpoint, array(), 'user', $assoc );

  } // getUser

  /**
   * Retrieves a list of $id_or_username's projects
   *
   * @param int|string $id_or_username  user's projects to search
   * @param array      $params           search parameters ex. [ per_page => 5, page => 2 ]
   * @param bool       $assoc           return objects will be converted to associative arrays
   *
   * @see http://www.behance.net/dev/api/endpoints/2#users-get-2
   *
   * @return array                      stdClass objects or associative arrays, based on $assoc
   */
  public function getUserProjects( $id_or_username, $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/projects';
    $results  = $this->_getDecodedJson( $endpoint, $params, 'projects', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserProjects


  /**
   * Retrieves a list of projects that $id_or_username has appreciated
   *
   * @param int|string $id_or_username  user's projects to search
   * @param bool       $assoc           return objects will be converted to associative arrays
   *
   * @return array                      stdClass objects or associative arrays, based on $assoc
   */
  public function getUserAppreciations( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/appreciations';
    $results  = $this->_getDecodedJson( $endpoint, array(), 'appreciations', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserAppreciations

  /**
   * Retrieves a list of users the given user follows
   *
   * @param  int|string $id_or_username  user
   * @param  bool       $assoc           return objects will be converted to associative arrays
   * @param  array      $options         search options
   *
   * @return array                       stdClass objects or associative arrays, based on $assoc
   */
  public function getUserFollows( $id_or_username, $options = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/following';

    $results = $this->_getDecodedJson( $endpoint, $options, 'following', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserFollows

  /**
   * Retrieves a list of users who follow the provided user
   *
   * @param  int|string $id_or_username  user
   * @param  bool       $assoc           return objects will be converted to associative arrays
   * @param  array      $options         search options
   *
   * @return array                       stdClass objects or associative arrays, based on $assoc
   */
  public function getUserFollowers( $id_or_username, $options = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/followers';

    $results = $this->_getDecodedJson( $endpoint, $options, 'followers', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserFollowers

  /**
   * Retrieves a list of $id_or_username's works in progress
   *
   * @param  int|string $id_or_username  user's works in progress to search
   * @param  array      $params          search parameters ex. [ per_page => 5, page => 2 ]
   * @param  bool       $assoc           return objects will be converted to associative arrays
   *
   * @see http://www.behance.net/dev/api/endpoints/2#users-get-3
   *
   * @return array                       stdClass objects or associative arrays, based on $assoc
   */
  public function getUserWips( $id_or_username, $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/wips';
    $results  = $this->_getDecodedJson( $endpoint, $params, 'wips', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserWips

  /**
   * Retrieves a full Work In Progress, by ID
   *
   * @param int  $id
   * @param bool $assoc           return object will be converted to an associative array
   *
   * @return array|stdClass|bool  array or stdClass based on $assoc, false on failure
   */
  public function getWorkInProgress( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $id;

    $results = $this->_getDecodedJson( $endpoint, array(), 'wip', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getWorkInProgress

  /**
   * Retrieves a collection, by ID
   *
   * @param  int     $id     which collection to retrieve
   * @param  boolean $assoc  return objects will be converted to associative arrays
   *
   * @return array           stdClass objects or associative arrays, based on $assoc
   */
  public function getCollection( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id;

    $results  = $this->_getDecodedJson( $endpoint, array(), 'collection', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getCollection

  /**
   * Retrieves a list of collection $id's projects
   *
   * @param int   $id     collection's projects to search
   * @param bool  $assoc  return objects will be converted to associative arrays
   *
   * @return array        stdClass objects or associative arrays, based on $assoc
   */
  public function getCollectionProjects( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id . '/projects';

    $results  = $this->_getDecodedJson( $endpoint, array(), 'projects', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getCollectionProjects

  /**
   * Retrieves a list of $id_or_username's collections
   *
   * @param int|string $id_or_username  user's works in progress to search
   * @param bool       $assoc           return objects will be converted to associative arrays
   *
   * @return array                      stdClass objects or associative arrays, based on $assoc
   */
  public function getUserCollections( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . "/{$id_or_username}/collections";

    $results  = $this->_getDecodedJson( $endpoint, array(), 'collections', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserCollections

  /**
   * Get user's statistics
   *
   * @param  int|string $id_or_username  user to retrieve stats
   * @param  boolean $assoc              return objects will be converted to associative arrays
   *
   * @return array          stdClass objects or associative arrays, based on $assoc
   */
  public function getUserStats( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . "/{$id_or_username}/stats";

    $results  = $this->_getDecodedJson( $endpoint, array(), 'stats', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserStats

  /**
   * Search projects, by these $params
   *
   * @param array $params  if empty defaults to featured projects
   * @param bool  $assoc   return objects will be converted to associative arrays
   *
   * @return array         stdClass objects or associative arrays, based on $assoc
   */
  public function searchProjects( array $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS;

    $results  = $this->_getDecodedJson( $endpoint, $params, 'projects', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // searchProjects

  /**
   * Search users, by these $params
   *
   * @param array $params  if empty defaults to featured users
   * @param bool  $assoc   return objects will be converted to associative arrays
   *
   * @return array         stdClass objects or associative arrays, based on $assoc
   */
  public function searchUsers( array $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS;

    $results  =  $this->_getDecodedJson( $endpoint, $params, 'users', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // searchUsers

  /**
   * Search works in progress, by these $params
   *
   * @param array $params  if empty defaults to featured works in progress
   * @param bool  $assoc   return objects will be converted to associative arrays
   *
   * @return array         stdClass objects or associative arrays, based on $assoc
   */
  public function searchWips( array $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS;

    $results = $this->_getDecodedJson( $endpoint, $params, 'wips', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // searchWips

  /**
   * Search collections, by these $params
   *
   * @param array $params  if empty defaults to featured collections
   * @param bool  $assoc   return objects will be converted to associative arrays
   *
   * @return array         stdClass objects or associative arrays, based on $assoc
   */
  public function searchCollections( array $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS;

    $results  = $this->_getDecodedJson( $endpoint, $params, 'collections', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // searchCollections

  /**
   * Get creative fields
   *
   * @param bool    $assoc  return objects will be converted to associative arrays
   *
   * @return array          stdClass objects or associative arrays, based on $assoc
   */
  public function getFields( $assoc = false ) {

    $endpoint = self::ENDPOINT_FIELDS;

    $results  = $this->_getDecodedJson( $endpoint, array(), '', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getFields

  /**
   * Automates retrieval data from $endpoint, using $query_params, and returns stdClass based on presence of $root_node
   *
   * @param string $endpoint      API segment to retrieve
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   * @param string $root_node     first object property of JSON response object where the data is attached
   * @param bool   $assoc         when TRUE, returned objects will be converted into associative array
   *
   * @return stdClass|bool
   */
  protected function _getDecodedJson( $endpoint, array $query_params = array(), $root_node = '', $assoc = false ) {

    $query_params['client_id'] = $this->_client_id;
    $entity = $this->_get( $endpoint, $query_params );

    if ( empty( $entity ) ) {
      return false;
    }

    $entity = json_decode( $entity, $assoc );

    if ( !$root_node ) {
      return $entity;
    }

    if ( $assoc ) {

      return ( empty( $entity[ $root_node ] ) )
             ? false
             : $entity[ $root_node ];

    } // if assoc

    return ( empty( $entity->{$root_node} ) )
           ? false
           : $entity->{$root_node};

  } // _getDecodedJson

  /**
   * Performs a GET request, isolates caller from Apiexceptions
   *
   * @param string $endpoint      just the segment of the API the request
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   *
   * @return string|bool          response body on success, false on failure
   */
  protected function _get( $endpoint, array $query_params = array() ) {

    $full_url = $this->_makeFullURL( $endpoint, $query_params );

    try {

      return $this->_executeRequest( 'GET', $full_url );

    } // try

    catch( ApiException $e ) {

      if ( $this->_debug ) {
        echo( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );
      }

    } // catch ApiException

    return false;

  } // _get

  /**
   * Generates a fully-quality API url, with $endpoint + $query_params, automatically adds in app's key
   *
   * @param string $endpoint      segment of the API being accessed
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   */
  protected function _makeFullURL( $endpoint, array $query_params = array() ) {

    $query_string = '?' . http_build_query( $query_params );
    $full_url     = self::API_ROOT . $endpoint . $query_string;

    return $full_url;

  } // _makeFullURL

  /**
   * Makes a remote request to $url
   *
   * @throws ApiException: on any non-200 response from request
   *
   * @param string       $method         HTTP verb of request (get|post|post|delete|head)
   * @param string       $url            fully-qualified destination of request
   * @param string|array $request_body   sent as HTTP body of request
   * @param array        $curl_params    parameters to override for cURL library (timeouts, user agent, etc)
   *
   * @return string                      response body
   */
  protected function _executeRequest( $method, $url, $request_body = false, $curl_params = array() ) {

    if ( !$this->_isCurlLoaded() ) {
      throw new ApiException( 'cURL module is required' );
    }

    $user_agent          = "Behance API/PHP (App {$this->_client_id})";
    $default_curl_params = array(
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: multipart/form-data',
            'Expect:'
        ),
        CURLOPT_TIMEOUT        => self::TIMEOUT_DEFAULT_SEC,
        CURLOPT_USERAGENT      => $user_agent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_BINARYTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST           => false,
        CURLOPT_HTTPGET        => true
    );

    $curl_params = array_replace_recursive( $default_curl_params, $curl_params );

    $ch = curl_init( $url );

    curl_setopt_array( $ch, $curl_params );

    list( $response_body, $request_info, $response_code ) = $this->_executeCurl( $ch );

    curl_close( $ch );

    if ( (int)round( $response_code, -2 ) !== 200 ) {
      throw new ApiException( "Unsuccessful Request, response ({$response_code}): " . ( empty( $response_body ) ? '' : ": {$response_body} " ) );
    }

    return $response_body;

  } // _executeRequest

  /**
   * @codeCoverageIgnore
   *
   * @return boolean
   */
  protected function _isCurlLoaded() {

    return extension_loaded( 'curl' );

  } // _isCurlLoaded

  /**
   * @codeCoverageIgnore
   *
   * @return array
   */
  protected function _executeCurl( $ch ) {

    $request_response = curl_exec( $ch );
    $request_info     = curl_getinfo( $ch );
    $response_code    = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

    return array( $request_response, $request_info, $response_code );

  } // _executeCurl

} // Client
