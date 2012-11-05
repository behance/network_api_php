<?php
/**
 * Basic Behance Network API implementation to
 * accessing Users, Projects and Works in Progress data
 *
 * Register your application FIRST: http://be.net/dev
 *
 *
 * @author   Bryan Latten <bryan@behance.com>
 * @link     http://be.net/dev
 *
 */
class Be_Api {

  const ENDPOINT_PROJECTS     = '/projects';
  const ENDPOINT_USERS        = '/users';
  const ENDPOINT_WIPS         = '/wips';
  const ENDPOINT_COLLECTIONS  = '/collections';
  const ENDPOINT_ACTIVITY     = '/activity';
  const ENDPOINT_AUTHENTICATE = '/oauth/authenticate';
  const ENDPOINT_TOKEN        = '/oauth/token';


  const TIMEOUT_DEFAULT_SEC  = 30;
  const VALID                = 1;
  const INVALID              = 0;

  protected $_api_root       = 'https://net.dev13.be.lan/v2';

  protected $_api_id,
            $_client_id,
            $_client_secret,
            $_debug,
            $_access_token,
            $_state;

  /**
   * Information can be found @ http://www.behance.net/dev/apps
   *
   * @param int    $api_id
   * @param string $api_key
   *
   * @param bool   $debug: OUTPUTS failures if they occur (non-2xx responses)
   */
  public function __construct( $client_id, $client_secret, $debug = false ) {

    if ( !extension_loaded( 'curl' ) )
      throw new Exception( "cURL module is required" );

    //$this->_api_id        = $api_id;
    $this->_client_id     = $client_id;
    $this->_client_secret = $client_secret;
    $this->_debug         = $debug;
    $this->_state         = uniqid();

  } // __construct

  /**
   * Redirects user to the Behance login page to accept/reject application permissions
   * User will be redirected with code that can be exchanged for a token
   *
   * @param  string $redirect_uri: Uri user will be redirected to after accepting/rejection permissions  
   */
  public function authenticate( $redirect_uri, $scope ) {

    $query_params = array(
        'client_id'    => $this->_client_id,
        'redirect_uri' => $redirect_uri,
        'state'        => $this->_state,
        'scope'        => $scope
    );
    
    $url = $this->_makeFullURL( self::ENDPOINT_AUTHENTICATE, $query_params  );
    
    $this->_redirect( $url );

  } // redirect

  /**
   * Makes code exchange for token
   *
   * @param  string $code         : Encrypted code to be exchanged for token
   * @param  string $redirect_uri : Uri user will be redirected to after accepting/rejection permissions
   * @param  string $grant_type   
   * 
   * @return string               : Authentication token
   */
  public function exchangeCodeForToken( $code, $redirect_uri, $grant_type = '' ){

    $query_params = array(
        'client_id'     => $this->_client_id,
        'client_secret' => $this->_client_secret,
        'redirect_uri'  => $redirect_uri,
        'state'         => $this->_state,
        'code'          => $code
    );

    if ( !empty( $grant_type ) ) 
      $query_params['grant_type'] = $grant_type;

    $response = json_decode( $this->_post( self::ENDPOINT_TOKEN, $query_params ) );

    if ( empty( $response ) || $response->valid == self::INVALID ) {
      
      $errors = ( empty( $response->errors ) )
                ? 'Could not get token'
                : $response->errors;

      throw new Exception( $errors );
    
    } // if invalid token
     
    $this->_access_token = $response->access_token;

  } // token

  public function getAccessToken() {
    
    return $this->_access_token;
  
  } // getToken

  public function setAccessToken( $access_token ) {

    $this->access_token = $access_token;

  } // setAccessToken

  /**
   * Retrieves a full Project, by ID
   *
   * @param int  $id    : which project to retrieve
   * @param bool $assoc : return object will be converted to an associative array
   *
   * @return array|stdClass|bool: array or stdClass based on $assoc, false on failure
   */
  public function getProject( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS . '/' . $id;

    return $this->_getDecodedJson( $endpoint, array(), 'project', $assoc );

  } // getProject


  /**
   * Retrieves a list of a projects comments, by project ID
   *
   * @param int  $id    : which project to retrieve comments for
   * @param bool $assoc : return objects will be converted to associative arrays
   *
   * @return array : stdClass objects or associative arrays, based on $assoc
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
   * @param int|string $id_or_username : who to retrieve
   * @param bool $assoc                : return object will be converted to an associative array
   *
   * @return array|stdClass|bool: array or stdClass based on $assoc, false on failure
   */
  public function getUser( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username;

    return $this->_getDecodedJson( $endpoint, array(), 'user', $assoc );

  } // getUser


  /**
   * Retrieves a list of $id_or_username's projects
   *
   * @param int|string $id_or_username : user's projects to search
   * @param bool       $assoc          : return objects will be converted to associative arrays
   *
   * @return array : stdClass objects or associative arrays, based on $assoc
   */
  public function getUserProjects( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/projects';
    $results  = $this->_getDecodedJson( $endpoint, array(), 'projects', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserProjects


  /**
   * Retrieves a list of projects that $id_or_username has appreciated
   *
   * @param int|string $id_or_username : user's projects to search
   * @param bool       $assoc          : return objects will be converted to associative arrays
   *
   * @return array : stdClass objects or associative arrays, based on $assoc
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
   * Retrieves a list of $id_or_username's works in progress
   *
   * @param int|string $id_or_username : user's works in progress to search
   * @param bool       $assoc          : return objects will be converted to associative arrays
   *
   * @return array : stdClass objects or associative arrays, based on $assoc
   */
  public function getUserWips( $id_or_username, $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/wips';
    $results  = $this->_getDecodedJson( $endpoint, array(), 'wips', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserWips


  public function getUserActivity( $assoc = false ) {

    $method   = 'POST';
    
    $endpoint = self::ENDPOINT_ACTIVITY ;
    
    $params['access_token'] = $this->_access_token;

    $results = $this->_getDecodedJson( $endpoint, $params, 'activity', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;
  
  } //getUserActivity

  /**
   * Retrieves a full Work In Progress, by ID
   *
   * @param int  $id
   * @param bool $assoc : return object will be converted to an associative array
   *
   * @return array|stdClass|bool: array or stdClass based on $assoc, false on failure
   */
  public function getWorkInProgress( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $id;

    return $this->_getDecodedJson( $endpoint, array(), 'wip', $assoc );

  } // getWorkInProgress


  /**
   * Retrieves a collcetion, by ID
   *
   * @param int  $id    : which collection to retrieve
   * @param bool $assoc : return object will be converted to an associative array
   *
   * @return array|stdClass|bool: array or stdClass based on $assoc, false on failure
   */
  public function getCollection( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id;

    return $this->_getDecodedJson( $endpoint, array(), 'collection', $assoc );

  } // getCollection


  /**
   * Retrieves a list of collection $id's projects
   *
   * @param int|string $id    : collection's projects to search
   * @param bool       $assoc : return objects will be converted to associative arrays
   *
   * @return array : stdClass objects or associative arrays, based on $assoc
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
   * @param int|string $id_or_username : user's works in progress to search
   * @param bool       $assoc          : return objects will be converted to associative arrays
   *
   * @return array : stdClass objects or associative arrays, based on $assoc
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
   * Search projects, by these $params
   *
   * @param array $params : if empty defaults to featured projects
   * @param bool  $assoc  : return objects will be converted to associative arrays
   *
   * @return array        : stdClass objects or associative arrays, based on $assoc
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
   * @param array $params : if empty defaults to featured users
   * @param bool  $assoc  : return objects will be converted to associative arrays
   *
   * @return array        : stdClass objects or associative arrays, based on $assoc
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
   * @param array $params : if empty defaults to featured works in progress
   * @param bool  $assoc  : return objects will be converted to associative arrays
   *
   * @return array        : stdClass objects or associative arrays, based on $assoc
   */
  public function searchWips( array $params = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS;
    $results  = $this->_getDecodedJson( $endpoint, $params, 'wips', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // searchWips


  /**
   * Search collections, by these $params
   *
   * @param array $params : if empty defaults to featured collections
   * @param bool  $assoc  : return objects will be converted to associative arrays
   *
   * @return array        : stdClass objects or associative arrays, based on $assoc
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
   * Automates retrieval data from $endpoint, using $query_params, and returns stdClass based on presence of $root_node
   *
   * @param string $endpoint     : API segment to retrieve
   * @param array  $query_params : anything additional to add to the query string, in key => value form
   * @param string $root_node    : first object property of JSON response object where the data is attached
   * @param bool   $assoc        : when TRUE, returned objects will be converted into associative array
   *
   * @return stdClass|bool
   */
  protected function _getDecodedJson( $endpoint, array $query_params, $root_node, $assoc, $method = 'GET' ) {

    $method = '_' . strtolower( $method );
    $entity = $this->$method( $endpoint, $query_params );

    if ( empty( $entity ) )
      return false;

    $entity = json_decode( $entity, $assoc );

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
    echo $full_url;
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
   * Performs a POST request, isolates caller from exceptions
   *
   * @param string $endpoint     : just the segment of the API the request
   * @param array  $query_params : anything additional to add to the query string, in key => value form
   *
   * @return string|bool: response body on success, false on failure
   */
  protected function _post( $endpoint, array $query_params = array() ) {

    $full_url = $this->_makeFullURL( $endpoint );
    
    $results  = false;

    try {

      return $this->_executeRequest( 'POST', $full_url, $query_params );

    } // try

    catch( Exception $e ) {

      if ( $this->_debug )
        echo ( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );

      return false;

    } // catch

  } // _post

  /**
   * Generates a fully-quality API url, with $endpoint + $query_params, automatically adds in app's key
   *
   * @param string $endpoint     : segment of the API being accessed
   * @param array  $query_params : anything additional to add to the query string, in key => value form
   */
  protected function _makeFullURL( $endpoint, array $query_params = array() ) {

    $query_params['client_id'] = $this->_client_id;

    $query_string = '?' . http_build_query( $query_params );
    $full_url     = $this->_api_root . $endpoint . $query_string;

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
        CURLOPT_HTTPHEADER      => array( 'Accept: application/json', 'Content-Type: application/x-www-form-urlencoded', 'Expect:' ),
        CURLOPT_TIMEOUT         => self::TIMEOUT_DEFAULT_SEC,
        CURLOPT_USERAGENT       => $user_agent,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_BINARYTRANSFER  => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false
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
  
  /**
   * Redirects user to specified url 
   * 
   * @param  string $location : Url to redirect user
   */
  protected function _redirect( $location ) {

    header( "Location: {$location}" );
    exit;
  
  } // _redirect


} // Be_Api

+