<?php

namespace Innocode\WPMUUsers\Filters;

use Innocode\WPMUUsers\Db;
use PhpMyAdmin\SqlParser;

/**
 * Class Insert
 *
 * @property SqlParser\Statements\InsertStatement $statement
 *
 * @package Innocode\WPMUUsers\filters
 */
class Insert extends AbstractFilter
{
    public function run()
    {
        if ( ! isset( $this->statement->into->dest ) ) {
            return;
        }

        $dest = $this->statement->into->dest;

        if (
            ! isset( $dest->table ) ||
            ! $dest->table == Db::get( 'usermeta' )
        ) {
            return;
        }

        $id = $this->_parse_columns();

        if (
            false !== $id &&
            $id > 0 &&
            $id < MU_USERS_OFFSET
        ) {
            $dest->table = Db::replace_tables( $dest->table );
            $dest->expr = Db::replace_tables( $dest->expr );
        }
    }

    private function _parse_columns()
    {
        foreach ( $this->statement->into->columns as $key => $column ) {
            if (
                $column == 'user_id' &&
                isset( $this->statement->values[0]->values[ $key ] )
            ) {
                return $this->statement->values[0]->values[ $key ];
            }
        }

        return false;
    }
}
