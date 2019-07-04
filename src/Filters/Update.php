<?php

namespace Innocode\WPMUUsers\Filters;

use Innocode\WPMUUsers\Db;
use Innocode\WPMUUsers\Users;
use PhpMyAdmin\SqlParser;

/**
 * Class Update
 *
 * @property SqlParser\Statements\UpdateStatement $statement
 *
 * @package Innocode\WPMUUsers\filters
 */
class Update extends AbstractFilter
{
    public function run()
    {
        $tables = $this->statement->tables;

        if ( ! isset( $tables[0] ) ) {
            return;
        }

        $table = $tables[0];

        if ( ! isset( $table->table ) ) {
            return;
        }

        if ( ! Db::is_local_table( $table->table ) ) {
            return;
        }

        $id = Db::parse_identifier( $this->statement );

        if (
            false !== $id &&
            Users::is_global_user_id( $id )
        ) {
            $table->table = Db::replace_tables( $table->table );
            $table->expr = Db::replace_tables( $table->expr );
        }
    }
}
