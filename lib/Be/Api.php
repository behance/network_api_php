<?php
/**
 * Basic Behance Network API implementation to
 * accessing Users, Projects and Works in Progress data
 *
 * Register your application FIRST: http://be.net/dev
 *
 * @author   Bryan Latten <bryan@behance.com>
 * @link     http://be.net/dev
 *
 */
class Be_Api {

  const API_ROOT          = 'http://www.behance.net/v2';

  const ENDPOINT_PROJECTS = '/projects';
  const ENDPOINT_USERS    = '/users';
  const ENDPOINT_WIPS     = '/wips';

  const TIMEOUT_DEFAULT_SEC = 30;

  protected $_api_id, $_api_key, $_debug;


  /**
   * Information can be found @ http://www.behance.net/dev/apps
   *
   * @param int    $api_id
   * @param string $api_key
   *
   * @param bool   $debug: OUTPUTS failures if they occur (non-2xx responses)
   */
  public function __construct( $api_id, $api_key, $debug = false ) {

    if ( !extension_loaded( 'curl' ) )
      throw new Exception( "cURL module is required" );

    $this->_api_id  = $api_id;
    $this->_api_key = $api_key;

    $this->_debug   = $debug;

  } // __construct


  /**
   * Retrieves a full Project, by ID
   *
   * @param int $id
   *
   * @return stdClass|bool: false on failure
   */
  public function getProject( $id ) {

    $endpoint = static::ENDPOINT_PROJECTS . '/' . $id;

    return $this->_getDecodedJson( $endpoint, array(), 'project' );

  } // getProject


  /**
   * Retrieves a list of a projects comments, by project ID
   *
   * @param int $id
   *
   * @return array|bool: array of stdClass objects, false on failure
   */
  public function getProjectComments( $id ) {

    $endpoint = static::ENDPOINT_PROJECTS . "/{$id}/comments";

    return $this->_getDecodedJson( $endpoint, array(), 'comments' );

  } // getProjectComments


  /**
   * Retrieves a full User, based on either their ID or Username
   *
   * @param int|string $id_or_username
   *
   * @return stdClass|bool: false on failure
   */
  public function getUser( $id_or_username ) {

    $endpoint = static::ENDPOINT_USERS . '/' . $id_or_username;

    return $this->_getDecodedJson( $endpoint, array(), 'user' );

  } // getUser


  /**
   * Retrieves a list of $id_or_username's projects
   *
   * @param int|string $id_or_username
   *
   * @return array|bool: array of stdClass objects, false on failure
   */
  public function getUserProjects( $id_or_username ) {

    $endpoint = static::ENDPOINT_USERS . '/' . $id_or_username . '/projects';

    return $this->_getDecodedJson( $endpoint, array(), 'projects' );

  } // getUserProjects


  /**
   * Retrieves a list of $id_or_username's works in progress
   *
   * @param int|string $id_or_username
   *
   * @return array|bool: array of stdClass objects, false on failure
   */
  public function getUserWips( $id_or_username ) {

    $endpoint = static::ENDPOINT_USERS . '/' . $id_or_username . '/wips';

    return $this->_getDecodedJson( $endpoint, array(), 'wips' );

  } // getUserWips


  /**
   * Retrieves a full Work In Progress, by ID
   *
   * @param int $id
   *
   * @return stdClass|bool: false on failure
   */
  public function getWorkInProgress( $id ) {

    $endpoint = static::ENDPOINT_WIPS . '/' . $id;

    return $this->_getDecodedJson( $endpoint, array(), 'wip' );

  } // getWorkInProgress


  /**
   * Search projects, by these $params
   *
   * @param array $params: if empty defaults to featured projects
   *
   * @return array|bool: array of stdClass objects, false on failure
   */
  public function searchProjects( array $params = array() ) {

    $endpoint = static::ENDPOINT_PROJECTS;

    return $this->_getDecodedJson( $endpoint, $params, 'projects' );

  } // searchProjects


  /**
   * Search users, by these $params
   *
   * @param array $params: if empty defaults to featured users
   *
   * @return array|bool: array of stdClass objects, false on failure
   */
  public function searchUsers( array $params = array() ) {

    $endpoint = static::ENDPOINT_USERS;

    return $this->_getDecodedJson( $endpoint, $params, 'users' );

  } // searchUsers


  /**
   * Search works in progress, by these $params
   *
   * @param array $params: if empty defaults to featured works in progress
   *
   * @return array|bool: array of stdClass objects, false on failure
   */
  public function searchWips( array $params = array() ) {

    $endpoint = static::ENDPOINT_WIPS;

    return $this->_getDecodedJson( $endpoint, $params, 'wips' );

  } // searchWips




  /**
   * Automates retrieval data from $endpoint, using $query_params, and returns stdClass based on presence of $root_node
   *
   * @param string $endpoint    : API segment to retrieve
   * @param array $query_params : anything additional to add to the query string, in key => value form
   * @param string $root_node   : first object property of JSON response object where the data is attached
   *
   * @return stdClass|bool
   */
  protected function _getDecodedJson( $endpoint, array $query_params, $root_node ) {

    $entity = $this->_get( $endpoint, $query_params );

    if ( empty( $entity ) )
      return false;

    $entity = json_decode( $entity );

    return ( empty( $entity->{$root_node} ) )
           ? false
           : $entity->{$root_node};

  } // _getDecodedJson


  /**
   * Performs a GET request, isolates caller from exceptions
   *
   * @param string $endpoint     : just the segment of the API the request
   * @param array  $query_params : anything additional to add to the query string, in key => value form
   *
   * @return string|bool: response body on success, false on failure
   */
  protected function _get( $endpoint, array $query_params = array() ) {

    $full_url = $this->_makeFullURL( $endpoint, $query_params );
    $results  = false;

    try {

      return $this->_executeRequest( 'GET', $full_url );

    } // try

    catch( Exception $e ) {

      if ( $this->_debug )
        echo ( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );

      return false;

    } // catch

  } // _get


  /**
   * Generates a fully-quality API url, with $endpoint + $query_params, automatically adds in app's key
   *
   * @param string $endpoint     : segment of the API being accessed
   * @param array  $query_params : anything additional to add to the query string, in key => value form
   */
  protected function _makeFullURL( $endpoint, array $query_params = array() ) {

    $query_params['api_key'] = $this->_api_key;

    $query_string = '?' . http_build_query( $query_params );
    $full_url     = static::API_ROOT . $endpoint . $query_string;

    return $full_url;

  } // _makeFullURL


  /**
   * Makes a remote request to $url
   *
   * @throws Exception: on any non-200 response from request
   *
   * @param string       $method        : HTTP verb of request (get|post|post|delete|head)
   * @param string       $url           : fully-qualified destination of request
   * @param string|array $request_body  : sent as HTTP body of request
   * @param array        $curl_params   : parameters to override for cURL library (timeouts, user agent, etc)
   *
   * @return string: response body
   */
  protected function _executeRequest( $method, $url, $request_body = false, $curl_params = array() ) {

    $user_agent          = "Behance API/PHP (App {$this->_api_id})";
    $default_curl_params = array(
        CURLOPT_HTTPHEADER      => array( 'Accept: application/json', 'Content-Type: application/json', 'Expect:' ),
        CURLOPT_TIMEOUT         => static::TIMEOUT_DEFAULT_SEC,
        CURLOPT_USERAGENT       => $user_agent,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_BINARYTRANSFER  => true,
        CURLOPT_HEADER          => true,
    );

    // Replace recursive will knock this *into* an array afterwards
    if ( !empty( $curl_params[ CURLOPT_HTTPHEADER ] ) )
      unset( $default_curl_params[ CURLOPT_HTTPHEADER ] );


    $curl_params = array_replace_recursive( $default_curl_params, $curl_params );
    $method      = strtoupper( $method );

    switch ( $method ) {

      case 'GET':

        $curl_params[ CURLOPT_HTTPGET ] = true;
        $curl_params[ CURLOPT_POST ]    = false;
        break;

      case 'POST':

        $curl_params[ CURLOPT_HTTPGET ] = false;
        $curl_params[ CURLOPT_POST ]    = true;

        // IMPORTANT: Since @ is used to signify files in arrays passed to this option,
        // pre-encode this array to prevent this from attempting to read a file
        if ( is_array( $request_body ) )
          $request_body = http_build_query( $request_body );

        $curl_params[ CURLOPT_HTTPHEADER ][] = 'Content-Length: ' . strlen( $request_body );
        $curl_params[ CURLOPT_POSTFIELDS ] = $request_body;
        break;

      case 'PUT':

        $curl_params[ CURLOPT_HTTPGET ]       = false;
        $curl_params[ CURLOPT_POST ]          = false;
        $curl_params[ CURLOPT_CUSTOMREQUEST ] = 'PUT';

        // IMPORTANT: Since @ is used to signify files in arrays passed to this option,
        // pre-encode this array to prevent this from attempting to read a file
        if ( is_array( $request_body ) )
          $request_body = http_build_query( $request_body );

        $curl_params[ CURLOPT_HTTPHEADER ][] = 'Content-Length: ' . strlen( $request_body );
        $curl_params[ CURLOPT_POSTFIELDS ]   = $request_body;
        break;


      case 'DELETE':

        $curl_params[ CURLOPT_HTTPGET ]       = false;
        $curl_params[ CURLOPT_CUSTOMREQUEST ] = 'DELETE';

        // IMPORTANT: Since @ is used to signify files in arrays passed to this option,
        // pre-encode this array to prevent this from attempting to read a file
        if ( is_array( $request_body ) )
          $request_body = http_build_query( $request_body );

        $curl_params[ CURLOPT_POSTFIELDS ] = $request_body;
        break;

      default:
        throw new Exception( "Unhandled method: [{$method}]" );

    } // switch method

    $ch = curl_init( $url );

    curl_setopt_array( $ch, $curl_params );


    ////////// MAKE THE REQUEST /////////
    $request_response = curl_exec( $ch );
    /////////////////////////////////////


    $request_info  = curl_getinfo( $ch );
    $response_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $curl_code     = curl_errno( $ch );
    $error_message = curl_error( $ch ); // Maintain if necessary, since the connection is closed


    curl_close( $ch );

    $header        = substr( $request_response, 0, $request_info['header_size'] );
    $response_body = false;


    if ( $request_info['download_content_length'] <= 0 ) {

      $exploded = explode( "\r\n\r\n", $request_response );

      while ( $exploded[0] == 'HTTP/1.1 100 Continue' )
        array_shift( $exploded );


      $response_body = ( isset( $exploded[1] ) )
                       ? $exploded[1]
                       : '';

    } // if download_content_length = 0

    else {

      $response_body = substr( $request_response, -$request_info['download_content_length'] );

    } // else


    // Unless array_shift completely solves headers in body problem, leave this line in
    if ( substr( $response_body, 0, 4 ) == 'HTTP' )
      throw new Exception( "Malformed response_body: " . var_export( $response_body, 1 ) );


    // @throws Exception on response non-2xx (success) responses from service
    if ( (int)round( $response_code, -2 ) !== 200 )
      throw new Exception( "Unsuccessful Request, response ({$response_code}): " . ( empty( $response_body ) ? '' : ": {$response_body} " ) );


    return $response_body;


  } // _executeRequest


} // Be_Api

