<?php

namespace Bakeoff\CmsConnector\Model\Table;

/**
 * Will be used on newer CakePHP versions that have type declarations.
 *
 * Fully inherits WithoutTypeDeclarationsTrait, adding type declarations to
 * methods overwriting those declared in \Cake\ORM\Table to avoid "Declaration
 * must be compatible" error.
 *
 * @package Bakeoff\CmsConnector\Model\Table
 */
trait WithTypeDeclarationsTrait
{

    // Rename methods from WithoutTypeDeclarationsTrait to reuse them here
    use WithoutTypeDeclarationsTrait {
        getTable as private getTableWithoutTypeDeclaration;
        getConnection as private getConnectionWithoutTypeDeclaration;
    }

    // These methods do have return type declarations in newer \Cake\ORM\Table

    public function getTable(): string
    {
        return $this->getTableWithoutTypeDeclaration();
    }

    public function getConnection(): \Cake\Database\Connection
    {
        return $this->getConnectionWithoutTypeDeclaration();
    }

}
