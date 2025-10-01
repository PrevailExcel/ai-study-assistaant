<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SendPushNotifications;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\JWT\Error\IdTokenVerificationFailed;
use Kreait\Firebase\JWT\IdTokenVerifier;

class AuthController extends Controller
{
    public function register(Request $request, UserService $userService, SendPushNotifications $sendPush)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'heard_about_us' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Create a new user
        $user = $userService->create($request->all());

        //Log the user in
        Auth::login($user);

        $token = $user->createToken('access-token')->plainTextToken;

        // add user to free plan automatically
        $userService->assignFreePlan($user);
        $userinfo = [
            'token' => $token,
            'user' => $user->only('name', 'email', 'id', 'code'),
            'plan' =>  $user->subscriptionPlan() ?? 'free',
        ];

        // Send Email and Push notification
        $sendPush->afterRegistering($user);

        return $this->success($userinfo, 'User registered successfully', 201);
    }


    public function registerWithThirdParty(Request $request, UserService $cus, SendPushNotifications $sendPush)
    {
        $encodedResponse = json_encode($request->json()->all());
        $decodedResponse = json_decode($encodedResponse, true);
        $projectId = env('FIREBASE_PROJECT_ID');
        $verifier = IdTokenVerifier::createWithProjectId($projectId);
        $idToken = $decodedResponse['token'];

        try {
            $token = $verifier->verifyIdToken($idToken);
            $encodedGoogleData = json_encode($token->payload(), true);
            $dataFromGoogle = json_decode($encodedGoogleData, true);
            $checkUser = User::where('email', $dataFromGoogle['email'])->first();

            if ($checkUser == null) {
                //Create User with Service
                $data = array_merge($dataFromGoogle, $decodedResponse);
                $user = $cus->create($data);

                // Send Email and Push notification
                $sendPush->afterRegistering($user);
            } else {
                // Edit User via service
                $data = array_merge($dataFromGoogle, $decodedResponse);
                $user = $cus->edit($data);

                // Send Email and Push notification
                $sendPush->returningUser($user);
            }

            //Log the user in
            Auth::login($user);

            $token = $user->createToken('access-token')->plainTextToken;
            $userinfo = [
                'token' => $token,
                'user' => $user->only('name', 'email', 'id', 'code'),
                'plan' =>  $user->subscriptionPlan() ?? 'free',
            ];

            return $this->success($userinfo, 'User registered successfully', 201);
        } catch (IdTokenVerificationFailed $e) {
            return $this->error($e->getMessage());
        }
    }

    public function login(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Attempt to log the user in
        if (Auth::attempt($request->only('email', 'password'))) {

            $user = User::where('email', $request->email)->first();
            $token = $user->createToken('access-token')->plainTextToken;
            $userinfo = [
                'token' => $token,
                'user' => $user->only('name', 'email', 'id', 'code'),
                'plan' =>  $user->subscriptionPlan() ?? 'free',
            ];

            return $this->success($userinfo, 'User logged in successfully');
        }

        return $this->error('Invalid credentials', 401);
    }
}
