<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Doctor;
use Validator;

class DoctorAuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:doctor', ['except' => ['login', 'register']]);
    }
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (!$token = auth()->guard('doctor')->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        auth()->guard('doctor')->user()->update(['active_status' => 1]);
        return $this->createNewToken($token);
    }
    /**
     * Register a doctor.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:doctors',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = Doctor::create(array_merge(
            $validator->validated(),
            [
                'password' => bcrypt($request->password),
                'avatar' => $request->file('avatar')->store('doctors')
            ]
        ));
        return response()->json([
            'message' => 'user successfully registered',
            'doctor' => $user
        ], 201);
    }

    /**
     * Log the doctor out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $user = auth()->guard('doctor')->user();
        if ($user) {
            $user->update(['active_status' => 0]);
        }
        auth()->guard('doctor')->logout();
        return response()->json(['message' => 'doctor successfully signed out']);
    }
    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->createNewToken(auth()->guard('doctor')->refresh());
    }
    /**
     * Get the authenticated doctor.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function doctorProfile()
    {
        return response()->json(auth()->guard('doctor')->user());
    }
    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'doctor' => auth()->guard('doctor')->user()
        ]);
    }
}