<?php
declare(strict_types=1);

/** Pure-PHP SQL dump and restore (no mysqldump binary needed). */
final class Backup
{
    public static function dir(): string
    {
        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0750, true);
        }
        return BACKUP_PATH;
    }

    /** Writes a full dump and returns the file name. */
    public static function create(string $label = 'manual'): string
    {
        $pdo = Database::instance()->pdo();
        $name = 'backup-' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9]/', '', strtolower($label)) . '.sql';
        $fh = fopen(self::dir() . '/' . $name, 'w');
        fwrite($fh, "-- Saffron POS backup\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Version: " . APP_VERSION . "\n\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
            fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
            $stmt = $pdo->query("SELECT * FROM `$table`", PDO::FETCH_NUM);
            $batch = [];
            while ($row = $stmt->fetch()) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
                if (count($batch) >= 200) {
                    fwrite($fh, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fh);
        return $name;
    }

    /** Splits SQL into statements, respecting quoted strings and comments. */
    public static function statements(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\') {
                    $buf .= $sql[++$i] ?? '';
                } elseif ($c === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $buf .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($c === '\'' || $c === '"' || $c === '`') {
                $quote = $c;
                $buf .= $c;
            } elseif ($c === '-' && ($sql[$i + 1] ?? '') === '-' && (($sql[$i + 2] ?? '') === ' ' || ($sql[$i + 2] ?? '') === "\n" || ($sql[$i + 2] ?? '') === "\r")) {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
            } elseif ($c === '#') {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
            } elseif ($c === '/' && ($sql[$i + 1] ?? '') === '*' && ($sql[$i + 2] ?? '') !== '!') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
            } elseif ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
            } else {
                $buf .= $c;
            }
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    public static function restore(string $sql): int
    {
        $pdo = Database::instance()->pdo();
        $count = 0;
        foreach (self::statements($sql) as $stmt) {
            $pdo->exec($stmt);
            $count++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        return $count;
    }

    public static function files(): array
    {
        $files = [];
        foreach (glob(self::dir() . '/*.sql') ?: [] as $f) {
            $files[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
        return $files;
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }
}
