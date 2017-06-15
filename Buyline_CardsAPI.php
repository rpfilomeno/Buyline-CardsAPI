<?php
/**
 * User: Roger Filomeno
 * Date: 6/11/2017
 * Time: 5:49 PM
 */

class Buyline_CardsAPI {

    private $host;
    private $port;
    private $timeout;
    private $passPhrase;
    private $pemFile;
    private $socket;

    private $clientId;

    private $comment;
    private $logfile;
    private $logSequence;
    private $debug;
    private $verbose;


    private $param;
    private $result;

    private $txnReference;
    private $responseCode;
    private $responseText;

    private $sessionId;


    public function __construct() {
        $this->sessionId = substr(md5(rand(0,1000).date("YmdHisu")),0,30);
        $this->logSequence=0;
        $this->reset();
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

    public function purchase($pan, $expiry, $amount, $cvv){
        $amount = sprintf("%.2f", $amount); //make sure it has decimal value

        if($this->getCardType($pan) === "AMEX") {
            $cvv = str_pad($cvv, 4, '0', STR_PAD_LEFT);
        } else {
            $cvv = str_pad($cvv, 3, '0', STR_PAD_LEFT);
        }

        $this->put("TRANSACTIONTYPE",'PURCHASE');
        $this->put("CARDDATA",$pan);
        $this->put("CARDEXPIRYDATE",$expiry);
        $this->put("TOTALAMOUNT",$amount);
        $this->put("CVV",$cvv);

        $this->buildParam();
        $this->checkParam();



        while(!$this->responseCode) {

            if($this->txnReference) {
                $this->put("TRANSACTIONTYPE",'STATUS');
                $this->put("CARDEXPIRYDATE",'****');
                $this->put("TXNREFERENCE",$this->txnReference);
                $this->put("TOTALAMOUNT",$amount);
                $this->put("CARDDATA",$this->maskPan($pan));
                $this->put("CVV",'****');

                $this->buildParam();
                $this->checkParam();

            }

            $this->execute();
            $this->responseCode = $this->get("RESPONSECODE");
            $this->responseText = $this->get("RESPONSETEXT");
            $this->txnReference = $this->get("TXNREFERENCE");

        }
    }

    private function reset(){
        $this->host = "trans.buylineplus.co.nz";
        $this->port ="3008";
        $this->timeout = 5;
        $this->passPhrase = "";
        $this->pemFile = "";

        $this->clientId = "10000000";
        $this->logfile = "webpay.log";
        $this->comment = "";

        $this->debug = true;
        $this->verbose =true;

        $this->param = array();
        $this->result = array();

        $this->responseCode = null;
        $this->txnReference = null;

    }

    private function execute() {

        $this->result = array();

        if(!$this->socket)
            $this->socket = $this->createSocket(
                $this->pemFile,
                $this->passPhrase,
                $this->host,
                $this->port,
                $this->timeout
            );

        $this->writeSocket();
        $this->readSocket();
    }


    private function maskPan($number, $maskingCharacter = 'X', $prefix = 6, $postfix = 4) {
        $postfix = $postfix * -1;
        return substr($number, 0, $prefix) . str_repeat($maskingCharacter, strlen($number) - 10) . substr($number, $postfix);
    }




    private function put($key, $value) {
        $this->param[$key] = $value;
    }

    public function get($key,$default=null) {
        if(!array_key_exists($key,$this->result)) return $default;
        return $this->result[$key];
    }

    private function writeSocket() {
        $command = $this->getParamString();
        $client_bytes_written = fwrite($this->socket, $command);
        $this->log("$client_bytes_written\t". $command,'WRITE');
        if(!$client_bytes_written) throw new Exception("Cannot send to gateway.");
    }

    private function readSocket()
    {
        $response = null;
        while (!feof($this->socket)) {
            $response .= fread($this->socket, 20240);
            if (preg_match('/\x0D\x0AEND\x0D\x0A\x0D\x0A/', $response)) break;
        }

        $client_bytes_read = mb_strlen($response, "8bit");
        $this->log("$client_bytes_read\t" . $response . "",'READ');
        preg_match_all('/(\N*)=(.*)/', $response, $result, PREG_PATTERN_ORDER);

        foreach($result[1] as $i => $key) {
            $value = $result[2][$i];
            $this->result[trim($key)] = trim($value);
        }
    }


    private function checkParam(){
        if(!array_key_exists("TRANSACTIONTYPE",$this->param))
            throw new Exception("TRANSACTIONTYPE not defined.");
        if(!in_array($this->param["TRANSACTIONTYPE"],['PURCHASE','STATUS']) )
            throw new Exception("TRANSACTIONTYPE value ". $this->param["TRANSACTIONTYPE"] ." is invalid.");
    }

    private function buildParam() {
        $fixedParams = [
            "WEBPAYCLIENTTYPE" => "WebpayClient (ANSI)",
            "VERSION" => "3.3",
            "WEBPAYCLIENTVERSION" => "1.17",
            "CLIENTTYPE" => "webpayPHP5",
            "INTERFACE"=>"CREDITCARD",
        ];
        $propertyParams = [
            "SERVER0" => $this->host,
            "SERVER" => $this->host,
            "SERVERPORT" => $this->port,
            "LOGFILE" => $this->logfile,
            "CLIENTID" => $this->clientId,
            "COMMENT" => $this->comment,
            "SERVERCOUNT" => 1,
            "DEBUG" => ($this->debug===true) ? "ON" : "OFF"
        ];

        $this->param = array_merge($this->param, $propertyParams);
        $this->param = array_merge($this->param, $fixedParams);

    }

    private function getParamString()
    {
        $paramString = "";
        foreach($this->param as $key => $value) {
            $paramString .= "$key=$value\x0D\x0A";
        }
        $paramString .= "\x0D\x0AEND\x0D\x0A\x0D\x0A";
        return $paramString;
    }


    private function createSocket($pem, $passphrase, $ip, $port, $timeout=30, $retry=3)
    {
        $errno = null;
        $errstr = null;

        $context = stream_context_create(
            array(
                'ssl' => array(
                    'local_cert' => $pem,
                    'passphrase' => $passphrase,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            )
        );

        //Connect to Server
        for ( $i = 0; $i < $retry; $i++ ) {
            $socket = stream_socket_client("ssl://{$ip}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            if ($socket) return $socket;
        }
        throw new Exception("Cannot connect to " . $ip);
    }


    private function log($string,$type='NOTICE')
    {

        //$allowed = array("\x0D","\x0A");
        $allowed = array();
        $buff = "";

        // iterate through string
        for($i=0; $i < strlen($string); $i++) {

            // check if current char is printable
            if(ctype_print($string[$i]) || in_array($string[$i], $allowed)) {
                $buff .= $string[$i];
            } else {
                // use printf and ord to print the hex value if
                // it is a non printable character
                $buff .= sprintf("\\x%02X", ord($string[$i]));
            }
        }
        $this->logSequence++;
        $message = date("M d H:i:s", time())."\t".$this->sessionId.':'. $this->logSequence ."\t".$type. "\t" .$buff."\n";

        if( $this->debug ) {
            error_log($message, 3, $this->logfile);
            if( $this->verbose ) echo $message;
        }

    }

    function getCardType($number)
    {
        $number=preg_replace('/[^\d]/','',$number);

        if (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
            return 'MASTERCARD';
        }
        elseif (preg_match('/^4[0-9]{12}([0-9]{3})$/', $number)) {
            return 'VISA';
        }
        elseif (preg_match('/^3[47][0-9]{13}$/', $number)) {
            return 'AMEX';
        }
        elseif (preg_match('/^3(0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
            return 'DINNERS';
        }
        elseif (preg_match('/^6011[0-9]{12}$/', $number)) {
            return 'DISCOVER';
        }
        elseif (preg_match('/^(3[0-9]{4}|2131|1800)[0-9]{11}$/', $number)) {
            return 'JCB';
        }
        elseif (preg_match('/^(5[06-8]|6)[0-9]{10,17}$/', $number)) {
            return 'MAESTRO';
        } else {
            return 'UNKNOWN';
        }
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }


    /**
     * @param mixed $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }


    /**
     * @param mixed $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }


    /**
     * @param mixed $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param mixed $passPhrase
     */
    public function setPassPhrase($passPhrase)
    {
        $this->passPhrase = $passPhrase;
    }

    /**
     * @param mixed $pemFile
     */
    public function setPemFile($pemFile)
    {
        $this->pemFile = $pemFile;
    }

    /**
     * @param mixed $logfile
     */
    public function setLogfile($logfile)
    {
        $this->logfile = $logfile;
    }

    /**
     * @param mixed $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * @param mixed $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @param mixed $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param mixed $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return mixed
     */
    public function getTxnReference()
    {
        return $this->txnReference;
    }

    /**
     * @return mixed
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @return mixed
     */
    public function getResponseText()
    {
        return $this->responseText;
    }


}
