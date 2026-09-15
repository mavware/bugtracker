<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords typed twice, as on
     * the reset and change-password forms.
     *
     * @return array<int, Password|ValidationRule|string>
     */
    protected function passwordRules(): array
    {
        return [...$this->unconfirmedPasswordRules(), 'confirmed'];
    }

    /**
     * The rules for a password typed once. Registration asks for it a single
     * time, with a reveal toggle on the box instead of a second box to match.
     *
     * @return array<int, Password|ValidationRule|string>
     */
    protected function unconfirmedPasswordRules(): array
    {
        return ['required', 'string', Password::default()];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
