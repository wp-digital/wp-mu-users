<?php

namespace Innocode\WPMUUsers\Filters;

use Innocode\WPMUUsers\Helpers;
use PhpMyAdmin\SqlParser;

/**
 * Class AbstractFilter
 *
 * @property SqlParser\Statement $statement
 *
 * @package Innocode\WPMUUsers
 */
abstract class AbstractFilter
{
    protected $_statement;

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function __get( $name )
    {
        if ( $name == 'statement' ) {
            return $this->_statement;
        }

        return null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set( $name, $value )
    {
        if ( $name == 'statement' ) {
            $filter_name = Helpers::get_class_short_name( $this );
            $statement_name = Helpers::get_class_short_name( $value );

            if ( "{$filter_name}Statement" == $statement_name ) {
                $this->_statement = $value;
            }
        }
    }

    /**
     * @return bool
     */
    abstract public function run();
}
