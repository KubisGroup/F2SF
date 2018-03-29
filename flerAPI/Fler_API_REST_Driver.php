<?php
/**
 * User: Ladislav Kafka
 * Date: 26.06.2014
 * Time: 9:32
 *
 */
class Fler_API_REST_Driver
{
    public $host;
    public $key_public;
    public $key_private;
    public $api_zone = "API1";



    public function __construct($options = array())
    {
        if (!function_exists('hash_hmac'))
        {
            throw new Exception("Hash libs not loaded");
        }
        if(!function_exists("curl_init"))
        {
            throw new Exception("Curl needed");
        }
        $this->setHost('www.fler.cz');
        $this->setOptions($options);
    }

    /**
     * @param $http_method
     * @param $path
     * @param array $data
     * @param array $curl_opts
     * @return Fler_API_REST_DriverResponse
     * @throws Exception
     */
    public function request($http_method, $path, $data = array(), $curl_opts = array())
    {
        $url = $this->host ? $this->host : $_SERVER['HTTP_HOST'];
        $url .= $path;

        $curlHeaderOpts = array(CURLOPT_HTTPHEADER => array(
            'X-FLER-AUTHORIZATION: '.$this->calcAuth($http_method, $path),
            'Accept: application/json',
        ));
        if($curl_opts)
        {
            $curlHeaderOpts = $curlHeaderOpts + $curl_opts;
        }
        //var_dump($data);

        switch ($http_method)
        {
            case "POST":
                list($response, $httpcode) = Fler_API_REST_DriverLoader::load_post($url, $data, $curlHeaderOpts);

                break;
            case "GET":
                $url .= '?'.http_build_query($data, '', '&');
                list($response, $httpcode) = Fler_API_REST_DriverLoader::load_get($url, $curlHeaderOpts);

                break;
            default:
                throw new Exception("Invalid method");
                break;
        }
        if(!$response)
        {
            throw new Exception("Failed to load URL [{$url}] (http_code:{$httpcode})");
        }



        $responseObject = new Fler_API_REST_DriverResponse();
        $responseObject->plain =$response;
        $responseObject->data = json_decode($responseObject->plain, 1);



        $responseObject->http_code = $httpcode;
        return $responseObject;


    }

    /**
     * @param $path
     * @param array $data
     * @param array $curlopts
     * @return Fler_API_REST_DriverResponse
     * @throws Exception
     */
    public function post($path, $data = array(), $curlopts = array())
    {
        return $this->request("POST", $path, $data, $curlopts);
    }

    /**
     * @param $path
     * @param array $data
     * @param array $curlopts
     * @return Fler_API_REST_DriverResponse
     * @throws Exception
     */
    public function get($path, $data = array(), $curlopts = array())
    {
        return $this->request("GET", $path, $data, $curlopts);
    }

    /**
     * @param $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @param $key_public
     * @param $key_private
     */
    public function setKeys($key_public, $key_private)
    {
        $this->key_public = $key_public;
        $this->key_private = $key_private;
    }

    public function setOptions($options)
    {
        foreach ($options as $k=>$v)
        {
            if(property_exists($this, $k))
            {
                $this->$k = $v;
            }
        }
    }

    /**
     * @param $http_method
     * @param $http_path
     * @return string
     */
    public function calcAuth($http_method, $http_path)
    {
        $time = time();
        $request_string = $http_method . "\n" . $time . "\n" . $http_path;
        $request_string_hashed = hash_hmac('sha1', $request_string, $this->key_private);
        $auth_string = $this->api_zone . ' ' . $this->key_public . ' ' . $time . ' ' . base64_encode($request_string_hashed);
        return $auth_string;

    }
}


class Fler_API_REST_DriverResponse
{
    public $plain;
    public $data;
    public $http_code;
}

class Fler_API_REST_DriverLoader
{
    public static $lastError = array();

    public static function load_get($url, $curloptions = array(), $chInit = null)
    {
        if(!function_exists("curl_init"))
        {
            throw new Exception("Curl needed");
        }
        $ch =  $chInit ? $chInit : curl_init();
        // URL SET
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!$chInit)
        {
            curl_setopt_array($ch, array(
                CURLOPT_TIMEOUT         => 6,
                CURLOPT_FOLLOWLOCATION  => 1,
                CURLOPT_HEADER          => 1,
                CURLOPT_RETURNTRANSFER  => 1,
            ));
        }
        if ($curloptions)
        {
            curl_setopt_array($ch, $curloptions);
        }
        $buffer = curl_exec($ch);
        if ($buffer === false)
        {
            self::$lastError = array('type'=>curl_errno($ch), 'message'=>curl_error($ch));
            if(!$chInit)
            {
                curl_close($ch);
            }
            return false;
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if(!$chInit)
        {
            curl_close($ch);
        }

        $header = substr($buffer, 0, $header_size);
        $body = substr($buffer, $header_size);
        $parsedHeaders = self::http_parse_headers($header);
        // http code
        preg_match("/[0-9]{3}/", $parsedHeaders[0], $matches);
        $httpcode = $matches[0];
        return array($body, $httpcode, $parsedHeaders, $buffer);
    }

    public static function load_post($url, $data, $curloptions = array(), $chInit = null)
    {
        if(!function_exists("curl_init"))
        {
            throw new Exception("Curl needed");
        }
        $postVars = http_build_query($data, '', "&");

        $ch =  $chInit ? $chInit : curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);


        if (!$chInit)
        {
            curl_setopt_array($ch, array(
                CURLOPT_TIMEOUT         => 6,
                CURLOPT_FOLLOWLOCATION  => 1,
                CURLOPT_HEADER          => 1,
                CURLOPT_POST            => 1,
                CURLOPT_RETURNTRANSFER  => 1,
            ));
        }
        if ($curloptions )
        {
            curl_setopt_array($ch, $curloptions);
        }
        $buffer = curl_exec($ch);
        if ($buffer === false)
        {
            self::$lastError = array('type'=>curl_errno($ch), 'message'=>curl_error($ch));
            if(!$chInit)
            {
                curl_close($ch);
            }
            return false;
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if(!$chInit)
        {
            curl_close($ch);
        }

        $header = substr($buffer, 0, $header_size);
        $body = substr($buffer, $header_size);
        $parsedHeaders = self::http_parse_headers($header);
        // http code
        preg_match("/[0-9]{3}/", $parsedHeaders[0], $matches);
        $httpcode = $matches[0];
        return array($body, $httpcode, $parsedHeaders, $buffer);
    }

    public static function get_http_headers($url, $chInit = null)
    {
        if (!function_exists('curl_init'))
        {
            throw new Exception("Curl module is missing");
        }
        $ch =  $chInit ? $chInit : curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!$chInit)
        {
            curl_setopt_array($ch, array(
                CURLOPT_TIMEOUT         => 6,
                CURLOPT_FOLLOWLOCATION  => 1,
                CURLOPT_HEADER          => 1,
                CURLOPT_NOBODY          => true,
                CURLOPT_FILETIME        => true,
                CURLOPT_RETURNTRANSFER  => 1,
            ));
        }
        $headersRaw = curl_exec($ch);
        $info = curl_getinfo($ch);
        $info['RAWHEADER'] = $headersRaw;
        if(!$chInit)
        {
            curl_close($ch);
        }
        return $info;
    }

    public static function http_parse_headers($raw_headers)
    {
        $headers = array();
        $key = '';

        foreach(explode("\n", $raw_headers) as $h)
        {
            $h = explode(':', $h, 2);

            if (isset($h[1]))
            {
                if (!isset($headers[$h[0]]))
                    $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]]))
                {
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                }
                else
                {
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                }

                $key = $h[0];
            }
            else
            {
                if (substr($h[0], 0, 1) == "\t")
                    $headers[$key] .= "\r\n\t".trim($h[0]);
                elseif (!$key)
                    $headers[0] = trim($h[0]);
            }
        }

        return $headers;
    }


    public static function remote_location_http_code($url, $chInit = null)
    {
        $headers = self::get_http_headers($url, $chInit);
        return $headers['http_code'];
    }
}