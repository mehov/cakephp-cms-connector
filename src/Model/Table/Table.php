<?php

namespace Bakeoff\CmsConnector\Model\Table;

use Cake\Core\Configure;

/**
 * Hijack CakePHP Table so that we can dynamically overwrite some settings
 *
 * @package Bakeoff\CmsConnector\Model\Table
 */

/*
 * CakePHP 4 introduced strict return type declarations, and when we re-declare
 * inherited methods on newer PHP versions, having them match is being enforced.
 *
 * At the same time, older PHP does not support this syntax and throws an error.
 *
 * So we have to put our code into legacy-compatible trait without return types,
 * and have another trait which will reuse the former, while adding return types
 * to methods that overwrite those inherited from \Cake\ORM\Table.
 */
if (version_compare(Configure::version(), '4.0.0', '>=')) {
    class Table extends \Cake\ORM\Table
    {
        /*
         * WithTypeDeclarationsTrait fully inherits WithoutTypeDeclarationsTrait
         *
         * Only difference is it adds type declarations to methods overwriting
         * \Cake\ORM\Table to avoid "Declaration must be compatible" error.
         */
        use WithTypeDeclarationsTrait;
    }
} else {
    class Table extends \Cake\ORM\Table
    {
        use WithoutTypeDeclarationsTrait;
    }
}
