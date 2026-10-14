<?php
/**
 * Simple RouterOS API Client
 */
class RouterOSAPI {
    private $socket;
    
    public function connect($host, $port, $user, $pass) {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$this->socket) {
            return false;
        }
        
        stream_set_timeout($this->socket, 5);
        
        // Read banner
        fread($this->socket, 1024);
        
        // Login
        fwrite($this->socket, "/login\n");
        $challenge = fread($this->socket, 1024);
        
        if (preg_match('/=ret=([a-f0-9]+)/', $challenge, $matches)) {
            $response = md5(chr(0) . $pass . pack('H*', $matches[1]));
            fwrite($this->socket, "/login\n=response=00" . $response . "\n");
            $login_result = fread($this->socket, 1024);
            
            if (strpos($login_result, '!done') === false) {
                return false;
            }
        }
        
        return true;
    }
    
    public function command($cmd) {
        fwrite($this->socket, $cmd . "\n");
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 1024);
            if ($line === false) break;
            $response .= $line;
            if (strpos($line, '!done') !== false) break;
        }
        return $response;
    }
    
    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
        }
    }
}
?>
