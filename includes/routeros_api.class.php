<?php
/**
 * RouterOS API Client
 * Modified to accept dynamic port
 */
class RouterosAPI
{
    var $socket;        // socket handle
    var $error_no;      // last error number
    var $error_str;     // last error string
    var $timeout    = 5; // connection timeout in seconds
    var $port       = 8728; // default port (will be overridden)
    var $debug      = false; // debug mode
    var $connected  = false; // connection status

    function connect($ip, $login, $password, $port = null)
    {
        // Override port if provided
        if ($port !== null) {
            $this->port = $port;
        }
        
        $this->debug('Connecting to ' . $ip . ':' . $this->port);
        
        // Create socket
        $this->socket = @fsockopen($ip, $this->port, $this->error_no, $this->error_str, $this->timeout);
        
        if (!$this->socket) {
            $this->debug('Could not create socket: ' . $this->error_str);
            return false;
        }
        
        // Set socket to non-blocking
        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, $this->timeout);
        
        // Read welcome message
        $welcome = fread($this->socket, 64);
        $this->debug('Welcome: ' . bin2hex($welcome));
        
        // Send login command
        $this->writeWord('/login');
        $response = $this->readResponse();
        
        if (isset($response[0]['ret'])) {
            $challenge = $response[0]['ret'];
            $hash = md5(chr(0) . $password . pack('H*', $challenge));
            $this->writeWord('/login', false);
            $this->writeWord('=name=' . $login);
            $this->writeWord('=response=00' . $hash);
            $response = $this->readResponse();
            
            if (isset($response[0]['ret']) && $response[0]['ret'] == 'done') {
                $this->connected = true;
                $this->debug('Login successful');
                return true;
            }
        }
        
        $this->debug('Login failed');
        return false;
    }
    
    function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->connected = false;
            $this->debug('Disconnected');
        }
    }
    
    function writeWord($word, $tag = true)
    {
        $len = strlen($word);
        if ($tag) {
            $len |= 0x8000;
        }
        fwrite($this->socket, pack('n', $len) . $word);
        $this->debug('Write: ' . $word);
    }
    
    function readResponse()
    {
        $response = [];
        while (true) {
            $header = fread($this->socket, 2);
            if (!$header) break;
            $len = unpack('n', $header)[1];
            $tag = ($len & 0x8000) != 0;
            $len &= 0x7FFF;
            if ($len == 0) break;
            $data = fread($this->socket, $len);
            if ($tag) {
                $response[] = ['ret' => $data];
                $this->debug('Read tag: ' . $data);
            } else {
                $parts = explode('=', $data, 2);
                if (count($parts) == 2) {
                    $response[0][$parts[0]] = $parts[1];
                    $this->debug('Read attr: ' . $parts[0] . ' = ' . $parts[1]);
                }
            }
        }
        return $response;
    }
    
    function write($command, $attrs = [])
    {
        $this->writeWord($command);
        foreach ($attrs as $key => $value) {
            $this->writeWord('=' . $key . '=' . $value, false);
        }
        $this->writeWord('');
        $this->debug('Write command: ' . $command);
    }
    
    function read()
    {
        return $this->readResponse();
    }
    
    function debug($msg)
    {
        if ($this->debug) {
            error_log('[RouterOS API] ' . $msg);
        }
    }
}
?>
