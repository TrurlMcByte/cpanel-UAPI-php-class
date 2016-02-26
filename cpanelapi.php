<?php

/**
 * PHP class to handle connections with cPanel's UAPI and API2 specifically through cURL requests as seamlessly and simply as possible.
 *
 * For documentation on cPanel's UAPI:
 *
 * @see https://documentation.cpanel.net/display/SDK/Guide+to+UAPI
 * For documentation on cPanel's API2:
 * @see https://documentation.cpanel.net/display/SDK/Guide+to+cPanel+API+2
 *
 * Please use UAPI where possible, only use API2 where the equivalent doesn't exist for UAPI
 *
 * @author Trurl McByte <trurl@mcbyte.net>
 * @copyright 2016 Trurl McByte
 * @license license.txt The MIT License (MIT)
 *
 * @link https://github.com/TrurlMcByte/cpanel-UAPI-php-class
 *
 * Fork from:
 *
 * @author N1ghteyes - www.source-control.co.uk
 * @copyright 2016 N1ghteyes
 * @license license.txt The MIT License (MIT)
 *
 * @link https://github.com/N1ghteyes/cpanel-UAPI-php-class
 */

/**
 * Class cPanelAPI.
 */
class cpanelapi
{
    public $version = '1.1';
    public $server;

    private $maxredirect = 0;
    private $ssl = 1;
    private $port = 2083;
    private $scope = '';
    private $api;
    private $auth;
    private $user;
    private $pass;
    private $secret;
    private $type;
    private $session;
    private $method;
    private $requestUrl;
    private $last_answer;

    /**
     * we emulate a browser here since some websites detect
     * us as a bot and don't let us do our job.
     */
    public $user_agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5)'.
      ' Gecko/20041107 Firefox/1.0';

  /**
   * @param $user
   * @param $pass
   * @param $server
   * @param $api (optional) api type 'uapi' ot 'api2' (by default is 'uapi')
   * @param $ssl (optional) use SSL (by default is TRUE)
   * @param $port (optional) use SSL (by default is TRUE)
   * @param $maxredirect (optional) Number of redirects to make, typically 0 is fine. on some shared setups this will need to be increased (defaul 0)
   *
   * @return cpanelAPI $this self object
   */
  public function __construct($user, $pass, $server, $api = 'uapi', $ssl = true, $port = null, $maxredirect = 0)
  {
      $this->user = $user;
      $this->pass = $pass;
      $this->ssl = $ssl;
      if ($port === null) {
          $this->port = $this->ssl ? 2083 : 2082;
      }
      $this->server = $server;
      $this->maxredirect = $maxredirect;

      return $this->setApi($api);
  }
  /**
   * @param string $api 'uapi' ot 'api2'
   *
   * @return cpanelAPI $this self object
   */
  public function setApi($api)
  {
      $this->api = $api;
      $this->setMethod();

      return $this;
  }

    /**
     * bit crazy.
     */
    public function __get($name)
    {
        if ($name === 'API2') {
            return $this->setApi('api2');
        }
        if ($name === 'UAPI' || $name === 'UAPI2') {
            return $this->setApi('uapi');
        }

        return new cpanelapimethod($this, $name);
    }

  /**
   * Magic __call method, will translate all function calls to object to API requests.
   *
   * @param $name - name of the function
   * @param $arguments - an array of arguments
   *
   * @return array report array
   *
   * @throws Exception
   */
  public function __call($name, $arguments)
  {
      if (method_exists($this, $name)) {
          return call_user_func_array(array($this, $name), $arguments);
      }

      if (count($arguments) < 1 || !is_array($arguments[0])) {
          $arguments[0] = (count($arguments) > 0 && is_object($arguments[0])) ? ((array) $arguments[0]) : array();
      }
      $this->last_query = (object) array('error' => null, 'api' => $this->api, 'scope' => $this->scope, 'method' => $name, 'args' => $arguments[0], 'reply' => null);
      $this->last_query->reply = $this->APIcall($name, $arguments[0]);
      if ($this->last_query->reply['errno'] === 0) {
          $this->last_answer = json_decode($this->last_query->reply['content']);
          if (json_last_error() !== JSON_ERROR_NONE) {
              $this->last_query->error = 'JSON ERROR: '.$this->last_query->json_error;
          } else {
              unset($this->last_query->reply);
          }
      } else {
          $this->last_query->error = $this->last_query->reply['errmsg'];
      }
      if (is_object($this->last_answer)) {
          $this->last_answer->__query = $this->last_query;
      } else {
          return (object) array('__query' => $this->last_query);
      }

      return $this->last_answer;
  }

    public function getLastRequest()
    {
        return $this->requestUrl;
    }

  /**
   * set the scope to the module we want to use. NOTE: this IS case sensitive.
   *
   * @param $scope 
   *
   * @return cpanelAPI $this self object
   */
  public function scope($scope)
  {
      $this->scope = $scope;

      return $this;
  }
    protected function setMethod()
    {
        switch ($this->api) {
      case 'uapi' :
        $this->method = '/execute/';
            break;
      case 'api2':
        $this->method = '/json-api/cpanel/';
            break;
      default:
            throw new Exception('$this->api is not set or is incorrectly set. The only available options are \'uapi\' or \'api2\'');
    }
    }

  /**
   * @param $name
   * @param $arguments
   *
   * @return bool|mixed
   *
   * @throws Exception
   */
  protected function APIcall($name, $arguments)
  {
      $this->auth = base64_encode($this->user.':'.$this->pass);
      $this->type = $this->ssl == 1 ? 'https://' : 'http://';
      $this->requestUrl = $this->type.$this->server.':'.$this->port.$this->method;
      switch ($this->api) {
        case 'uapi':
            $this->requestUrl .= ($this->scope != '' ? $this->scope.'/' : '').$name.'?';
            break;
        case 'api2':
            if ($this->scope == '') {
                throw new Exception('Scope must be set.');
            }
            $this->requestUrl .= '?cpanel_jsonapi_user='.$this->user.'&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module='.$this->scope.'&cpanel_jsonapi_func='.$name.'&';
            break;
        default:
            throw new Exception('$this->api is not set or is incorrectly set. The only available options are \'uapi\' or \'api2\'');
      }
      foreach ($arguments as $key => $value) {
          $this->requestUrl .= $key.'='.$value.'&';
      }

      return $this->curl_request($this->requestUrl);
  }

  /**
   * @param $url
   *
   * @return bool|mixed
   */
  protected function curl_request($url)
  {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$this->auth));
      curl_setopt($ch, CURLOPT_TIMEOUT, 100020);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $content = $this->curl_exec_follow($ch, $this->maxredirect);
      $err = curl_errno($ch);
      $errmsg = curl_error($ch);
      $header = curl_getinfo($ch);

      curl_close($ch);

      $header['errno'] = $err;
      $header['errmsg'] = $errmsg;
      $header['content'] = $content;

      return $header;
  }

  /**
   * @param $ch
   * @param null $maxredirect
   *
   * @return bool|mixed
   */
  protected function curl_exec_follow($ch, &$maxredirect = null)
  {
      curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

      $mr = $maxredirect === null ? 5 : intval($maxredirect);

      if (ini_get('open_basedir') == '' && ini_get('safe_mode') == 'Off') {
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
          curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      } else {
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

          if ($mr > 0) {
              $original_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
              $newurl = $original_url;

              $rch = curl_copy_handle($ch);

              curl_setopt($rch, CURLOPT_HEADER, true);
              curl_setopt($rch, CURLOPT_NOBODY, true);
              curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
              do {
                  curl_setopt($rch, CURLOPT_URL, $newurl);
                  $header = curl_exec($rch);
                  if (curl_errno($rch)) {
                      $code = 0;
                  } else {
                      $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                      if ($code == 301 || $code == 302) {
                          preg_match('/Location:(.*?)\n/', $header, $matches);
                          $newurl = trim(array_pop($matches));

                          // if no scheme is present then the new url is a
                          // relative path and thus needs some extra care
                          if (!preg_match('/^https?:/i', $newurl)) {
                              $newurl = $original_url.$newurl;
                          }
                      } else {
                          $code = 0;
                      }
                  }
              } while ($code && --$mr);

              curl_close($rch);

              if (!$mr) {
                  if ($maxredirect === null) {
                      trigger_error('Too many redirects.', E_USER_WARNING);
                  } else {
                      $maxredirect = 0;
                  }

                  return false;
              }
              curl_setopt($ch, CURLOPT_URL, $newurl);
          }
      }

      return curl_exec($ch);
  }
}

/**
 * Pseudo API class.
 */
class cpanelapimethod
{
    public $base = null;
    public $name = '';

    public function __construct(cpanelapi &$base, $name)
    {
        $this->base = &$base;
        $this->name = $name;
    }
    public function __call($name, $arguments)
    {
        $this->base->scope($this->name);

        return call_user_func_array(array($this->base, $name), $arguments);
    }
}
