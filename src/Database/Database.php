<?php

namespace MakeWeb\WordpressTestEnvironment\Database;

use MakeWeb\WordpressTestEnvironment\Wordpress;
use PDO;

class Database
{
    public function __construct(Wordpress $wordpress)
    {
        $this->wordpress = $wordpress;
    }

    public function wordpressTablesExist()
    {
        return (new QueryBuilder($this))->fromRaw('SHOW TABLES;');
    }

    public function select($columnName)
    {
        return (new QueryBuilder($this))->select($columnName);
    }

    public function update($columnName)
    {
        return (new QueryBuilder($this))->update($columnName);
    }

    public function execute(Query $query)
    {
        $preparedStatement = $this->wordpress->pdo->prepare($query->string);

        $success = $preparedStatement->execute($query->parameters);

        if (!$success) {
            $errorMessageComponents = $preparedStatement->errorInfo();
            $errorMessageComponents[] = $query->string;
            array_push($errorMessageComponents, implode("\n", $query->parameters));

            throw new \Exception(implode("\n", $errorMessageComponents));
        }

        return $preparedStatement->fetchAll();
    }

    public function connect()
    {
        return new PDO($this->buildDsn(), getenv('DATABASE_USER'), getenv('DATABASE_PASSWORD'));
    }

    protected function buildDsn()
    {
        return 'mysql:host=localhost;dbname='.getenv('DATABASE_NAME');
    }
}
