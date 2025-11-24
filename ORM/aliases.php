<?php

/**
 * Backward Compatibility Aliases
 * 
 * Alphavel Database v2.0.0 unified alphavel/orm into alphavel/database.
 * These aliases ensure existing code using Alphavel\ORM namespace continues to work.
 * 
 * Performance Impact: ZERO
 * - class_alias() is resolved at compile time by opcache
 * - No runtime overhead
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */

// Core ORM classes
if (!class_exists('Alphavel\ORM\HasRelationships', false)) {
    class_alias(
        'Alphavel\Database\ORM\HasRelationships',
        'Alphavel\ORM\HasRelationships'
    );
}

if (!class_exists('Alphavel\ORM\Relation', false)) {
    class_alias(
        'Alphavel\Database\ORM\Relation',
        'Alphavel\ORM\Relation'
    );
}

// Relations
if (!class_exists('Alphavel\ORM\Relations\HasMany', false)) {
    class_alias(
        'Alphavel\Database\ORM\Relations\HasMany',
        'Alphavel\ORM\Relations\HasMany'
    );
}

if (!class_exists('Alphavel\ORM\Relations\HasOne', false)) {
    class_alias(
        'Alphavel\Database\ORM\Relations\HasOne',
        'Alphavel\ORM\Relations\HasOne'
    );
}

if (!class_exists('Alphavel\ORM\Relations\BelongsTo', false)) {
    class_alias(
        'Alphavel\Database\ORM\Relations\BelongsTo',
        'Alphavel\ORM\Relations\BelongsTo'
    );
}

if (!class_exists('Alphavel\ORM\Relations\BelongsToMany', false)) {
    class_alias(
        'Alphavel\Database\ORM\Relations\BelongsToMany',
        'Alphavel\ORM\Relations\BelongsToMany'
    );
}

