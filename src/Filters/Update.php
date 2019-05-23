<?php

namespace Innocode\WPMUUsers\Filters;

use Innocode\WPMUUsers\Db;
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

        if ( ! Db::is_local_users_table( $table->table ) ) {
            return;
        }

        $id = $this->_parse_identifier();

        if (
            false !== $id &&
            $id > 0 &&
            $id < MU_USERS_OFFSET
        ) {
            $table->table = Db::replace_tables( $table->table );
            $table->expr = Db::replace_tables( $table->expr );
        }
    }

    protected function _parse_identifier()
    {
        foreach ( $this->statement->where as $where ) {
            if ( isset( $where->identifiers[0] ) ) {
                $identifier = $where->identifiers[0];

                switch ( mb_strtolower( $identifier ) ) {
                    case 'id':
                    case 'user_id':
                        if ( preg_match(
                            '/^' . SqlParser\Context::escape( $identifier ) . '\s*=\s*(\d+)$/',
                            trim( $where->expr ),
                            $match
                        ) ) {
                            return intval( $match[1] );
                        }

                        break;
                    case 'user_login':
                    case 'user_email':
                    case 'user_nicename':
                        if ( ! isset( $where->identifiers[1] ) ) {
                            break;
                        }

                        $value = $where->identifiers[1];
                        $field = $identifier == 'user_nicename'
                            ? 'slug'
                            : preg_replace( '/^user_(.*)$/', '$1', $identifier );

                        if ( false !== ( $user = get_user_by( $field, $value ) ) ) {
                            return $user->ID;
                        }

                        break;
                }
            }
        }

        return false;
    }
}
