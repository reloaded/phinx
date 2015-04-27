<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
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
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adapter Wrapper.
 *
 * Proxy commands through to another adapter, allowing modification of
 * parameters during calls.
 *
 * @author Woody Gilk <woody.gilk@gmail.com>
 */
abstract class AdapterWrapper implements AdapterInterface, WrapperInterface
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * {@inheritdoc}
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->adapter->setOptions($options);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->adapter->getOptions();
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name)
    {
        return $this->adapter->hasOption($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name)
    {
        return $this->adapter->getOption($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output)
    {
        $this->adapter->setOutput($output);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput()
    {
        return $this->adapter->getOutput($output);
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        return $this->getAdapter()->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        return $this->getAdapter()->disconnect();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($sql)
    {
        return $this->getAdapter()->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        return $this->getAdapter()->query($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchRow($sql)
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($sql)
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersions()
    {
        return $this->getAdapter()->getVersions();
    }

    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        $this->getAdapter()->migrated($migration, $direction, $startTime, $endTime);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSchemaTable()
    {
        return $this->getAdapter()->hasSchemaTable();
    }

    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {
        return $this->getAdapter()->createSchemaTable();
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return $this->getAdapter()->getColumnTypes();
    }

    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column)
    {
        return $this->getAdapter()->isValidColumnType($column);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTransactions()
    {
        return $this->getAdapter()->hasTransactions();
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        return $this->getAdapter()->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        return $this->getAdapter()->commitTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        return $this->getAdapter()->rollbackTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return $this->getAdapter()->quoteTableName($tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return $this->getAdapter()->quoteColumnName($columnName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName, $database = null)
    {
        return $this->getAdapter()->hasTable($tableName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table)
    {
        return $this->getAdapter()->createTable($table);
    }

    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName, $sourceDatabase = null, $targetDatabase = null)
    {
        return $this->getAdapter()->renameTable($tableName, $newTableName, $sourceDatabase, $targetDatabase);
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName, $database = null)
    {
        return $this->getAdapter()->dropTable($tableName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName, $database = null)
    {
        return $this->getAdapter()->getColumns($tableName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName, $database = null)
    {
        return $this->getAdapter()->hasColumn($tableName, $columnName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        return $this->getAdapter()->addColumn($table, $column);
    }

    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName, $database = null)
    {
        return $this->getAdapter()->renameColumn($tableName, $columnName, $newColumnName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn, $database = null)
    {
        return $this->getAdapter()->changeColumn($tableName, $columnName, $newColumn, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName, $database = null)
    {
        return $this->getAdapter()->dropColumn($tableName, $columnName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns, $database = null)
    {
        return $this->getAdapter()->hasIndex($tableName, $columns, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        return $this->getAdapter()->addIndex($table, $index);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns, $options = array())
    {
        return $this->getAdapter()->dropIndex($tableName, $columns, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName, $database = null)
    {
        return $this->getAdapter()->dropIndexByName($tableName, $indexName, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null, $database = null)
    {
        return $this->getAdapter()->hasForeignKey($tableName, $columns, $constraint, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        return $this->getAdapter()->addForeignKey($table, $foreignKey);
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null, $database = null)
    {
        return $this->getAdapter()->dropForeignKey($tableName, $columns, $constraint, $database);
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        return $this->getAdapter()->getSqlType($type, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        return $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        return $this->getAdapter()->hasDatabase($name);
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        return $this->getAdapter()->dropDatabase($name);
    }
}
