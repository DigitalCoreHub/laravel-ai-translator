<?php

namespace DigitalCoreHub\LaravelAiTranslator\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AiTranslatorUser extends Authenticatable
{
    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Create a new AI Translator user instance.
     */
    public static function make(string $email): self
    {
        $user = new self();

        $user->forceFill([
            'id' => sha1(strtolower($email)),
            'name' => $email,
            'email' => $email,
        ]);

        return $user;
    }
}
