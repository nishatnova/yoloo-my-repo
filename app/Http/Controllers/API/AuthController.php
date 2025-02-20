<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Notifications\ResetPasswordNotification;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    use ResponseTrait;

       // Register method
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);


            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
            ]);


            try {
                $data = [
                        'email' => $request->email,
                        'content' => ''
                        ];

                Mail::to($request->email)->send(new WelcomeEmail($data));

                } catch (\Throwable $th) {
                        // Log the error or handle it appropriately
                    Log::error("Error sending welcome email: " . $th->getMessage());
                }
           
            $token = JWTAuth::fromUser($user);

            return $this->sendResponse([
                'token' => $token,
                'user' => $user
            ], 'User account created successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = $e->validator->errors()->first();
            return $this->sendError($errorMessage, [], 400);
        }catch (\Exception $e) {
            return $this->sendError('Error during registration', [], 500);
        }
    }

    // Login method
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            JWTAuth::factory()->setTTL(1); // 2 days


            // If token is invalid or expired, attempt will return false
            if (!$accessToken = JWTAuth::attempt($request->only('email', 'password'))) {
                return $this->sendError('Invalid credentials or token expired', [], 401);
            }


            // Manually set the authenticated user
            $user = auth()->user();

            // Generate the refresh token
            JWTAuth::factory()->setTTL(2); // 2 weeks
            $refreshToken = JWTAuth::fromUser($user);

            // Store the access token in a secure cookie
            $cookie = Cookie::make(
                'access_token',       // Cookie name
                $accessToken,         // Cookie value (access token)
                2880,                 // Expiration time in minutes (2 days)
                '/',                  // Path
                null,                 // Domain (null = current domain)
                true,                 // Secure (only sent over HTTPS)
                true,                 // HttpOnly (accessible only by HTTP requests)
                false,                // SameSite attribute
                'Strict'              // SameSite value (options: 'Lax', 'Strict', 'None')
            );


            return $this->sendResponse([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => auth('api')->user(),
            ], 'Login successful.')->cookie($cookie);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), []);
        } catch (\Exception $e) {
            return $this->sendError('Error during login' .$e->getMessage(), []);
        }
    }

    // Logout method
    public function logout()
    {
        try {
            auth()->logout();
            // Remove the access token cookie
            $cookie = Cookie::forget('access_token');
            return $this->sendResponse([], 'Logged out successfully.')->cookie($cookie);;
        } catch (\Exception $e) {
            return $this->sendError('Failed to logout', []);
        }
    }


    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Use the custom ResetPasswordNotification
            $status = Password::broker()->sendResetLink(
                $request->only('email'),
                function ($user, $token) {
                    $user->notify(new ResetPasswordNotification($token));
                }
            );

            if ($status === Password::RESET_LINK_SENT) {
                return $this->sendResponse([], 'Password reset link sent to your email.');
            }

            return $this->sendError('Failed to send reset link.', []);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the forgot password process.', []);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            // Validate the input
            $request->validate([
                'token' => 'required',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:6|confirmed', // Must include `password_confirmation`
            ]);

            // Reset the password
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->password = Hash::make($password);
                    $user->save();
                }
            );

            // Handle success or failure
            if ($status === Password::PASSWORD_RESET) {
                return $this->sendResponse([], 'Password has been reset successfully.');
            }

            return $this->sendError('Failed to reset password.', []);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), []);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the password reset process.', []);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            // Check if the current password matches
            if (!Hash::check($request->current_password, auth()->user()->password)) {
                return $this->sendError('Current password is incorrect.', []);
            }


            // Update the password
            $user = auth()->user();
            $user->password = Hash::make($request->new_password);
            $user->save();

            return $this->sendResponse([], 'Password updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error occurred while updating the password.', []);
        }

    }

    public function refreshToken(Request $request)
    {
        try {
            // Get the refresh token from the Authorization header
            $authorizationHeader = $request->header('Authorization');

            if (!$authorizationHeader || !preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
                return $this->sendError('Refresh token is required in the Authorization header', []);
            }

            $refreshToken = $matches[1];

            // Use the refresh token to generate a new access token
            $newToken = JWTAuth::setToken($refreshToken)->refresh();

            return $this->sendResponse([
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ], 'Token refreshed successfully.');
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->sendError('Refresh token has expired', []);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return $this->sendError('Invalid refresh token', []);
        } catch (\Exception $e) {
            return $this->sendError('Error refreshing token', []);
        }
    }

    
}
