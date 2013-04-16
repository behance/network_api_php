<?php

require_once( 'Exception.php' );

/**
 * Basic Behance Network API implementation to
 * accessing Users, Projects and Works in Progress data
 *
 * Register your application FIRST: http://be.net/dev
 *
 *
 * @author   Bryan Latten  <bryan@behance.com>
 * @author   Michael Klein <michael.klein@behance.com>
 * @link     http://be.net/dev
 *
 */
class Be_Api {

  const ENDPOINT_PROJECTS      = '/projects';
  const ENDPOINT_USERS         = '/users';
  const ENDPOINT_WIPS          = '/wips';
  const ENDPOINT_COLLECTIONS   = '/collections';
  const ENDPOINT_ACTIVITY      = '/activity';
  const ENDPOINT_AUTHENTICATE  = '/oauth/authenticate';
  const ENDPOINT_TOKEN         = '/oauth/token';
  const ENDPOINT_FIELDS        = '/fields';

  const TIMEOUT_DEFAULT_SEC    = 30;
  const VALID                  = 1;
  const INVALID                = 0;

  protected $_api_root         = 'https://www.behance.net/v2';
  protected $_access_token_key = 'access_token';

  protected $_client_id,
            $_client_secret,
            $_debug,
            $_access_token,
            $_user;

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
      throw new Be_Exception( "cURL module is required" );

    $this->_client_id     = $client_id;
    $this->_client_secret = $client_secret;
    $this->_debug         = $debug;


  } // __construct

  /**
   * Redirects user to the Behance login page to accept/reject application permissions, then EXITS.
   * User will be returned to redirect_uri with code in query string parameters that
   * can be exchanged for a token, if successful.
   *
   * @see http://www.behance.net/dev/authentication#step-by-step
   *
   * @param  string $redirect_uri  Location user's browser will be redirected after accepting/rejection permissions
   * @param  string $scopes        Permissions to request from the user [http://www.behance.net/dev/authentication#scopes]
   * @param  string $state         application XSS-check, will be part of redirect_uri query parameters on success
   */
  public function authenticate( $redirect_uri, array $scopes, $state = '' ) {

    $query_params = array(
        'client_id'    => $this->_client_id,
        'redirect_uri' => $redirect_uri,
        'scope'        => implode( '|', $scopes ) // Standard oAuth parameter is scope, even though they are plural
    );

    $query_params['state'] = ( empty( $state ) )
                             ? uniqid()
                             : $state;

    $url = $this->_makeFullURL( self::ENDPOINT_AUTHENTICATE, $query_params  );

    $this->_redirect( $url );

  } // authenticate

  /**
   * Makes code exchange for token, sets $this->_user + $this->_access_token
   *
   * @see http://www.behance.net/dev/authentication#step-by-step
   *
   * @param  string $code          Encrypted code to be exchanged for token
   * @param  string $redirect_uri  Uri user will be redirected to after accepting/rejection permissions
   * @param  string $state         state value
   * @param  string $grant_type    Authorization grant type
   *
   * @return string                Authentication token
   */
  public function exchangeCodeForToken( $code, $redirect_uri, $state = '',  $grant_type = '' ) {

    $query_params = array(
        'client_id'     => $this->_client_id,
        'client_secret' => $this->_client_secret,
        'redirect_uri'  => $redirect_uri,
        'code'          => $code
    );

    $query_params['state'] = ( empty( $state ) )
                             ? uniqid()
                             : $state;

    if ( !empty( $grant_type ) )
      $query_params['grant_type'] = $grant_type;

    $response = json_decode( $this->_post( self::ENDPOINT_TOKEN, array(), $query_params ) );

    if ( empty( $response ) || $response->valid == self::INVALID ) {

      $errors = ( empty( $response->errors ) )
                ? 'Could not get token'
                : $response->errors;

      throw new Be_Exception( $errors );

    } // if invalid token

    $this->setAccessToken( $response->access_token );
    $this->setAuthenticatedUser( $response->user );

    return $this->_access_token;

  } // exchangeCodeForToken

  /**
   * Pulls the access_token that had been previously set, either manually or through a previous authentication
   */
  public function getAccessToken() {

    return $this->_access_token;

  } // getToken

  /**
   * After authenticating with the oAuth API, store the access_token before making authenticated requests
   *
   * @param string $access_token
   */
  public function setAccessToken( $access_token ) {

    $this->_access_token = $access_token;

  } // setAccessToken

  /**
   * Sets the user object from the token authentication response to be reused
   *
   * @param Object $user
   */
  public function setAuthenticatedUser( $user ) {

    $this->_user = $user;

  } // setAuthenticatedUser

  /**
   * When available, returns the currently authenticated user object
   *
   * @return Object|null
   */
  public function getAuthenticatedUser() {

    return $this->_user;

  } // getAuthenticatedUser

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
   * @param array     $params           search parameters ex. [ per_page => 5, page => 2 ]
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

    if ( !empty( $this->_access_token ) )
      $options[ $this->_access_token_key ] = $this->_access_token;

    $results = $this->_getDecodedJson( $endpoint, $options, 'followers', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserFollowers

  /**
   * Retrieves a list of users in the given user's feedback circle
   *
   * @param  int|string $id_or_username : user
   * @param  bool       $assoc          : return objects will be converted to associative arrays
   * @param  array      $options        : search options
   *
   * @return array                       stdClass objects or associative arrays, based on $assoc
   */
  public function getUserFeedbackCircle( $id_or_username, $options = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_USERS . '/' . $id_or_username . '/feedback';

    $results  = $this->_getDecodedJson( $endpoint, $options, 'feedback_circlezz', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getUserFeedbackCircle

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
   * Create new Work in Progress ( WIP )
   *
   * @param  string          $image_path   full image path
   * @param  string          $title        title of WIP
   * @param  array           $tags         tags assoicated with wip
   * @param  string          $description  description of wip
   * @param  boolean         $assoc        return objects will be converted to associative arrays
   *
   * @return array                         stdClass objects or associative arrays, based on $assoc
   */
  public function createUserWip( $image_path, $title, array $tags, $description = '', $assoc = false ) {

    $endpoint                                = self::ENDPOINT_WIPS;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $post_body['tags']         = implode( '|', $tags );
    $post_body['image']        = '@' . $image_path;
    $post_body['title']        = $title;
    $post_body['description']  = $description;


    $curl_params[ CURLOPT_HTTPHEADER ] = array( 'Content-Type: multipart/form-data' );

    $response = $this->_post( $endpoint, $query_params, $post_body, $curl_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // createUserWip

  /**
   * Create new Work in Progress ( WIP ) revision
   *
   * @param  int             $wip_id       wip id
   * @param  string          $image_path   full image path
   * @param  string          $title        title of WIP
   * @param  array           $tags         tags assoicated with wip revision
   * @param  string          $description  description of wip revision
   * @param  boolean $assoc         return objects will be converted to associative arrays
   *
   * @return array                  stdClass objects or associative arrays, based on $assoc
   */
  public function createUserWipRevision( $wip_id, $image_path, $title, array $tags, $description = '', $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $post_body['tags']         = implode( '|', $tags );
    $post_body['image']        = '@' . $image_path;
    $post_body['title']        = $title;
    $post_body['description']  = $description;


    $curl_params[ CURLOPT_HTTPHEADER ] = array( 'Content-Type: multipart/form-data' );

    $response = $this->_post( $endpoint, $query_params, $post_body, $curl_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // createUserWipRevision

  /**
   * Update WIP description
   *
   * @param  int     $wip_id        WIP ID
   * @param  int     $revision_id   revision id
   * @param  string  $description   WIP description
   * @param  boolean $assoc         return objects will be converted to associative arrays
   *
   * @return array                  stdClass objects or associative arrays, based on $assoc
   */
  public function updateUserWipRevisionDescription( $wip_id, $revision_id, $description, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $wip_id . '/' . $revision_id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $put_body['description']      = $description;

    $response = $this->_put( $endpoint, $query_params, $put_body );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } //updateUserWipRevisionDescription

  /**
   * Update WIP title
   *
   * @param  string  $wip_id        WIP to be updated
   * @param  string  $title         WIP title
   * @param  boolean $assoc         return objects will be converted to associative arrays
   *
   * @return array                  stdClass objects or associative arrays, based on $assoc
   */
  public function updateUserWipTitle( $wip_id, $title, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $wip_id ;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $put_body['title']            = $title;

    $response = $this->_put( $endpoint, $query_params, $put_body );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // updateUserWipTitle

  /**
   * Update WIP revision tags
   *
   * @param  string  $wip_id       WIP to be updated
   * @param  string  $revision_id  WIP revision to be updated
   * @param  array   $tags         WIP revision tags
   * @param  boolean $assoc        return objects will be converted to associative arrays
   *
   * @return array                 stdClass objects or associative arrays, based on $assoc
   */
  public function updateUserWipRevisionTags( $wip_id, $revision_id, array $tags, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $wip_id . '/' . $revision_id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $put_body['tags']             = implode( '|', $tags );

    $response = $this->_put( $endpoint, $query_params, $put_body );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } //updateUserWipRevisionTags

  /**
   * Post WIP comment
   *
   * @param  string  $wip_id       WIP to comment on
   * @param  string  $revision_id  WIP revision to comment on
   * @param  string  $comment      comment text
   * @param  boolean $assoc        return objects will be converted to associative arrays
   *
   * @return array                 stdClass objects or associative arrays, based on $assoc
   */
  public function postWipComment( $wip_id, $revision_id, $comment, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $id . '/' . $revision_id . '/comments';

    $query_params[ $this->_access_token_key ] = $this->_access_token;
    $body_params['comment']       = $comment;

    $response = $this->_post( $endpoint, $query_params, $body_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // postProjectComment

  /**
   * Delete WIP revision
   *
   * @param  string  $wip_id      WIP to be deleted
   * @param  string  $revision_id revision to be deleted
   * @param  boolean $assoc       return objects will be converted to associative arrays
   *
   * @return array                 stdClass objects or associative arrays, based on $assoc
   */
  public function deleteUserWipRevision( $wip_id, $revision_id, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $wip_id . '/' . $revision_id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response = $this->_delete( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } //deleteUserWipRevision

  /**
   * Delete WIP
   *
   * @param  string  $wip_id      WIP to be deleted
   * @param  boolean $assoc       return objects will be converted to associative arrays
   *
   * @return array                stdClass objects or associative arrays, based on $assoc
   */
  public function deleteUserWip( $wip_id, $assoc = false ) {

    $endpoint = self::ENDPOINT_WIPS . '/' . $wip_id ;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response = $this->_delete( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } //deleteUserWip

  /**
   * Get user's activity feed
   *
   * @param  int|bool $offset_ts used for paging, timestamp received as "earliest_ts" in the previous feed request if "has_more" is true
   * @param  boolean $assoc      return objects will be converted to associative arrays
   *
   * @return array          stdClass objects or associative arrays, based on $assoc
   */
  public function getUserActivity( $offset_ts = false, $assoc = false ) {

    $endpoint                         = self::ENDPOINT_ACTIVITY ;

    $params['offset_ts']              = $offset_ts;
    $params[ $this->_access_token_key ] = $this->_access_token;

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
   * Create new collection and add projects
   *
   * @param  string  $title        collection title
   * @param  array   $project_ids  projects to add to collection
   * @param  boolean $assoc        return objects will be converted to associative arrays
   *
   * @return array                 stdClass objects or associative arrays, based on $assoc
   */
  public function createCollection( $title, $project_ids = array(), $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS;

    $query_params[ $this->_access_token_key ] = $this->_access_token;
    $body_params['title']         = $title;

    if ( !empty( $project_ids ) )
      $body_params['projects'] = implode( '|', $project_ids );


    $response = $this->_post( $endpoint, $query_params, $body_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // createColletion

  /**
   * Delete collection
   *
   * @param  int     $id     which collection to delete
   * @param  boolean $assoc  return objects will be converted to associative arrays
   *
   * @return array           stdClass objects or associative arrays, based on $assoc
   */
  public function deleteCollection( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response = $this->_delete( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // deleteCollection

  /**
   * Update collection
   *
   * @param int     $id     which collection to retrieve
   * @param string  $title  new collection title
   * @param boolean $assoc  return objects will be converted to associative arrays
   *
   * @return array          stdClass objects or associative arrays, based on $assoc
   */
  public function updateCollection( $id, $title, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;
    $body_params['title']         = $title;

    $response = $this->_put( $endpoint, $query_params, $body_params );

     return ( empty( $response ) )
            ? false
            : json_decode( $response, $assoc );

  } // updateCollection

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
   * Add projects to collection
   *
   * @param int     $id           collection to add projects
   * @param array   $project_ids  projects to add to collection
   * @param boolean $assoc        return objects will be converted to associative arrays
   *
   * @return array                stdClass objects or associative arrays, based on $assoc
   */
  public function addProjectsToCollection( $id, array $project_ids, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id . '/projects';

    $projects = implode( '|', $project_ids );

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $body_params['projects']      = $projects;


    $response = $this->_post( $endpoint, $query_params, $body_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // addProjectsToCollection

  /**
   * Remove project from collection
   *
   * @param int        $id         collection to remove project
   * @param int        $project_id project to remove from collection
   * @param boolean    $assoc      return objects will be converted to associative arrays
   *
   * @return array                  stdClass objects or associative arrays, based on $assoc
   */
  public function removeProjectFromCollection(  $id, $project_id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id . '/projects/' . $project_id;

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response = $this->_delete( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // removeProjectFromCollection

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

  } //getUserStats

  /**
   * View project
   * - increments project view counter
   * - returns user appreciation info for given project
   *
   * @param  int  $id     project id
   * @param  bool $assoc  return objects will be converted to associative arrays
   *
   * @return array        stdClass objects or associative arrays, based on $assoc
   */
  public function viewProject( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS . '/' . $id . '/view';

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response  = $this->_post( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // viewProject

  /**
   * Appreciate project
   *
   * @param  int   $id     project to be appreciated
   * @param  bool  $assoc  return objects will be converted to associative arrays
   *
   * @return array         stdClass objects or associative arrays, based on $assoc
   */
  public function appreciateProject( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS . '/' . $id . '/appreciate';

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response  = $this->_post( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // appreciateProject

  /**
   * Post project comment
   *
   * @param  int    $id       project to post comment
   * @param  string $comment  comment to post
   * @param  bool   $assoc    return objects will be converted to associative arrays
   *
   * @return array            stdClass objects or associative arrays, based on $assoc
   */
  public function postProjectComment( $id, $comment, $assoc = false ) {

    $endpoint = self::ENDPOINT_PROJECTS . '/' . $id . '/comments';

    $query_params[ $this->_access_token_key ] = $this->_access_token;
    $body_params['comment']       = $comment;

    $response = $this->_post( $endpoint, $query_params, $body_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // postProjectComment

  /**
   * Follow collection
   *
   * @param int   $id     collection to follow
   * @param bool  $assoc  return objects will be converted to associative arrays
   *
   * @return array        stdClass objects or associative arrays, based on $assoc
   */
  public function followCollection( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id . '/follow';

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response  = $this->_post( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // followCollection

  /**
   * Unfollow collection
   *
   * @param int  $id     collection to unfollow
   * @param bool $assoc  return objects will be converted to associative arrays
   *
   * @return array       stdClass objects or associative arrays, based on $assoc
   */
  public function unfollowCollection( $id, $assoc = false ) {

    $endpoint = self::ENDPOINT_COLLECTIONS . '/' . $id . '/follow';

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response  = $this->_delete( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // unfollowCollection

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

    $results  = $this->_getDecodedJson( $endpoint, $params, 'wips', $assoc );

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
   * Follow user
   *
   * @param int|string $id_or_username  user to follow
   * @param bool       $assoc           return objects will be converted to associative arrays
   *
   * @return array                      stdClass objects or associative arrays, based on $assoc
   */
  public function followUser( $id_or_username, $assoc =  false ) {

    $endpoint = self::ENDPOINT_USERS . "/{$id_or_username}/follow";

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response  = $this->_post( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // followUser

  /**
   * Unfollow user
   *
   * @param int|string $id_or_username  user to unfollow
   * @param bool       $assoc           return objects will be converted to associative arrays
   *
   * @return array                      stdClass objects or associative arrays, based on $assoc
   */
  public function unfollowUser( $id_or_username, $assoc =  false ) {

    $endpoint = self::ENDPOINT_USERS . "/{$id_or_username}/follow";

    $query_params[ $this->_access_token_key ] = $this->_access_token;

    $response  = $this->_delete( $endpoint, $query_params );

    return ( empty( $response ) )
           ? false
           : json_decode( $response, $assoc );

  } // unfollowUser

  /**
   * Get creative fields
   *
   * @param bool    $assoc  return objects will be converted to associative arrays
   *
   * @return array          stdClass objects or associative arrays, based on $assoc
   */
  public function getFields( $assoc =  false ) {

    $endpoint = self::ENDPOINT_FIELDS;

    $results  = $this->_getDecodedJson( $endpoint, array(), '', $assoc );

    // IMPORTANT: Ensure this will always return an array
    return ( empty( $results ) )
           ? array()
           : $results;

  } // getFields

  /**
   * Change the URL root of the Behance API, mostly for testing purposes
   *
   * @param string $url  protocol + fully qualified domain to use instead of https://www.behance.net/v2
   */
  public function setApiRoot( $url ) {

    // IMPORTANT: Since each segment appended to this starts with a slash, don't end with a slash
    $this->_api_root = rtrim( $url, '/' );

  } // setApiRoot

  /**
   * Set access token key
   *
   * @param string $key
   */
  public function setAccessTokenKey( $key ) {

    $this->_access_token_key = $key;

  } // setAccessTokenKey

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

    if ( empty( $entity ) )
      return false;

    $entity = json_decode( $entity, $assoc );

    if ( !$root_node )
      return $entity;

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
   * @param string $endpoint      just the segment of the API the request
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   *
   * @return string|bool          response body on success, false on failure
   */
  protected function _get( $endpoint, array $query_params = array() ) {

    $full_url = $this->_makeFullURL( $endpoint, $query_params );

    $results  = false;

    try {

      return $this->_executeRequest( 'GET', $full_url );

    } // try

    catch( Be_Exception $e ) {

      if ( $this->_debug )
        echo ( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );

      return false;

    } // catch

  } // _get

  /**
   * Performs a POST request, isolates caller from exceptions
   *
   * @param string $endpoint      just the segment of the API the request
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   *
   * @return string|bool          response body on success, false on failure
   */
  protected function _post( $endpoint, array $query_params = array(), $post_body = array(), $curl_params= array() ) {

    $full_url = $this->_makeFullURL( $endpoint, $query_params );

    $results  = false;

    try {

      return $this->_executeRequest( 'POST', $full_url, $post_body, $curl_params );

    } // try

    catch( Be_Exception $e ) {

      if ( $this->_debug )
        echo ( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );

      return false;

    } // catch

  } // _post

  /**
   * Performs a PUT request, isolates caller from exceptions
   *
   * @param string $endpoint      just the segment of the API the request
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   *
   * @return string|bool          response body on success, false on failure
   */
  protected function _put( $endpoint, array $query_params = array(), $put_body = array(), $curl_params= array() ) {

    $full_url = $this->_makeFullURL( $endpoint, $query_params );

    $results  = false;

    try {

      return $this->_executeRequest( 'PUT', $full_url, $put_body, $curl_params );

    } // try

    catch( Be_Exception $e ) {

      if ( $this->_debug )
        echo ( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );

      return false;

    } // catch

  } // _put

  /**
   * Performs a DELETE request, isolates caller from exceptions
   *
   * @param string $endpoint      just the segment of the API the request
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   *
   * @return string|bool          response body on success, false on failure
   */
  protected function _delete( $endpoint, array $query_params = array(), $delete_body = array(), $curl_params= array() ) {

    $full_url = $this->_makeFullURL( $endpoint, $query_params );

    $results  = false;

    try {

      return $this->_executeRequest( 'DELETE', $full_url, $delete_body, $curl_params );

    } // try

    catch( Be_Exception $e ) {

      if ( $this->_debug )
        echo ( __CLASS__ . "::" . __LINE__ . ": " . $e->getMessage() );

      return false;

    } // catch

  } // _delete

  /**
   * Generates a fully-quality API url, with $endpoint + $query_params, automatically adds in app's key
   *
   * @param string $endpoint      segment of the API being accessed
   * @param array  $query_params  anything additional to add to the query string, in key => value form
   */
  protected function _makeFullURL( $endpoint, array $query_params = array() ) {

    $query_string = '?' . http_build_query( $query_params );
    $full_url     = $this->_api_root . $endpoint . $query_string;

    return $full_url;

  } // _makeFullURL

  /**
   * Makes a remote request to $url
   *
   * @throws Be_Exception: on any non-200 response from request
   *
   * @param string       $method         HTTP verb of request (get|post|post|delete|head)
   * @param string       $url            fully-qualified destination of request
   * @param string|array $request_body   sent as HTTP body of request
   * @param array        $curl_params    parameters to override for cURL library (timeouts, user agent, etc)
   *
   * @return string                      response body
   */
  protected function _executeRequest( $method, $url, $request_body = false, $curl_params = array() ) {

    $user_agent          = "Behance API/PHP (App {$this->_client_id})";
    $default_curl_params = array(
        CURLOPT_HTTPHEADER      => array( 'Accept: application/json', 'Content-Type: multipart/form-data', 'Expect:' ),
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
        // if ( is_array( $request_body ) )
        //   $request_body = http_build_query( $request_body );

       // $curl_params[ CURLOPT_HTTPHEADER ][] = 'Content-Length: ' . strlen( $request_body );
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
        throw new Be_Exception( "Unhandled method: [{$method}]" );

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
      throw new Be_Exception( "Malformed response_body: " . var_export( $response_body, 1 ) );


    // @throws Be_Exception on response non-2xx (success) responses from service
    if ( (int)round( $response_code, -2 ) !== 200 )
      throw new Be_Exception( "Unsuccessful Request, response ({$response_code}): " . ( empty( $response_body ) ? '' : ": {$response_body} " ) );


    return $response_body;


  } // _executeRequest

  /**
   * Redirects user to specified url
   *
   * @param  string $location  Url to redirect user
   */
  protected function _redirect( $location ) {

    header( "Location: {$location}" );
    exit;

  } // _redirect

} // Be_Api
