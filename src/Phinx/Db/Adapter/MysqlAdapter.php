<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 * 
 * @package    Phinx
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\ForeignKey;

/**
 * Phinx MySQL Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
class MysqlAdapter extends PdoAdapter implements AdapterInterface
{

    protected $signedColumnTypes = array('integer' => true, 'biginteger' => true, 'float' => true, 'decimal' => true);

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if (null === $this->connection) {
            if (!class_exists('PDO') || !in_array('mysql', \PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_Mysql extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }
            
            $db = null;
            $options = $this->getOptions();
            
            // if port is specified use it, otherwise use the MySQL default
            if (empty($options['port'])) {
                $dsn = 'mysql:host=' . $options['host'] . ';dbname=' . $options['name'];
            } else {
                $dsn = 'mysql:host=' . $options['host'] . ';port=' . $options['port'] . ';dbname=' . $options['name'];
            }

            // unix socket support
            if (isset($options['unix_socket'])) {
                $dsn .= ';unix_socket=' . $options['unix_socket'];
            }
            
            // charset support
            if (isset($options['charset'])) {
                $dsn .= ';charset=' . $options['charset'];
            }

            $driverOptions = array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION);

            // support arbitrary \PDO::MYSQL_ATTR_* driver options and pass them to PDO
            // http://php.net/manual/en/ref.pdo-mysql.php#pdo-mysql.constants
            foreach ($options as $key => $option) {
                if (strpos($key, 'mysql_attr_') === 0) {
                    $driverOptions[constant('\PDO::' . strtoupper($key))] = $option;
                }
            }

            try {
                $db = new \PDO($dsn, $options['user'], $options['pass'], $driverOptions);
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
                ));
            }

            $this->setConnection($db);
            
            // Create the schema table if it doesn't already exist
            if (!$this->hasSchemaTable()) {
                $this->createSchemaTable();
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->connection = null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasTransactions()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->execute('START TRANSACTION');
    }
    
    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        $this->execute('COMMIT');
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        $this->execute('ROLLBACK');
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return str_replace('.', '`.`', $this->quoteColumnName($tableName));
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '`' . str_replace('`', '``', $columnName) . '`';
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $options = $this->getOptions();
        
        $tables = array();
        $rows = $this->fetchAll(sprintf('SHOW TABLES IN `%s`', $options['name']));
        foreach ($rows as $row) {
            $tables[] = strtolower($row[0]);
        }
        
        return in_array(strtolower($tableName), $tables);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table)
    {
        $this->startCommandTimer();

        // This method is based on the MySQL docs here: http://dev.mysql.com/doc/refman/5.1/en/create-index.html
        $defaultOptions = array(
            'engine' => 'InnoDB',
            'collation' => 'utf8_general_ci'
        );
        $options = array_merge($defaultOptions, $table->getOptions());
        
        // Add the default primary key
        $columns = $table->getPendingColumns();
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $column = new Column();
            $column->setName('id')
                   ->setType('integer')
                   ->setSigned(false)
                   ->setIdentity(true);
            
            array_unshift($columns, $column);
            $options['primary_key'] = 'id';

        } elseif (isset($options['id']) && is_string($options['id'])) {
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                   ->setType('integer')
                   ->setIdentity(true);

            array_unshift($columns, $column);
            $options['primary_key'] = $options['id'];
        }
        
        // TODO - process table options like collation etc
        
        // process table engine (default to InnoDB)
        $optionsStr = 'ENGINE = InnoDB';
        if (isset($options['engine'])) {
            $optionsStr = sprintf('ENGINE = %s', $options['engine']);
        }
        
        // process table collation
        if (isset($options['collation'])) {
            $charset = explode('_', $options['collation']);
            $optionsStr .= sprintf(' CHARACTER SET %s', $charset[0]);
            $optionsStr .= sprintf(' COLLATE %s', $options['collation']);
        }
        
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';
        }
        
        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            $sql .= ' PRIMARY KEY (';
            if (is_string($options['primary_key'])) {       // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                // PHP 5.4 will allow access of $this, so we can call quoteColumnName() directly in the
                // anonymous function, but for now just hard-code the adapter quotes
                $sql .= implode(
                    ',',
                    array_map(
                        function ($v) {
                            return '`' . $v . '`';
                        },
                        $options['primary_key']
                    )
                );
            }
            $sql .= ')';
        } else {
            $sql = substr(rtrim($sql), 0, -1);              // no primary keys
        }
        
        // set the indexes
        $indexes = $table->getIndexes();
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql .= ', ' . $this->getIndexSqlDefinition($index);
            }
        }

        // set the foreign keys
        $foreignKeys = $table->getForeignKeys();
        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $foreignKey) {
                $sql .= ', ' . $this->getForeignKeySqlDefinition($foreignKey);
            }
        }

        $sql .= ') ' . $optionsStr;
        $sql = rtrim($sql) . ';';

        // execute the sql
        $this->writeCommand('createTable', array($table->getName()));
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $this->startCommandTimer();
        $this->writeCommand('renameTable', array($tableName, $newTableName));
        $this->execute(sprintf('RENAME TABLE %s TO %s', $this->quoteTableName($tableName), $this->quoteTableName($newTableName)));
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropTable', array($tableName));
        $this->execute(sprintf('DROP TABLE %s', $this->quoteTableName($tableName)));
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $columns = array();
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $tableName));
        foreach ($rows as $columnInfo) {
            $column = new Column();
            $column->setName($columnInfo['Field'])
                   ->setType($columnInfo['Type'])
                   ->setNull($columnInfo['Null'] != 'NO')
                   ->setDefault($columnInfo['Default']);

            $phinxType = $this->getPhinxType($columnInfo['Type']);
            $column->setType($phinxType['name'])
                   ->setLimit($phinxType['limit']);

            if ($columnInfo['Extra'] == 'auto_increment') {
                $column->setIdentity(true);
            }

            $columns[] = $column;
        }

        return $columns;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName)
    {
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $tableName));
        foreach ($rows as $column) {
            if (strcasecmp($column['Field'], $columnName) === 0) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $this->startCommandTimer();
        $sql = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );
        
        if ($column->getAfter()) {
            $sql .= ' AFTER ' . $this->quoteColumnName($column->getAfter());
        }
        
        $this->writeCommand('addColumn', array($table->getName(), $column->getName(), $column->getType()));
        $this->execute($sql);
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $this->startCommandTimer();
        $rows = $this->fetchAll(sprintf('DESCRIBE %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            if (strcasecmp($row['Field'], $columnName) === 0) {
                $null = ($row['Null'] == 'NO') ? 'NOT NULL' : 'NULL';
                $extra = ' ' . strtoupper($row['Extra']);
                $definition = $row['Type'] . ' ' . $null . $extra;
        
                $this->writeCommand('renameColumn', array($tableName, $columnName, $newColumnName));
                $this->execute(
                    sprintf(
                        'ALTER TABLE %s CHANGE COLUMN %s %s %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($columnName),
                        $this->quoteColumnName($newColumnName),
                        $definition
                    )
                );
                $this->endCommandTimer();
                return;
            }
        }
        
        throw new \InvalidArgumentException(sprintf(
            'The specified column doesn\'t exist: '
            . $columnName
        ));
    }
    
    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $this->startCommandTimer();
        $this->writeCommand('changeColumn', array($tableName, $columnName, $newColumn->getType()));
        $this->execute(
            sprintf(
                'ALTER TABLE %s CHANGE %s %s %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumn->getName()),
                $this->getColumnSqlDefinition($newColumn)
            )
        );
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropColumn', array($tableName, $columnName));
        $this->execute(
            sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName)
            )
        );
        $this->endCommandTimer();
    }
    
    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $indexes = array();
        $rows = $this->fetchAll(sprintf('SHOW INDEXES FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            if (!isset($indexes[$row['Key_name']])) {
                $indexes[$row['Key_name']] = array('columns' => array());
            }
            $indexes[$row['Key_name']]['columns'][] = strtolower($row['Column_name']);
        }
        return $indexes;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        
        $columns = array_map('strtolower', $columns);
        $indexes = $this->getIndexes($tableName);
        
        foreach ($indexes as $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        $this->startCommandTimer();
        $this->writeCommand('addIndex', array($table->getName(), $index->getColumns()));
        $this->execute(
            sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getIndexSqlDefinition($index)
            )
        );
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        $this->startCommandTimer();
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        
        $this->writeCommand('dropIndex', array($tableName, $columns));
        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);
        
        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $this->execute(
                    sprintf(
                        'ALTER TABLE %s DROP INDEX %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($indexName)
                    )
                );
                $this->endCommandTimer();
                return;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $this->startCommandTimer();
        
        $this->writeCommand('dropIndexByName', array($tableName, $indexName));
        $indexes = $this->getIndexes($tableName);
        
        foreach ($indexes as $name => $index) {
            //$a = array_diff($columns, $index['columns']);
            if ($name === $indexName) {
                $this->execute(
                    sprintf(
                        'ALTER TABLE %s DROP INDEX %s',
                        $this->quoteTableName($tableName),
                        $this->quoteColumnName($indexName)
                    )
                );
                $this->endCommandTimer();
                return;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        $foreignKeys = $this->getForeignKeys($tableName);
        if ($constraint) {
            if (isset($foreignKeys[$constraint])) {
                return !empty($foreignKeys[$constraint]);
            }
            return false;
        } else {
            foreach ($foreignKeys as $key) {
                $a = array_diff($columns, $key['columns']);
                if (empty($a)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $foreignKeys = array();
        $rows = $this->fetchAll(sprintf(
            'SELECT
              CONSTRAINT_NAME,
              TABLE_NAME,
              COLUMN_NAME,
              REFERENCED_TABLE_NAME,
              REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_NAME = "%s"
            ORDER BY POSITION_IN_UNIQUE_CONSTRAINT',
            $tableName
        ));
        foreach ($rows as $row) {
            $foreignKeys[$row['CONSTRAINT_NAME']]['table'] = $row['TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['columns'][] = $row['COLUMN_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['referenced_table'] = $row['REFERENCED_TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['referenced_columns'][] = $row['REFERENCED_COLUMN_NAME'];
        }
        return $foreignKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $this->startCommandTimer();
        $this->writeCommand('addForeignKey', array($table->getName(), $foreignKey->getColumns()));
        $this->execute(
            sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getForeignKeySqlDefinition($foreignKey)
            )
        );
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        $this->startCommandTimer();
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        
        $this->writeCommand('dropForeignKey', array($tableName, $columns));
        
        if ($constraint) {
            $this->execute(
                sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quoteTableName($tableName),
                    $constraint
                )
            );
            $this->endCommandTimer();
            return;
        } else {
            foreach ($columns as $column) {
                $rows = $this->fetchAll(sprintf(
                    'SELECT
                        CONSTRAINT_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                        AND TABLE_NAME = "%s"
                        AND COLUMN_NAME = "%s"
                      ORDER BY POSITION_IN_UNIQUE_CONSTRAINT',
                    $tableName,
                    $column
                ));
                foreach ($rows as $row) {
                    $this->dropForeignKey($tableName, $columns, $row['CONSTRAINT_NAME']);
                }
            }
        }
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSqlType($type)
    {
        switch ($type) {
            case static::PHINX_TYPE_STRING:
                return array('name' => 'varchar', 'limit' => 255);
                break;
            case static::PHINX_TYPE_CHAR:
                return array('name' => 'char', 'limit' => 255);
                break;
            case static::PHINX_TYPE_TEXT:
                return array('name' => 'text');
                break;
            case static::PHINX_TYPE_INTEGER:
                return array('name' => 'int', 'limit' => 11);
                break;
            case static::PHINX_TYPE_BIG_INTEGER:
                return array('name' => 'bigint', 'limit' => 20);
                break;
            case static::PHINX_TYPE_FLOAT:
                return array('name' => 'float');
                break;
            case static::PHINX_TYPE_DECIMAL:
                return array('name' => 'decimal');
                break;
            case static::PHINX_TYPE_DATETIME:
                return array('name' => 'datetime');
                break;
            case static::PHINX_TYPE_TIMESTAMP:
                return array('name' => 'timestamp');
                break;
            case static::PHINX_TYPE_TIME:
                return array('name' => 'time');
                break;
            case static::PHINX_TYPE_DATE:
                return array('name' => 'date');
                break;
            case static::PHINX_TYPE_BINARY:
                return array('name' => 'blob');
                break;
            case static::PHINX_TYPE_BOOLEAN:
                return array('name' => 'tinyint', 'limit' => 1);
                break;
            // Geospatial database types
            case static::PHINX_TYPE_GEOMETRY:
            case static::PHINX_TYPE_POINT:
            case static::PHINX_TYPE_LINESTRING:
            case static::PHINX_TYPE_POLYGON:
                return array('name' => $type);
                break;
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param $sqlTypeDef
     * @throws \RuntimeException
     * @internal param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlTypeDef)
    {
        if (preg_match('/^([\w]+)(\(([\d]+)*(,([\d]+))*\))*(.+)*$/', $sqlTypeDef, $matches) === false) {
            throw new \RuntimeException('Column type ' . $sqlTypeDef . ' is not supported');
        } else {
            $limit = null;
            $precision = null;
            $type = $matches[1];
            if (count($matches) > 2) {
                $limit = $matches[3] ? $matches[3] : null;
            }
            if (count($matches) > 4) {
                $precision = $matches[5];
            }
            switch ($matches[1]) {
                case 'varchar':
                    $type = static::PHINX_TYPE_STRING;
                    if ($limit == 255) {
                        $limit = null;
                    }
                    break;
                case 'char':
                    $type = static::PHINX_TYPE_CHAR;
                    if ($limit == 255) {
                        $limit = null;
                    }
                case 'int':
                    $type = static::PHINX_TYPE_INTEGER;
                    if ($limit == 11) {
                        $limit = null;
                    }
                    break;
                case 'bigint':
                    if ($limit == 20) {
                        $limit = null;
                    }
                    $type = static::PHINX_TYPE_BIG_INTEGER;
                    break;
                case 'blob':
                    $type = static::PHINX_TYPE_BINARY;
                    break;
            }
            if ($type == 'tinyint') {
                if ($matches[3] == 1) {
                    $type = static::PHINX_TYPE_BOOLEAN;
                    $limit = null;
                }
            }

            $this->getSqlType($type);

            return array(
                'name' => $type,
                'limit' => $limit,
                'precision' => $precision
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        $this->startCommandTimer();
        $this->writeCommand('createDatabase', array($name));
        $charset = isset($options['charset']) ? $options['charset'] : 'utf8';
        
        if (isset($options['collation'])) {
            $this->execute(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET `%s` COLLATE `%s`', $name, $charset, $options['collation']));
        } else {
            $this->execute(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET `%s`', $name, $charset));
        }
        $this->endCommandTimer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        $rows = $this->fetchAll(
            sprintf(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \'%s\'',
                $name
            )
        );
        
        foreach ($rows as $row) {
            if (!empty($row)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropDatabase', array($name));
        $this->execute(sprintf('DROP DATABASE IF EXISTS `%s`', $name));
        $this->endCommandTimer();
    }
    
    /**
     * Gets the MySQL Column Definition for a Column object.
     *
     * @param Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $sqlType = $this->getSqlType($column->getType());
        $def = '';
        $def .= strtoupper($sqlType['name']);
        if ($column->getPrecision() && $column->getScale()) {
            $def .= '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        }
        $def .= ($column->getLimit() || isset($sqlType['limit']))
                     ? '(' . ($column->getLimit() ? $column->getLimit() : $sqlType['limit']) . ')' : '';
        $def .= (!$column->isSigned() && isset($this->signedColumnTypes[$column->getType()])) ? ' unsigned' : '' ;
        $def .= ($column->isNull() == false) ? ' NOT NULL' : ' NULL';
        $def .= ($column->isIdentity()) ? ' AUTO_INCREMENT' : '';
        $default = $column->getDefault();
        if (is_numeric($default) || $default == 'CURRENT_TIMESTAMP') {
            $def .= ' DEFAULT ' . $column->getDefault();
        } else {
            $def .= is_null($column->getDefault()) ? '' : ' DEFAULT \'' . $column->getDefault() . '\'';
        }

        if ($column->getComment()) {
            $def .= ' COMMENT ' . $this->getConnection()->quote($column->getComment());
        }

        if ($column->getUpdate()) {
            $def .= ' ON UPDATE ' . $column->getUpdate();
        }

        return $def;
    }
    
    /**
     * Gets the MySQL Index Definition for an Index object.
     *
     * @param Index $index Index
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index)
    {
        $def = '';
        
        if ($index->getType() == Index::UNIQUE) {
            $def .= ' UNIQUE';
        }
        
        $def .= ' KEY';
        
        if (is_string($index->getName())) {
            $def .= ' `' . $index->getName() . '`';
        }
        
        $def .= ' (`' . implode('`,`', $index->getColumns()) . '`)';
        
        return $def;
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param ForeignKey $foreignKey
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey)
    {
        $def = '';
        if ($foreignKey->getConstraint()) {
            $def .= ' CONSTRAINT ' . $this->quoteColumnName($foreignKey->getConstraint());
        }
        $columnNames = array();
        foreach ($foreignKey->getColumns() as $column) {
            $columnNames[] = $this->quoteColumnName($column);
        }
        $def .= ' FOREIGN KEY (' . implode(',', $columnNames) . ')';
        $refColumnNames = array();
        foreach ($foreignKey->getReferencedColumns() as $column) {
            $refColumnNames[] = $this->quoteColumnName($column);
        }
        $def .= ' REFERENCES ' . $this->quoteTableName($foreignKey->getReferencedTable()->getName()) . ' (' . implode(',', $refColumnNames) . ')';
        if ($foreignKey->getOnDelete()) {
            $def .= ' ON DELETE ' . $foreignKey->getOnDelete();
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= ' ON UPDATE ' . $foreignKey->getOnUpdate();
        }
        return $def;
    }

    /**
     * Describes a database table. This is a MySQL adapter specific method.
     * @return array
     */
    public function describeTable($tableName)
    {
        $options = $this->getOptions();

        // mysql specific
        $sql = sprintf(
            'SELECT *'
            . ' FROM information_schema.tables'
            . ' WHERE table_schema = "%s"'
            . ' AND table_name = "%s"',
            $options['name'],
            $tableName
        );

        return $this->fetchRow($sql);
    }

}
