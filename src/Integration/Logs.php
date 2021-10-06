<?php

/*
 * This file is part of the HAISTAR Core Integration.
 *
 * (c) Nanda Firmansyah <nafima21@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Haistar\Integration;

use Haistar\Curl;

date_default_timezone_set("Asia/Bangkok");

class Logs
{    
    private $url;
    private $header;
    private $body;

    public function __construct(String $path, String $func, String $filename, String $log)
    {
        $this->url = "54.169.64.89:3000/logger";
        $this->body = array(
            "path"         => trim($path, "/")."/".$func."/".date('Y-m')."/".date('d')."/",
            "keyGenSecret" => base64_encode(explode("/", $path)[0].",Haistari,".date('yyyy-mm-dd', time()).",HAISTAR"),
            "function"     => $func,
            "fileName"     => $filename,
            "log"          => $log
        );

        $this->header = [
            "Cache-Control: no-cache",
            "Content-type: application/json",
            "User-Agent: Haistar Service v.0.1",
            "Content-Length: " . strlen(json_encode($this->body))
        ];
    }

    public function send()
    {
        $curl = new Curl($this->url);
        $curl->setHeader($this->header);
        $curl->setMethod("POST");
        if(!is_null($this->body))
        {
            $curl->setBody(json_encode($this->body));
        }
        $result = json_decode($curl->exec(), true);
        $error = $curl->error();
        return $result;
    }
}