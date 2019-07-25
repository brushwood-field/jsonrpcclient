<?php

namespace Brushwoodfield\JsonRPCClient;

/**
 * The object of this class are generic jsonRPC 1.0 clients
 * http://json-rpc.org/wiki/specification
 *
 * @author sergio <jsonrpcphp@inservibile.org>
 * @author brushwoodfield <brushwoodfield@deim.me>
 */
class jsonRPCClient
{

    /**
     * @var boolean Debug state
     */
    private $debug;
    /**
     * @var string The server URL
     */
    private $url;
    /**
     * @var string The proxy URL. ex, http://192.168.10.10:8080
     */
    private $proxy;
    /**
     * @var integer The request id
     */
    private $id;
    /**
     * @var boolean If true, notifications are performed instead of requests
     */
    private $notification = false;

    /**
     * Takes the connection parameters
     *
     * @param string  $url
     * @param string  $proxy
     * @param boolean $debug
     */
    public function __construct($url, $proxy = null, $debug = false)
    {
        // server URL
        $this->url = $url;
        // proxy
        $this->proxy = empty($proxy) ? null: $proxy;
        // debug state
        $this->debug = empty($debug) ? false: true;
        // message id
        $this->id = 1;
    }

    /**
     * Sets the notification state of the object. In this state, notifications are performed, instead of requests.
     *
     * @param boolean $notification
     */
    public function setRPCNotification($notification)
    {
        $this->notification = empty($notification) ? false: true;
    }

    /**
     * Performs a jsonRCP request and gets the results as an array
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function __call($method, $params)
    {

        // check
        if (!is_scalar($method)) {
            throw new \RuntimeException('Method name has no scalar value');
        }

        // check
        if (is_array($params)) {
            // no keys
            $params = array_values($params);
        } else {
            throw new \RuntimeException('Params must be given as array');
        }

        // sets notification or request task
        if ($this->notification) {
            $currentId = null;
        } else {
            $currentId = $this->id;
        }

        // prepares the request
        $request = array(
            'method' => $method,
            'params' => $params,
            'id' => $currentId
        );
        $request = json_encode($request);
        $this->debug && $this->debug.='***** Request *****'."\n".$request."\n".'***** End Of request *****'."\n\n";

        // performs the HTTP POST
        $opts = array('http' => array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/json',
            'content' => $request
        ));
        if ($this->proxy) {
            $this->with_proxy($opts);
        }
        $context  = stream_context_create($opts);
        if ($fp = fopen($this->url, 'r', false, $context)) {
            $response = '';
            while ($row = fgets($fp)) {
                $response.= trim($row)."\n";
            }
            $this->debug && $this->debug.='***** Server response *****'."\n".$response.'***** End of server response *****'."\n";
            $response = json_decode($response, true);
        } else {
            throw new \Exception('Unable to connect to '.$this->url);
        }

        // debug output
        if ($this->debug) {
            echo nl2br($this->debug);
        }

        // final checks and return
        if (!$this->notification) {
            // check
            if ($response['id'] != $currentId) {
                throw new \Exception('Incorrect response id (request id: '.$currentId.', response id: '.$response['id'].')');
            }
            if (!is_null($response['error'])) {
                throw new \Exception('Request error: '.$response['error']);
            }

            return $response['result'];
        } else {
            return true;
        }
    }

    /**
     * If use proxy, aditional proxy option
     *
     * @param  array $opts reference
     * @return void
     */
    private function with_proxy(&$opts)
    {
        array_merge($opts, array(
            'http' => array(
                'proxy' => $this->proxy,
                'request_fulluri' => true,
            ),
        ));
    }
}
