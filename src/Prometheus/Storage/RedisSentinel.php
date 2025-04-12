<?php

namespace Prometheus\Storage;
use Prometheus\Exception\StorageException;

class RedisSentinel
{

    protected $host;

    protected $master;

    protected $port = 26379;

    protected $connectionTimeout;

    protected $_socket;

    private static $defaultOptions = [
        'enable' => false,
        'host' => null,
        'port' => 26379,
        'master' => 'mymaster',
        'timeout' => 0.1,
        'read_timeout' => null,
    ];

    public function __construct(array $options = [], $host = null)
    {
        $this->options = array_merge(self::$defaultOptions, $options);
        if(!isset($this->options['host'])) {
            $this->options['host'] = $host;
        }
    }
    /**
     * Connects to redis sentinel
     **/
    protected function open () {
        if ($this->_socket !== null) {
            return;
        }
        $connection = $this->options['host'] . ':' . $this->options['port'];
        $connectionTimeout = $this->options['timeout'] ? $this->options['timeout'] : ini_get("default_socket_timeout");
        $address = 'tcp://' . $this->options['host'] . ':' . $this->options['port'];
        $this->_socket = @stream_socket_client($address, $errorNumber, $errorDescription, $connectionTimeout, STREAM_CLIENT_CONNECT);
        if(!$this->_socket){
            throw new StorageException(sprintf("Can't connect to Redis server '$address'. %s", $errorDescription),
                $errorNumber,
                null);
        }

        if ($this->options['timeout'] !== null) {
            $timeoutSeconds = (int) $this->options['timeout'];
            $timeoutMicroseconds = (int) (($this->options['timeout'] - $timeoutSeconds) * 1000000);
            stream_set_timeout($this->_socket, $timeoutSeconds, $timeoutMicroseconds);
        }
    }

    /**
     * Asks sentinel to tell redis master server
     *
     * @return array|false [host,port] array or false if case of error
     **/
    function getMaster () {
        $this->open();

        return $this->executeCommand('sentinel', [
            'get-master-addr-by-name',
            $this->options['master']
        ], $this->_socket);
    }

    /**
     * Execute redis command on socket and return parsed response
     **/
    function executeCommand ($name, $params, $socket) {
        $params = array_merge(explode(' ', $name), $params);
        $command = '*' . count($params) . "\r\n";
        foreach ($params as $arg) {
            $command .= '$' . mb_strlen($arg, '8bit') . "\r\n" . $arg . "\r\n";
        }

        fwrite($socket, $command);

        return $this->parseResponse(implode(' ', $params), $socket);
    }

    /**
     *
     * @param string $command
     * @return mixed
     * @throws StorageException
     */
    function parseResponse ($command, $socket) {
        if (($line = fgets($socket)) === false) {
            throw new StorageException("Failed to read from socket.\nRedis command was: " . $command);
        }
        $type = $line[0];
        $line = mb_substr($line, 1, - 2, '8bit');
        switch ($type) {
            case '+': // Status reply
                if ($line === 'OK' || $line === 'PONG') {
                    return true;
                } else {
                    return $line;
                }
            case '-': // Error reply
                throw new StorageException("Redis error: " . $line . "\nRedis command was: " . $command);
            case ':': // Integer reply
                // no cast to int as it is in the range of a signed 64 bit integer
                return $line;
            case '$': // Bulk replies
                if ($line == '-1') {
                    return null;
                }
                $length = $line + 2;
                $data = '';
                while ($length > 0) {
                    if (($block = fread($socket, $length)) === false) {
                        throw new \Exception("Failed to read from socket.\nRedis command was: " . $command);
                    }
                    $data .= $block;
                    $length -= mb_strlen($block, '8bit');
                }

                return mb_substr($data, 0, - 2, '8bit');
            case '*': // Multi-bulk replies
                $count = (int) $line;
                $data = [];
                for ($i = 0; $i < $count; $i ++) {
                    $data[] = $this->parseResponse($command, $socket);
                }

                return $data;
            default:
                throw new StorageException('Received illegal data from redis: ' . $line . "\nRedis command was: " . $command);
        }
    }

}
