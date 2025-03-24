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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Notifications\ResetPasswordNotification;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    use ResponseTrait;

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            $defaultProfilePhoto = 'user.png';


            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'profile_photo' => $defaultProfilePhoto,
            ]);

            try {
                $data = [
                        'email' => $request->email,
                        'content' => ''
                        ];

                Mail::to($request->email)->send(new WelcomeEmail($data));

                } catch (\Throwable $th) {
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

            JWTAuth::factory()->setTTL(60);


            if (!$accessToken = JWTAuth::attempt($request->only('email', 'password'))) {
                return $this->sendError('Invalid credentials', [], 401);
            }
            $user = auth()->user();

            JWTAuth::factory()->setTTL(20160); // Refresh token valid for 30 days
            $refreshToken = JWTAuth::fromUser($user);

            // Store the access token in a secure cookie
            // $cookie = Cookie::make(
            //     'access_token',       // Cookie name
            //     $accessToken,         // Cookie value (access token)
            //     2880,                 // Expiration time in minutes (2 days)
            //     '/',                  // Path
            //     null,                 // Domain (null = current domain)
            //     true,                 // Secure (only sent over HTTPS)
            //     true,                 // HttpOnly (accessible only by HTTP requests)
            //     false,                // SameSite attribute
            //     'Strict'              // SameSite value (options: 'Lax', 'Strict', 'None')
            // );


            return $this->sendResponse([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => auth('api')->user(),
            ], 'Login successful.');
            // ->cookie($cookie)

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
            
            return $this->sendResponse([], 'Logged out successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to logout'. $e->getMessage(), []);
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

            return $this->sendError('Failed to send reset link. Please try again later.', []);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the forgot password process.' .$e->getMessage(), []);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:6|confirmed', 
            ]);


            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->password = Hash::make($password);
                    $user->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return $this->sendResponse([], 'Password has been reset successfully.');
            }

            return $this->sendError('Failed to reset password.', []);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), []);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the password reset process.' .$e->getMessage(), []);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if (!Hash::check($request->current_password, auth()->user()->password)) {
                return $this->sendError('Current password is incorrect.', []);
            }

            $user = auth()->user();
            $user->password = Hash::make($request->new_password);
            $user->save();

            return $this->sendResponse([], 'Password updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error occurred while updating the password.' .$e->getMessage(), []);
        }

    }

    public function refreshToken(Request $request)
    {
        try {
            // Get the refresh token from the Authorization header
            $authorizationHeader = $request->header('Authorization');

            if (!$authorizationHeader || !preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
                return $this->sendError('Refresh token is required in the Authorization header', [], 401);
            }

            $refreshToken = $matches[1];

            // Check if the refresh token is valid
            try {
                $newToken = JWTAuth::setToken($refreshToken)->refresh();
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                return $this->sendError('Refresh token has expired. Please log in again.', [], 401);
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                return $this->sendError('Invalid refresh token.', [], 401);
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return $this->sendError('Refresh token error: ' . $e->getMessage(), [], 401);
            }

            return $this->sendResponse([
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ], 'Token refreshed successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error refreshing token.', [], 500);
        }
    }


    // private function generateImageUrl($path)
    // {
    //     return $path ? env('APP_URL') . '/storage/app/public/' . $path : null;

    //     //return $path ? url('storage/' . $path) : null;

    // }

    public function getProfile(Request $request)
    {
        try {
            $user = User::find(Auth::id());

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $profilePhotoUrl = $user->profile_photo
            ? asset('storage/' . $user->profile_photo)
            : asset('user.png');

            return $this->sendResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => $profilePhotoUrl,
                'role' => $user->role,
            ], 'User profile retrieved successfully.');
        } catch (\Exception $e) {
            Log::error("Error fetching profile: " . $e->getMessage());
            return $this->sendError('Error fetching user profile.', [], 500);
        }
    }

    /**
     * Update user's name and email.
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = User::find(Auth::id());

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $request->validate([
                'name' => 'nullable|string|max:255', 
                'email' => 'nullable|email|unique:users,email,' . $user->id,  
            ]);

            $data = [];

            if ($request->has('name') && $request->name !== null) {
                $data['name'] = $request->name;
            }

            if ($request->has('email') && $request->email !== null) {
                $data['email'] = $request->email;
            }

            if (!empty($data)) {
                $user->update($data);
            }

            return $this->sendResponse([
                'user' => $user,
            ], 'Profile updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 400);
        } catch (\Exception $e) {
            Log::error("Error updating profile: " . $e->getMessage());
            return $this->sendError('Error updating profile.' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Upload a new profile photo.
     */
    public function uploadProfilePhoto(Request $request)
    {
        try {
            $user = User::find(Auth::id());

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $request->validate([
                'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $filePath = $request->file('profile_photo')->store('profile_photos', 'public');


            $user->update(['profile_photo' => $filePath]);

            return $this->sendResponse([
                'profile_photo' => asset('storage/' . $filePath),
            ], 'Profile photo updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 400);
        } catch (\Exception $e) {
            Log::error("Error uploading profile photo: " . $e->getMessage());
            return $this->sendError('Error uploading profile photo.' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the current profile photo.
     */
    public function removeProfilePhoto(Request $request)
    {
        try {
            $user = User::find(Auth::id());

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            if (!$user->profile_photo || !Storage::disk('public')->exists($user->profile_photo)) {
                return $this->sendError('No profile photo to delete.', [], 404);
            }

            Storage::disk('public')->delete($user->profile_photo);
            $user->update(['profile_photo' => null]);

            return $this->sendResponse([], 'Profile photo removed successfully.');
        } catch (\Exception $e) {
            Log::error("Error removing profile photo: " . $e->getMessage());
            return $this->sendError('Error removing profile photo.' . $e->getMessage(), [], 500);
        }
    }


    // public function updateProfile(Request $request)
    // {
    //     try {
    //         $user = User::find(Auth::id());

    //         $request->validate([
    //             'name' => 'nullable|string|max:255',
    //             'email' => 'nullable|email|unique:users,email,' . $user->id,
    //             'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:20480',
    //         ]);

    //         // Update name and email
    //         $user->name = $request->name;
    //         $user->email = $request->email;

    //         // Check if the profile photo is uploaded
    //         if ($request->hasFile('profile_photo')) {
    //             // Delete old profile photo if exists
    //             if ($user->profile_photo && Storage::exists($user->profile_photo)) {
    //                 Storage::delete($user->profile_photo);
    //             }

    //             // Store the new profile photo
    //             $filePath = $request->file('profile_photo')->store('profile_photos');
    //             $user->profile_photo = $filePath;
    //         }

    //         $user->save();

    //         return response()->json([
    //             'message' => 'Profile updated successfully.',
    //             'user' => $user,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => 'An error occurred while updating the profile.',
    //         ], 500);
    //     }
    // }
    

    
}
