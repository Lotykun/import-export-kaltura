<?php

namespace UEFORMA\ImportExportKaltura;

class LoggerImplementation implements \Kaltura\Client\ILogger {
    public function log($msg){
        if (php_sapi_name() == 'cli'){
            //echo $msg.PHP_EOL;
        } else {
            //echo $msg.'<br />';
        }
    }
}