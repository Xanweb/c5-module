<?php

namespace Xanweb\Module;

use Concrete\Core\Support\Facade\Database;

class Uninstaller
{
    /**
     * Drop Database Tables.
     *
     * @param \array $tables
     */
    public static function dropTables(array $tables): void
    {
        $db = Database::connection();
        $platform = $db->getDatabasePlatform();

        foreach ($tables as $table) {
            if ($db->tableExists($table)) {
                $db->executeQuery($platform->getDropTableSQL($table));
            }
        }
    }
}
