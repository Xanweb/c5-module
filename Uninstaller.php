<?php

namespace Xanweb\Module;

use Concrete\Core\Support\Facade\Database;

class Uninstaller
{
    /**
     * Drop Database Tables.
     *
     * @param ...$tables
     */
    public static function dropTables(...$tables): void
    {
        $db = Database::connection();
        $platform = $db->getDatabasePlatform();

        // Support both
        if (count($tables) === 1 && is_array($tables[0])) {
            $tables = $tables[0];
        }

        foreach ($tables as $table) {
            if ($db->tableExists($table)) {
                $db->executeQuery($platform->getDropTableSQL($table));
            }
        }
    }
}
