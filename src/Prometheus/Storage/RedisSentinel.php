<?php

namespace Prometheus\Storage;

class Sentinel
{

    public $hostname;

    public $masterName;

    public $port = 26379;

    public $connectionTimeout;

    /**
     * Depricated. Redis sentinel does not work on unix socket
     **/
    public $unixSocket;

    protected $_socket;

    /**
     * Connects to redis sentinel
     **/
    protected function open () {
        if ($this->_socket !== null) {
            return;
        }
        $connection = $this->hostname . ':' . $this->port;
        $this->_socket = @stream_socket_client('tcp://' . $this->hostname . ':' . $this->port, $errorNumber, $errorDescription, $this->connectionTimeout ? $this->connectionTimeout : ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT);
        if ($this->_socket) {
            if ($this->connectionTimeout !== null) {
                stream_set_timeout($this->_socket, $timeout = (int) $this->connectionTimeout, (int) (($this->connectionTimeout - $timeout) * 1000000));
            }
            return true;
        } else {
            $this->_socket = false;
            return false;
        }
    }

    /**
     * Asks sentinel to tell redis master server
     *
     * @return array|false [host,port] array or false if case of error
     **/
    function getMaster () {
        if ($this->open()) {
            return $this->executeCommand('sentinel', [
                'get-master-addr-by-name',
                $this->masterName
            ], $this->_socket);
        } else {
            return false;
        }
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
