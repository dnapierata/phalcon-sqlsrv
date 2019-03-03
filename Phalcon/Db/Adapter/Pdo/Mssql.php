<?php

namespace Phalcon\Db\Adapter\Pdo;

use Phalcon\Db\Result\PdoMssql as ResultPdo;
use Phalcon\Db\Column as Column;

/**
 * Phalcon\Db\Adapter\Pdo\Mssql
 * Specific functions for the MsSQL database system
 * <code>
 * $config = array(
 * "name" => "mssql",
 * "host" => "192.168.0.11",
 * "dbname" => "blog",
 * "port" => 3306,
 * "username" => "sigma",
 * "password" => "secret"
 * );
 * $connection = new \Phalcon\Db\Adapter\Pdo\Sqlsrv($config);
 * </code>.
 *
 * @property \Phalcon\Db\Dialect\Mssql $_dialect
 */
class Mssql extends \Phalcon\Db\Adapter\Pdo implements \Phalcon\Db\AdapterInterface
{
    protected $_type = 'mssql';
    protected $_dialectType = 'mssql';

    /**
     * This method is automatically called in Phalcon\Db\Adapter\Pdo constructor.
     * Call it when you need to restore a database connection.
     *
     * @param array $descriptor
     *
     * @return bool
     */
    public function connect(array $descriptor = null)
    {
        if (is_null($descriptor) === true) {
            $descriptor = $this->_descriptor;
        }

        /*
         * Check if the developer has defined custom options or create one from scratch
         */
        if (isset($descriptor['options']) === true) {
            $options = $descriptor['options'];
            unset($descriptor['options']);
        } else {
            $options = array();
        }

        $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        $options[\PDO::ATTR_STRINGIFY_FETCHES] = true;
        $options[\PDO::SQLSRV_ATTR_FORMAT_DECIMALS] = true;
        
        if (!isset($descriptor['name'])) {
            $descriptor['name'] = $this->_type;
        }
        // Specific dsn strings
        $dsn='';
        switch ($descriptor['name']) {
            case 'sqlserv' :
            case 'sqlsrv' :
                $dsn = "{$descriptor['name']}:server={$descriptor['host']};database={$descriptor['dbname']}";
                break;
            case 'odbc' :
                $dsn = "odbc:{$descriptor['dsn']}";
                break;
            case 'dblib' :
            case 'sybase' :
            case 'mssql' :
            default :
                $dsn = "{$descriptor['name']}:host={$descriptor['host']};dbname={$descriptor['dbname']}";
                break;
        }

        $this->_pdo = new \PDO($dsn, $descriptor['username'], $descriptor['password'], $options);

//        $this->execute('SET QUOTED_IDENTIFIER ON');
//        $this->execute("SET ANSI_WARNINGS ON ");
//        $this->execute("SET ANSI_NULLS ON ");
//        $this->execute("SET NOCOUNT ON ");
//        $this->execute("SET XACT_ABORT ON ");

        /*
         * Set dialect class
         */
        if (isset($descriptor['dialectClass']) === false) {
            $dialectClass = "Phalcon\\Db\\Dialect\\Mssql";
        } else {
            $dialectClass = $descriptor['dialectClass'];
        }

        /*
         * Create the instance only if the dialect is a string
         */
        if (is_string($dialectClass) === true) {
            $dialectObject = new $dialectClass();
            $this->_dialect = $dialectObject;
        }
    }

    /**
     * Returns an array of Phalcon\Db\Column objects describing a table
     * <code>
     * print_r($connection->describeColumns("posts"));
     * </code>.
     *
     * @param string $table
     * @param string $schema
     *
     * @return \Phalcon\Db\Column
     */
    public function describeColumns($table, $schema = null)
    {
        $oldColumn = null;

        /*
         * Get primary keys
         */
        $primaryKeys = array();
        foreach ($this->fetchAll($this->_dialect->getPrimaryKey($table, $schema)) as $field) {
            $primaryKeys[$field['COLUMN_NAME']] = true;
        }

        /*
         * Get the SQL to describe a table
         * We're using FETCH_NUM to fetch the columns
         * Get the describe
         * Field Indexes: 0:name, 1:type, 2:not null, 3:key, 4:default, 5:extra
         */
        foreach ($this->fetchAll($this->_dialect->describeColumns($table, $schema)) as $field) {
            /*
             * By default the bind types is two
             */
            $definition = array('bindType' => Column::BIND_PARAM_STR);

            /*
             * By checking every column type we convert it to a Phalcon\Db\Column
             */
            $autoIncrement = false;
            $columnType = $field['TYPE_NAME'];
            switch ($columnType) {
                /*
                 * Smallint/Bigint/Integers/Int are int
                 */
                case 'int identity':
                case 'tinyint identity':
                case 'smallint identity':
                    $definition['type'] = Column::TYPE_INTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    $autoIncrement = true;
                    break;
                case 'bigint' :
                    $definition['type'] = Column::TYPE_BIGINTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    break;
                case 'decimal':
                case 'money':
                case 'smallmoney':
                    $definition['type'] = Column::TYPE_DECIMAL;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;
                case 'int':
                case 'tinyint':
                case 'smallint':
                    $definition['type'] = Column::TYPE_INTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    break;
                case 'numeric':
                    $definition['type'] = Column::TYPE_DOUBLE;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;
                case 'float':
                    $definition['type'] = Column::TYPE_FLOAT;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;

                /*
                 * Boolean
                 */
                case 'bit':
                    $definition['type'] = Column::TYPE_BOOLEAN;
                    $definition['bindType'] = Column::BIND_PARAM_BOOL;
                    break;

                /*
                 * Date are dates
                 */
                case 'date':
                    $definition['type'] = Column::TYPE_DATE;
                    break;

                /*
                 * Special type for datetime
                 */
                case 'datetime':
                case 'datetime2':
                case 'smalldatetime':
                    $definition['type'] = Column::TYPE_DATETIME;
                    break;

                /*
                 * Timestamp are dates
                 */
                case 'timestamp':
                    $definition['type'] = Column::TYPE_TIMESTAMP;
                    break;

                /*
                 * Chars are chars
                 */
                case 'char':
                case 'nchar':
                    $definition['type'] = Column::TYPE_CHAR;
                    break;

                case 'varchar':
                case 'nvarchar':
                    $definition['type'] = Column::TYPE_VARCHAR;
                    break;

                /*
                 * Text are varchars
                 */
                case 'text':
                case 'ntext':
                    $definition['type'] = Column::TYPE_TEXT;
                    break;

                /*
                 * blob type
                 */
                case 'image' : 
                case 'varbinary':
                    $definition['type'] = Column::TYPE_BLOB;
                    $definition['bindType'] = Column::BIND_PARAM_BLOB;
                    break;

                /*
                 * By default is string
                 */
                default:
                    $definition['type'] = Column::TYPE_VARCHAR;
                    break;
            }

            /*
             * If the column type has a parentheses we try to get the column size from it
             */
            $definition['size'] = (int) $field['LENGTH'];
            $definition['precision'] = (int) $field['PRECISION'];

            if ($field['SCALE'] || $field['SCALE'] == '0') {
                //                $definition["scale"] = (int) $field['SCALE'];
                $definition['size'] = $definition['precision'];
            }

            /*
             * Positions
             */
            if (!$oldColumn) {
                $definition['first'] = true;
            } else {
                $definition['after'] = $oldColumn;
            }

            /*
             * Check if the field is primary key
             */
            if (isset($primaryKeys[$field['COLUMN_NAME']])) {
                $definition['primary'] = true;
            }

            /*
             * Check if the column allows null values
             */
            if ($field['NULLABLE'] == 0) {
                $definition['notNull'] = true;
            }

            /*
             * Check if the column is auto increment
             */
            if ($autoIncrement) {
                $definition['autoIncrement'] = true;
            }

            /*
             * Check if the column is default values
             */
            if ($field['COLUMN_DEF'] != null) {
                $definition['default'] = $field['COLUMN_DEF'];
            }

            $columnName = $field['COLUMN_NAME'];
            $columns[] = new Column($columnName, $definition);
            $oldColumn = $columnName;
        }
        
        return $columns;
    }

    /**
     * Sends SQL statements to the database server returning the success state.
     * Use this method only when the SQL statement sent to the server is returning rows
     * <code>
     * //Querying data
     * $resultset = $connection->query("SELECTFROM robots WHERE type='mechanical'");
     * $resultset = $connection->query("SELECTFROM robots WHERE type=?", array("mechanical"));
     * </code>.
     *
     * @param string $sqlStatement
     * @param mixed  $bindParams
     * @param mixed  $bindTypes
     *
     * @return bool|\Phalcon\Db\ResultInterface
     */
    public function query($sqlStatement, $bindParams = null, $bindTypes = null)
    {
        $eventsManager = $this->_eventsManager;

        /*
         * Execute the beforeQuery event if a EventsManager is available
         */
        if (is_object($eventsManager)) {
            $this->_sqlStatement = $sqlStatement;
            $this->_sqlVariables = $bindParams;
            $this->_sqlBindTypes = $bindTypes;

            if ($eventsManager->fire('db:beforeQuery', $this, $bindParams) === false) {
                return false;
            }
        }

        $pdo = $this->_pdo;

        $cursor = \PDO::CURSOR_SCROLL;
        if (strpos($sqlStatement, 'exec') !== false) {
            $cursor = \PDO::CURSOR_FWDONLY;
        }

        // Get the row count with this query by calling the @@RowCount function

        if (is_array($bindParams)) {
            $statement = $pdo->prepare($sqlStatement);
            if (is_object($statement)) {
                $statement = $this->executePrepared($statement, $bindParams, $bindTypes);
            }
        } else {
            $statement = $pdo->prepare($sqlStatement);
            $statement->execute();
        }

        /*
         * Execute the afterQuery event if a EventsManager is available
         */
        if (is_object($statement)) {
            
            if (is_object($eventsManager)) {
                $eventsManager->fire('db:afterQuery', $this, $bindParams);
            }

            $result = new ResultPdo($this, $statement, $sqlStatement, $bindParams, $bindTypes);
            return $result;
        }

        return $statement;
    }
    
    
    public function fetchOne($sqlQuery, $fetchMode = \Phalcon\Db::FETCH_ASSOC, $bindParams = null, $bindTypes = null) {
        $result = $this->query($sqlQuery, $bindParams, $bindTypes);
        $result->setFetchMode($fetchMode);
        return $result->fetch();
    }
    
    /**
     * Executes a prepared statement binding. This function uses integer indexes starting from zero
     *
     *<code>
     * use Phalcon\Db\Column;
     *
     * $statement = $db->prepare(
     *     "SELECT * FROM robots WHERE name = :name"
     * );
     *
     * $result = $connection->executePrepared(
     *     $statement,
     *     [
     *         "name" => "Voltron",
     *     ],
     *     [
     *         "name" => Column::BIND_PARAM_INT,
     *     ]
     * );
     *</code>
     *
     * @param \PDOStatement statement
     * @param array placeholders
     * @param array dataTypes
     * @return \PDOStatement
     */
    // Transcribed to php 
    public function executePrepared(\PDOStatement $statement, array $placeholders, $dataTypes)
    {
        foreach ($placeholders as $wildcard => $value) {
            if (gettype($wildcard) == "integer") {
                $parameter = $wildcard + 1;
            } elseif (gettype($wildcard) == "string") {
                $parameter = $wildcard;
            } else {
                throw new \Exception("Invalid bind parameter (1)");
            }
            
            if (gettype($dataTypes) == "array" && isset($dataTypes[$wildcard])) { 
                $type = $dataTypes[$wildcard];
                
                /**
                 * The bind type is double so we try to get the double value
                 */
                if ($type == Column::BIND_PARAM_DECIMAL) {
                    $castValue = doubleval($value);
                    $type = Column::BIND_SKIP;
                } else {
                    // globals_get("db.force_casting")
                    if (false) {
                        if (gettype($value != "array")) {
                            switch ($type) {
                                
                                case Column::BIND_PARAM_INT:
                                    $castValue = intval($value, 10);
                                    break;
                                    
                                case Column::BIND_PARAM_STR:
                                    $castValue = (string) $value;
                                    break;
                                    
                                case Column::BIND_PARAM_NULL:
                                    $castValue = null;
                                    break;
                                    
                                case Column::BIND_PARAM_BOOL:
                                    $castValue = (boolean) $value;
                                    break;
                                    
                                default:
                                    $castValue = $value;
                                    break;
                            }
                        } else {
                            $castValue = $value;
                        }
                    } else {
                        $castValue = $value;
                    }
                }
                
                /**
                 * 1024 is ignore the bind type
                 */
                if (gettype($castValue) != "array") {
                    if ($type == Column::BIND_SKIP) {
                        $statement->bindValue($parameter, $castValue);
                    } else {
                        if ($type == Column::BIND_PARAM_BLOB) {
                            // Prevent by-reference overwrites
                            $param = $parameter;
                            $cValue = $castValue;
                            // Bind Blobs using bindParam
                            $statement->bindParam($param, $cValue, \PDO::PARAM_LOB, 0, \PDO::SQLSRV_ENCODING_BINARY);
                        } else {
                            $statement->bindValue($parameter, $castValue, $type);
                        }
                    }
                } else {
                    foreach ($castValue as $position => $itemValue) {
                        if ($type == Column::BIND_SKIP) {
                            $statement->bindValue($parameter . $position, $itemValue);
                        } else {
                            $statement->bindValue($parameter . $position, $itemValue, $type);
                        }
                    }
                }
            } else {
                if (gettype($value) != "array") {
                    $statement->bindValue($parameter, $value);
                } else {
                    foreach ($value as $position => $itemValue) {
                        $statement->bindValue($parameter . $position, $itemValue);
                    }
                }
            }
        }
        
        $statement->execute();
        return $statement;
    }
}
