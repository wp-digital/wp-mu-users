<?php

namespace Innocode\WPMUUsers\Filters;

use Innocode\WPMUUsers\Db;
use PhpMyAdmin\SqlParser;

/**
 * Class Select
 *
 * @property SqlParser\Statements\SelectStatement $statement
 *
 * @package Innocode\WPMUUsers\filters
 */
class Select extends AbstractFilter
{
    public function run()
    {
        foreach ( $this->statement->from as $key => $from ) {
            if ( ! Db::is_local_users_table( $from->table ) ) {
                continue;
            }

            $this->_order_clauses_to_expr();
            $query = $this->statement->build();
            $query = Db::replace_tables( $query );
            $parsed = new SqlParser\Parser( $query );

            if ( isset( $parsed->statements[0] ) ) {
                /**
                 * @var SqlParser\Statements\SelectStatement $statement
                 */
                $statement = $parsed->statements[0];
                $this->_union( $statement );
            }

            $this->_count();
        }
    }

    protected function _order_clauses_to_expr()
    {
        if ( isset( $this->statement->order ) ) {
            $expr_columns = array_column( $this->statement->expr, 'column' );

            foreach ( $this->statement->order as $order ) {
                if ( false === array_search(
                    $order->expr->column,
                    $expr_columns
                ) ) {
                    $this->statement->expr[] = $order->expr;
                }
            }
        }
    }

    protected function _union( SqlParser\Statements\SelectStatement $statement )
    {
        $statement->order = [];
        $statement->limit = null;
        $statement->options = null;
        $this->statement->union[] = [ 'UNION ALL', $statement ];
    }

    protected function _count()
    {
        if (
            ! isset( $this->statement->expr[0] ) ||
            $this->statement->expr[0]->function != 'COUNT'
        ) {
            return;
        }

        $wrapper = new SqlParser\Statements\SelectStatement();

        foreach ( $this->statement->expr as $key => $expr ) {
            if ( $expr->function != 'COUNT' ) {
                continue;
            }

            $expr->alias = preg_replace(
                '/COUNT\(NULLIF\(`meta_value`\sLIKE\s\'%"(.+)"%\',\sfalse\)\)/i',
                '$1',
                stripslashes( $expr->expr )
            );

            switch ( $expr->alias ) {
                case 'COUNT(NULLIF(`meta_value` = \'a:0:{}\', false))':
                    $expr->alias = 'none';
                    break;
                case 'COUNT(*)':
                    $expr->alias = 'total';
                    break;
            }

            $sum_expr = new SqlParser\Components\Expression();
            $sum_expr->expr = 'SUM(' . SqlParser\Context::escape( $expr->alias ) . ')';
            $sum_expr->function = 'SUM';
            $wrapper->expr[] = $sum_expr;

            if ( isset( $this->statement->union[0][1]->expr[ $key ] ) ) {
                $this->statement->union[0][1]->expr[ $key ] = $expr;
            }
        }

        $from = new SqlParser\Components\Expression();
        $from->expr = "({$this->statement->build()})";
        $from->alias = 'count';
        $from->function = 'COUNT';
        $from->subquery = 'SELECT';
        $wrapper->from[] = $from;
        $this->statement = $wrapper;
    }
}
