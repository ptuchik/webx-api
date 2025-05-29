<?php

namespace WebX;

use Exception;

/**
 * Class Api
 *
 * @package WebX
 */
class Api
{
    /**
     * API username
     *
     * @var string
     */
    protected $username = 'YOUR-USERNAME'; // Replace with your username

    /**
     * API key
     *
     * @var string
     */
    protected $key = 'YOUR-API-KEY'; // Replace with your API key

    /**
     * Supplier website URL
     *
     * @var string
     */
    protected $url = 'WEBSITE-URL'; // Replace with your supplier's website URL

    /**
     * Folder, where you keep the files to be sent with file orders
     *
     * @var string
     */
    protected $uploadsFolder = 'uploads';

    /*** DO NOT CHANGE ANYTHING BELOW ***/
    const IMEI_SERVICES = 'imei-services';
    const SERVER_SERVICES = 'server-services';
    const FILE_SERVICES = 'file-services';

    protected $accountInfo;
    protected $imeiServices;
    protected $serverServices;
    protected $fileServices;
    protected $providerList;
    protected $networkList;
    protected $modelList;
    protected $mepList;

    /**
     * Get account info
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getAccountInfo()
    {
        if (is_null($this->accountInfo)) {
            $this->accountInfo = $this->call();
        }

        return $this->accountInfo;
    }

    /**
     * Get balance
     *
     * @return int|float
     *
     * @throws \Exception
     */
    public function getBalance()
    {
        return filter_var($this->arrayGet($this->getAccountInfo(), 'balance', 0), FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Get currency
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getCurrency()
    {
        return $this->arrayGet($this->getAccountInfo(), 'currency', 'USD');
    }

    /**
     * Get IMEI services
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getImeiServices()
    {
        return $this->getServices(static::IMEI_SERVICES);
    }

    /**
     * Get server services
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getServerServices()
    {
        return $this->getServices(static::SERVER_SERVICES);
    }

    /**
     * Get file services
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getFileServices()
    {
        return $this->getServices(static::FILE_SERVICES);
    }

    /**
     * Get services from external API
     *
     * @param $serviceType
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getServices($serviceType)
    {
        $services = $this->camelize($serviceType);

        if (is_null($this->$services)) {
            $this->$services = $this->parseServices($this->call($serviceType), $serviceType);
        }

        return $this->$services;
    }

    /**
     * Parse services
     *
     * @param $result
     * @param $serviceType
     *
     * @return array
     */
    protected function parseServices($result, $serviceType)
    {
        // Init an empty array of services
        $services = [];

        // Loop through result as service
        foreach ($result as $service) {

            if (!empty($service['id'])) {
                $services[$service['id']] = array_merge([
                    'id'                => $service['id'],
                    'name'              => $service['name'] ?? '',
                    'time'              => $service['time'] ?? '',
                    'info'              => $service['info'] ?? '',
                    'price'             => $service['credits'] ?? 0,
                    'additional_fields' => json_encode($service['fields'] ?? []),
                    'additional_data'   => null,
                    'params'            => json_encode([
                        'main_field'       => $service['main_field'] ?? [],
                        'calculation_type' => $service['type'],
                        'allow_duplicates' => $service['allow_duplicates'] ?? false
                    ])
                ], $this->collectAdditionalData($service, $serviceType));
            }
        }

        return $services;
    }

    /**
     * Collect additional data for service
     *
     * @param $data
     * @param $serviceType
     *
     * @return array
     */
    protected function collectAdditionalData($data, $serviceType)
    {
        switch ($serviceType) {
            case static::FILE_SERVICES:
                return [
                    'allowed_extensions' => $data['main_field']['rules']['allowed'] ?? null
                ];
            default:
                return [];
        }
    }

    /**
     * Place order
     *
     * @param \WebX\Order $order
     *
     * @return \WebX\Order
     * @throws \Exception
     */
    public function placeOrder(Order $order)
    {
        $data = [
            'service_id' => $order->getServiceId(),
            'comments'   => $order->getComments()
        ];

        if ($order instanceof ImeiOrder) {
            $data['device'] = $order->getDevice();
        } elseif ($order instanceof ServerOrder) {
            $data['quantity'] = $order->getQuantity();
        } elseif ($order instanceof FileOrder) {
            $path = $this->uploadsFolder.'/'.$order->getDevice();
            $data['device'] = file_exists($path) ? new CURLFile($path) : null;
        } else {
            throw new Exception('invalid_order');
        }

        $response = $this->call($order->getType(), array_merge($data, $order->getAdditional()), 'POST');

        $order->setResponse($response['response'] ?? null);

        $order->setId($response['id'] ?? 0);

        return $order;
    }

    /**
     * Get order
     *
     * @param \WebX\Order $order
     *
     * @return \WebX\Order
     */
    public function getOrder(Order $order)
    {
        $response = $this->call($order->getType().'/'.$order->getId());

        $order->setResponse($response['response'] ?? null);

        $order->setStatus($response['status']);

        return $order;
    }

    /**
     * Call API
     *
     * @param string $route
     * @param array  $params
     * @param string $method
     * @param bool   $debug
     *
     * @return array
     */
    protected function call($route = '', array $params = [], $method = 'GET', $debug = false)
    {
        $params['username'] = $this->username;
        $headers = [
            'Accept: application/json',
            'Auth-Key: '.password_hash($this->username.$this->key, PASSWORD_BCRYPT)
        ];

        $response = $this->curl($this->url.'/api/'.$route, $params, $method, $debug, $headers);

        // If the response was not successful, throw an error
        if (!is_array($response) || !empty($response['errors'])) {
            $this->parseErrors($response['errors'] ?? []);
        }

        return $response;
    }

    /**
     * Parse errors
     *
     * @param $errors
     *
     * @throws \Exception
     */
    protected function parseErrors($errors)
    {
        if (empty($errors) || !is_array($errors)) {
            $message = 'could_not_connect_to_api';
        } else {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = implode(', ', $error);
            }
            $message = implode(', ', $messages);
        }

        throw new Exception($message);
    }

    /**
     * Call via PHP cURL
     *
     * @param        $url
     * @param array  $data
     * @param string $method
     * @param bool   $debug
     * @param array  $headers
     * @param bool   $postJson
     * @param bool   $getHeaders
     * @param int    $timout
     *
     * @return array|mixed
     */
    protected function curl(
        $url,
        $data = array(),
        $method = 'GET',
        $debug = false,
        $headers = [],
        $postJson = false,
        $getHeaders = false,
        $timout = 0
    ) {

        $method = strtoupper($method);

        if ($postJson) {
            $post = json_encode($data);
        } else {
            $post = http_build_query($data);
        }

        $url = (!empty($data) && ($method == 'GET' || $method == 'DELETE')) ? $url.'?'.$post : $url;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (isset($timout)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timout);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($getHeaders) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        }
        if ($method == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($getHeaders) {
            return $this->getCurlHeaders($response);
        }

        if ($debug) {
            return ['response' => $response, 'status' => $status];
        }

        return json_decode($response, true);
    }

    /**
     * Get cURL headers
     *
     * @param $response
     *
     * @return array
     */
    protected function getCurlHeaders($response)
    {
        $headers = array();

        $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

        foreach (explode("\r\n", $header_text) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list ($key, $value) = explode(': ', $line);

                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * Convert value into camelCase
     *
     * @param $value
     *
     * @return string
     */
    protected function camelize($value)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value))));
    }

    /**
     * Get the value of the key from array
     *
     * @param array  $array
     * @param string $key
     * @param null   $default
     *
     * @return mixed|null
     */
    protected function arrayGet(array $array, string $key, $default = null)
    {
        return $array[$key] ?? $default;
    }
}

/**
 * Class Order
 *
 * @package WebX
 */
abstract class Order
{
    const WAITING_ACTION = 0;
    const IN_PROCESS = 1;
    const CANCELLED = 2;
    const REJECTED = 3;
    const SUCCESS = 4;

    protected $id = 0;
    protected $device = '';
    protected $quantity = 1;
    protected $comments = '';
    protected $additional = [];
    protected $serviceId = '';
    protected $status;
    protected $response;
    protected $api;

    /**
     * Order constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setStatus(static::WAITING_ACTION);

        if (isset($data['id'])) {
            $this->setId($data['id']);
        }

        if (isset($data['device'])) {
            $this->setDevice($data['device']);
        }

        if (isset($data['quantity'])) {
            $this->setQuantity($data['quantity']);
        }

        if (isset($data['comments'])) {
            $this->setComments($data['comments']);
        }

        if (isset($data['additional']) && is_array($data['additional'])) {
            $this->setAdditional($data['additional']);
        }

        if (isset($data['service_id'])) {
            $this->setServiceId($data['service_id']);
        }
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId(int $id) : self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getDevice() : string
    {
        return $this->device;
    }

    /**
     * @param string $device
     *
     * @return $this
     */
    public function setDevice(string $device) : self
    {
        $this->device = $device;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity() : int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     *
     * @return $this
     */
    public function setQuantity(int $quantity) : self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return string
     */
    public function getComments() : string
    {
        return $this->comments;
    }

    /**
     * @param string $comments
     *
     * @return $this
     */
    public function setComments(string $comments) : self
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * @return array
     */
    public function getAdditional() : array
    {
        return $this->additional;
    }

    /**
     * @param array $additional
     *
     * @return $this
     */
    public function setAdditional(array $additional) : self
    {
        $this->additional = $additional;

        return $this;
    }

    /**
     * @return string
     */
    public function getServiceId() : string
    {
        return $this->serviceId;
    }

    /**
     * @param string $serviceId
     *
     * @return $this
     */
    public function setServiceId(string $serviceId) : self
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatus() : int
    {
        return $this->status;
    }

    /**
     * @param int $status
     *
     * @return $this
     */
    public function setStatus(int $status) : self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param $response
     *
     * @return $this
     */
    public function setResponse($response) : self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get API instance
     *
     * @return \WebX\Api
     */
    protected function getApi()
    {
        if (is_null($this->api)) {
            $this->api = new Api();
        }

        return $this->api;
    }

    /**
     * Place order
     *
     * @return \WebX\Order
     */
    public function send()
    {
        return $this->getApi()->placeOrder($this);
    }

    /**
     * Get order
     *
     * @return \WebX\Order
     */
    public function get()
    {
        return $this->getApi()->getOrder($this);
    }

    /**
     * Get order type
     *
     * @return string
     */
    abstract function getType();
}

/**
 * Class ImeiOrder
 *
 * @package WebX
 */
class ImeiOrder extends Order
{
    /**
     * Get order type
     *
     * @return string
     */
    public function getType()
    {
        return 'imei-orders';
    }
}

/**
 * Class ServerOrder
 *
 * @package WebX
 */
class ServerOrder extends Order
{
    /**
     * Get order type
     *
     * @return string
     */
    public function getType()
    {
        return 'server-orders';
    }
}

/**
 * Class FileOrder
 *
 * @package WebX
 */
class FileOrder extends Order
{
    /**
     * Get order type
     *
     * @return string
     */
    public function getType()
    {
        return 'file-orders';
    }
}
