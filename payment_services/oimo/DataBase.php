<?php
// chdir('../../');
class DataBase
{
    public static function getConn()
    {
        require 'config.inc.php';
        $host = $dbconfig['db_server'];
        $user = $dbconfig['db_username'];
        $pass = $dbconfig['db_password'];
        $name = $dbconfig['db_name'];

        $conn = new mysqli($host, $user, $pass, $name);

        if ($conn->connect_errno) {
            throw new Exception('MySQL error: ' . $conn->connect_error);
        }

        $conn->query('set names utf8');

        return $conn;
    }

}