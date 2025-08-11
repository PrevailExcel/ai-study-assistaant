<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Nette\Utils\Random;

class UserService
{
    private function createRefCode()
    {
        $code = strtoupper(Random::generate(6));
        return $this->checkIfCodeExists($code);
    }

    private function checkIfCodeExists(string $code)
    {
        if (User::where('code', $code)->first())
            return $this->createRefCode();
        else
            return $code;
    }

    private function getReferrerId(string|null $refcode): int|null
    {
        $referrer = User::where('code', $refcode)->first();
        if ($referrer)
            return $referrer->id;
        else
            return null;
    }

    /**
     * The method creates a user
     *
     * @var array $data
     * @param array $data
     *
     * @return User $user
     */
    public function create(array $data): User
    {
        $user = new User();
        $user->token_id = $data['token'] ?? null;
        $user->code = $this->createRefCode();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->photo = $data['picture'] ?? null;
        $user->notification_id = $data['notificationId'] ?? null;
        if (isset($data['heard_about_us']))
            $user->heard_about_us = $data['heard_about_us'];

        if (isset($data['referralCode']))
            $user->referred_by = $this->getReferrerId($data['referralCode']);


        if ($data['password'])
            $user->password = Hash::make($data['password']);

        $user->save();

        return $user;
    }

    /**
     * The method edits a returning user
     *
     * @var array $data
     * @param array $data
     *
     * @return User $user
     */
    public function edit($data)
    {
        $user = User::where('email', $data['email'])->first();
        $user->token_id = $data['token'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->photo = $data['picture'];
        $user->notification_id = $data['notificationId'];
        $user->save();

        return $user;
    }
}
