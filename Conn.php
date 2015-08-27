<?php

/*
 * The MIT License (MIT)
 * 
 * Copyright (c) 2015 Willian Girhad
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class Conn extends PDO {
    private static $instance;

    function __construct() {
        include ('database.conf.php');
        parent::__construct($conf['DSN'], $conf['user'], $conf['password']);
        $this->exec("SET CHARACTER SET utf8");
    }

    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function fetchTableData($table, $field = null, $value = null, $operator = "=", $orderby = array(), $invert = false, $limit = false, $fields = "*") {
        $where = array();
        if ($value !== null && $field !== '') {
            if ($invert) {
                $where[0]['query'] = "? $operator $field";
            } else {
                $where[0]['query'] = "$field $operator ?";
            }
            $where[0]['param'] = $value;
        }
        return $this->fetchTableDataF($tabela, $where, $orderby, $limit, $fields);
    }

    public function fetchTableDataF($table, $filter = array(), $orderby = array(), $limit = false, $fields = "*") {
        $param = [];
        $where = [];

        foreach ($filter as $key => $value) {
            array_push($param, $value['param']);
            array_push($where, $value['query']);
        }

        $where = implode(" AND ", $where);
        if (strlen(trim($where)) > 0) $where = "WHERE $where";
        

        if ($limit !== false) {
            $limit = (int) $limit;
            $limit = "LIMIT $limit";
        }

        if (is_array($orderby)) {
            $orderby = implode(', ', array_filter($orderby));
            if (strlen(trim($orderby)) > 0) {
                $orderby = "ORDER BY $orderby";
            }
        } else {
            $orderby = '';
        }

        $sql = "SELECT $fields FROM $table $where $orderby $limit";

        return $this->getSQLArray($sql, $param);
    }

    public function assembleFilter($values, $operator = "=") {
        $result = array();

        $isContaining = ($operator == "CONTAINING");

        foreach ($values as $key => $value) {
            if ($isContaining) {
                $op = "LIKE";
                $value = "%$value%";
            } else {
                $op = $operator;
            }

            array_push($result, array(
                "query" => "$key $op ?",
                "param" => $value
            ));
        }

        return $result;
    }

    public function fetchTableMeta($table) {
        $sql =
        "SELECT
            COLUMN_NAME,
            DATA_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = ?
        ";

        $array = $this->getSQLArray($sql, array($table));

        $result = array();

        foreach ($array as $value) {
            $result[$value['COLUMN_NAME']] = $value['DATA_TYPE'];
        }

        return $result;
    }

    public function fetchTablePK($table) {
        $sql =
        "SELECT DISTINCT
            COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            UPPER(TABLE_NAME) = UPPER(?)
        AND COLUMN_KEY =  'PRI'
        ";

        $array = $this->getSQLArray($sql, array($table));

        return $array[0]["COLUMN_NAME"];
    }

    public function getSQLArray($sql, $param = array()) {
        $result = array();

        $stmt = $this->prepare($sql);

        if (!$stmt->execute($param)) return $result;

        while ($row = $stmt->fetch(parent::FETCH_ASSOC)) {
            array_push($result, $row);
        }

        return $result;
    }

    public function executeSQL($sql, $param = array()) {
        $stmt = $this->prepare($sql);

        if (!$stmt->execute($param)) {
            $err = $stmt->errorInfo();
            throw new Exception($err[2], $err[0]);
            return false;
        }

        return true;
    }

    public function runInsert($sql, $param = array()) {
        $result = false;

        $this->beginTransaction();
        $stmt = $this->prepare($sql);

        if ($stmt->execute($param)) {
            $result = $this->lastInsertId();
            $this->commit();
        } else {
            $err = $stmt->errorInfo();
            $this->rollBack();
            throw new Exception($err[2], $err[0]);
        }

        return $result;
    }
}
