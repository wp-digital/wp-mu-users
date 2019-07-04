<?php

namespace Innocode\WPMUUsers\Filters;

use Innocode\WPMUUsers\Db;
use Innocode\WPMUUsers\Users;
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
            $dest->table != Db::get( 'usermeta' )
        ) {
            return;
        }

        $id = $this->_parse_columns();

        if (
            false !== $id &&
            Users::is_global_user_id( $id )
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
