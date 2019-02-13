<?php

namespace Phalcon\Db\Result;

/**
 * Phalcon\Db\Result\PdoSqlsrv
 * Encapsulates the resultset internals
 * <code>
 * $result = $connection->query("SELECT * FROM robots ORDER BY name");
 * $result->setFetchMode(Phalcon\Db::FETCH_NUM);
 * while ($robot = $result->fetchArray()) {
 * print_r($robot);
 * }
 * </code>.
 */
class PdoMssql extends Pdo
{
    public $_rowCount = false;
    private $_cursor_pos = 0;
    private $_fetch_mode = \Phalcon\Db::FETCH_ASSOC;
    private $_rows, $_obj_rows, $_numeric_rows = null;
    /**
     * Gets number of rows returned by a resultset
     * <code>
     * $result = $connection->query("SELECT * FROM robots ORDER BY name");
     * echo 'There are ', $result->numRows(), ' rows in the resultset';
     * </code>.
     *
     * @return int
     */
    public function numRows()
    {
        if ($this->_rowCount === false) {
            $this->_rowCount = $this->_pdoStatement->rowCount();
            if ($this->_rowCount === -1) {
                // Do the fetch now and store it
                $this->fetchAll();
                
                $this->_rowCount = is_array($this->_rows) ? count($this->_rows) : false;
            }
        }

        return $this->_rowCount;
    }
    
    public function fetch($fetchStyle = null, $cursorOrientation = null, $cursorOffset = null) {
        $rows = $this->fetchAll($fetchStyle);
        $row = (isset($rows[$this->_cursor_pos])) ? $rows[$this->_cursor_pos] : false;
        $this->_cursor_pos++;
        return $row;
    }
    
    public function fetchArray() {
        return $this->fetchAll();
    }
    
    public function fetchAll($fetchStyle = null, $fetchArgument = null, $ctorArgs = null) {
        if ($fetchStyle !== null) {
            $this->setFetchMode($fetchStyle);
        }
        if ($this->_rows === null) {
            $this->_rows = $this->_pdoStatement->fetchAll(\Phalcon\Db::FETCH_ASSOC);
        }
        $rows = $this->_rows;
        
        if ($this->_fetch_mode === \Phalcon\Db::FETCH_OBJ) {
            if ($this->_obj_rows === null) {
                $this->_obj_rows = [];
                foreach ($this->_rows as $row) {
                    $this->_obj_rows[] = (object) $row;
                }
            }
            $rows = $this->_obj_rows;
        } else if ($this->_fetch_mode === \Phalcon\Db::FETCH_NUM) {
            if ($this->_numeric_rows === null) {
                $this->_numeric_rows = [];
                foreach ($this->_rows as $row) {
                    $this->_numeric_rows[] = array_values($row);
                }
            }
            $rows = $this->_numeric_rows;
        }
        return $rows;
    }
    
    public function dataSeek($number) {
        $this->_cursor_pos = $number;
    }
    
    public function setFetchMode($fetchMode, $colNoOrClassNameOrObject = NULL, $ctorargs = NULL) {
        $this->_fetch_mode = $fetchMode;
    }
}
