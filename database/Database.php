<?php

namespace Database;

use PDO;
use PDOException;

class Database
{
    private PDO $connection;

    private array $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ];

    public function __construct()
    {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USERNAME,
                DB_PASSWORD,
                $this->options
            );
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit;
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function select($sql, $values = null)
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($values ?? []);
            return $stmt;
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit;
        }
    }

    public function query($sql, $values = [])
    {
        return $this->select($sql, $values);
    }

    public function insert($tableName, array $fields, array $values, bool $timestamps = true)
    {
        try {
            $placeholders = ':' . implode(', :', $fields);
            $fieldList = implode(', ', $fields);
            $sql = "INSERT INTO {$tableName} ({$fieldList}";
            if ($timestamps) {
                $sql .= ', created_at, updated_at) VALUES (' . $placeholders . ', NOW(), NOW())';
            } else {
                $sql .= ') VALUES (' . $placeholders . ')';
            }
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_combine($fields, $values));
            return true;
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit;
        }
    }

    public function insertRow($tableName, array $data, bool $createdOnly = false)
    {
        $fields = array_keys($data);
        $values = array_values($data);
        if ($createdOnly) {
            $placeholders = ':' . implode(', :', $fields);
            $sql = 'INSERT INTO ' . $tableName . ' (' . implode(', ', $fields) . ', created_at) VALUES (' . $placeholders . ', NOW())';
        } else {
            return $this->insert($tableName, $fields, $values, true);
        }
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(array_combine($fields, $values));
        return true;
    }

    public function update($tableName, $id, array $fields, array $values)
    {
        $sets = [];
        $params = [];
        foreach (array_combine($fields, $values) as $field => $value) {
            $sets[] = '`' . $field . '` = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $sql = 'UPDATE ' . $tableName . ' SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return true;
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit;
        }
    }

    public function delete($tableName, $id)
    {
        $stmt = $this->connection->prepare('DELETE FROM ' . $tableName . ' WHERE id = ?');
        $stmt->execute([$id]);
        return true;
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }
}
