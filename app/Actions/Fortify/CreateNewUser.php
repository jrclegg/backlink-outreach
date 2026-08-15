<?php

namespace App\Actions\Fortify;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            // Create user
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            // Create a default organization
            $org = Organization::create([
                'name' => $input['name'] . "'s Workspace",
                'slug' => Str::slug($input['name'] . '-' . Str::random(6)),
            ]);

            // Create membership
            OrganizationMembership::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            // Set current organization
            $user->update(['current_organization_id' => $org->id]);

            return $user;
        });
    }
}
