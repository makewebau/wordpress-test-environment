<?php

namespace MakeWeb\WordpressTestEnvironment\Database;

class QueryBuilder
{
    protected $phrases = [];

    protected $parameters = [];

    protected $pdo;

    protected $prepared;

    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function select($tableName)
    {
        $this->addPhrase("SELECT * FROM {$this->prefix($tableName)}");

        return $this;
    }

    public function update($tableName)
    {
        $this->addPhrase("UPDATE {$this->prefix($tableName)}");

        return $this;
    }

    public function set($columnName, $value)
    {
        $this->addPhrase("SET $columnName = ?");
        $this->addParameter($value);

        return $this;
    }

    public function where($columnName, $operator, $value)
    {
        $this->addPhrase("WHERE {$columnName} {$operator} ?");
        $this->addParameter($value);

        return $this;
    }

    public function first()
    {
        $this->addPhrase('LIMIT 1');

        $results = $this->execute();

        if (empty($results)) {
            return null;
        }

        return $results[0];
    }

    public function execute()
    {
        return $this->database->execute($this->build());
    }

    protected function build()
    {
        return new Query(implode($this->phrases, ' ').';', $this->parameters);
    }

    protected function addPhrase($phrase)
    {
        $this->phrases[] = $phrase;
    }

    protected function addParameter($parameter)
    {
        $this->parameters[] = $parameter;
    }

    protected function prefix($columnName)
    {
        return (getenv('DATABASE_PREFIX') ? getenv('DATABASE_PREFIX') : 'wp') .'_'.$columnName;
    }
}
