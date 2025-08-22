<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    protected $cooldownMinutes = 2; // time before user can request again
    protected $codeExpiryMinutes = 10;

    // Step 1: Request reset code
    public function sendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $ip = $request->ip();
        $email = $request->email;

        // Throttle by email
        if (RateLimiter::tooManyAttempts("password-reset-email:$email", 3)) {
            return $this->error('Too many attempts. Please try again later.', 429);
        }

        // Throttle by IP
        if (RateLimiter::tooManyAttempts("password-reset-ip:$ip", 5)) {
            return $this->error('Too many attempts. Please try again later.', 429);
        }

        // Cooldown check
        $existing = DB::table('password_resets')
            ->where('email', $email)
            ->where('created_at', '>', Carbon::now()->subMinutes($this->cooldownMinutes))
            ->first();

        if ($existing) {
            return response()->json(['message' => "You can request another code in {$this->cooldownMinutes} minutes."], 429);
        }

        // Generate and hash code
        $code = rand(100000, 999999);
        $hashedCode = Hash::make($code);

        // Remove any old reset requests for this email
        DB::table('password_resets')->where('email', $email)->delete();

        DB::table('password_resets')->insert([
            'email' => $email,
            'code' => $hashedCode,
            'expires_at' => Carbon::now()->addMinutes($this->codeExpiryMinutes),
            'ip_address' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send the email
        Mail::to($email)->send(new PasswordResetCodeMail($code));

        // Increment rate limits
        RateLimiter::hit("password-reset-email:$email", 3600);
        RateLimiter::hit("password-reset-ip:$ip", 3600);

        return $this->success(['code' => $code], 'Reset code sent to your email.');
    }

    // Step 2: Verify and reset password
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (
            !$reset ||
            Carbon::parse($reset->expires_at)->isPast() ||
            !Hash::check($request->code, $reset->code)
        ) {
            return $this->error('Invalid or expired code.', 422);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_resets')->where('email', $request->email)->delete();

        return $this->success([], 'Password has been reset successfully.');
    }
}
