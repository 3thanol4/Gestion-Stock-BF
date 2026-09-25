<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class UserRegistrationData extends Data
{
    public function __construct(
        public string $name,

        #[Unique('users', 'username')]
        public string $username,

        #[Email]
        #[Unique('users', 'email')]
        public string $email,

        #[Min(8)]
        public string $password,

        public string $role,

        public ?string $entreprise_nom = null
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'role' => [
                'required',
                Rule::in(['administrateur_plateforme', 'administrateur_entreprise', 'gestionnaire_stock', 'vendeur']),
            ],
            'entreprise_nom' => [
                'nullable',
                'string',
                'max:255'
            ]
        ];
    }
}
