<?php

class MyLogger {

    public $logFile;

    public function __construct($logFile) {
        $this->logFile = $logFile;
    }

    public function log($message) {
        date_default_timezone_set('Asia/Bishkek');
        $text = date('Y-m-d H:i:s') . ': ' . $message . "\n";
        $open = fopen($this->logFile, 'a');
        fwrite($open, $text);
        fclose($open);
    }
}