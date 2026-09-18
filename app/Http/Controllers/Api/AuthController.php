<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\AuthResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{


    public function index()
    {

        $users = User::with('orders.shift', 'accessLevel')->get();

        return response()->json($users);
        //return UserResource::collection($users);
    }

    public function register(Request $request)
    {
        // Validação dos dados de entrada
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'active' => 'required|boolean',
            'access_level_id' => 'required|exists:access_levels,id',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Criação do usuário
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'active' => $request->active,
                'access_level_id' => $request->access_level_id,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return new AuthResource([
                'access_token' => $token,
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            // Retornar uma mensagem de erro se o email já existir
            return response()->json([
                'message' => 'Este e-mail já está em uso.',
                'error' => $e->getMessage(),
            ], 409); // 409 Conflict
        }
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Credenciais incorretas'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {

        $user = User::where('email', $request->user()->email)->first();
        return response()->json([
            'user' => $user,
        ]);

        //return new UserResource($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email', // valida se existe o email
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email'])
            : response()->json(['message' => 'Unable to send reset link'], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully'])
            : response()->json(['message' => 'Unable to reset password'], 400);
    }







    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                'active' => 'sometimes|required|boolean',
                'access_level_id' => 'sometimes|required|exists:access_levels,id',
                'profile_photo' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,webp|max:6048',
            ]);
        } catch (ValidationException $e) {
            // Checa se o erro é de tamanho da imagem
            $errors = $e->validator->errors();
            if ($errors->has('profile_photo') && str_contains($errors->first('profile_photo'), 'may not be greater than')) {
                return response()->json([
                    'message' => 'A imagem não pode ter mais que 2MB.',
                    'errors' => $errors,
                ], 422);
            }

            // Retorna os outros erros normalmente
            throw $e;
        }

        // Atualiza dados exceto a imagem
        $user->update(Arr::except($validated, ['profile_photo']));

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $photoPath = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo = $photoPath;
            $user->save();
        }

        return new UserResource($user);
    }


}