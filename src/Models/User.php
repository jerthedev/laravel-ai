<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Simple User model for testing purposes.
 *
 * In a real application, this would be replaced by the application's User model.
 */
class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \JTD\LaravelAI\Database\Factories\UserFactory::new();
    }
}
