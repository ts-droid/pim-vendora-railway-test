<?php

namespace App\Services;

use PDO;

class RemoteDatabaseService
{
    public PDO $db;

    private $preparedStatements = [];

    function __construct(string $host = '', string $port = '', string $database = '', string $username = '', string $password = '')
    {
        $this->db = new PDO(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database,
            $username,
            $password,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function execute(string $sql, array $values = [])
    {
        $sqlHash = hash('sha256', $sql);

        if (isset($this->preparedStatements[$sqlHash])) {
            $statement = $this->preparedStatements[$sqlHash];
        }
        else {
            $statement = $this->db->prepare($sql);
            $this->preparedStatements[$sqlHash] = $statement;
        }

        $statement->execute($values);
        return $statement;
    }

    public function fetch(string $sql, array $values = [], int $fetchStyle = PDO::FETCH_ASSOC)
    {
        $statement = $this->execute($sql, $values);
        $result = $statement->fetch($fetchStyle);
        $statement->closeCursor();
        return $result;
    }

    public function fetchAll(string $sql, array $values = [], int $fetchStyle = PDO::FETCH_ASSOC)
    {
        return $this->execute($sql, $values)->fetchAll($fetchStyle);
    }
}
