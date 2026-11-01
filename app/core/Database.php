<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper. Table and column names passed to the helper methods must come
 * from code, never from user input; values are always bound as parameters.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $c = $GLOBALS['__config']['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int)$c['port'], $c['name'], $c['charset'] ?? 'utf8mb4');
        $this->pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }

    public static function instance(): Database
    {
        return self::$instance ??= new Database();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Keep MySQL NOW()/CURDATE() aligned with the PHP timezone. */
    public function syncTimezone(): void
    {
        $this->pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', array_fill(0, count($cols), '?'))
        );
        $this->query($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        return $this->query("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$params])->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    /** Runs $fn atomically. Nested calls join the outer transaction. */
    public function transaction(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
