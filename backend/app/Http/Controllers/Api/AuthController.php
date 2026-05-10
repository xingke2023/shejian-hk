<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:64', 'unique:users', 'regex:/^\w+$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        \App\Models\UserStoreRole::create([
            'user_id' => $user->id,
            'store_id' => $request->integer('store_id'),
            'role_id' => 4, // 门店店员（默认）
        ]);

        $storeIdInt = $request->integer('store_id');
        $token = $user->createToken('auth_token', ['store:'.$storeIdInt], now()->addDays(30))->plainTextToken;
        $jwtToken = app(JwtService::class)->issueForUser($user, $storeIdInt);

        return response()->json([
            'message' => '注册成功',
            'token' => $token,
            'jwt_token' => $jwtToken,
            'store_id' => $storeIdInt,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
        ], 201);
    }

    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
            'store_id' => ['nullable', 'integer'],
        ]);

        $login = $request->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $login)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['用户名/邮箱或密码错误'],
            ]);
        }

        // 解析用户可访问的门店列表（有效期内）
        $accessibleStoreIds = $user->storeRoles()
            ->where(fn ($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', now()))
            ->pluck('store_id')
            ->unique()
            ->values();

        // 管理员若未绑定任何门店，视为可访问所有门店（需指定 store_id）
        if ($user->is_admin && $accessibleStoreIds->isEmpty()) {
            $storeId = $request->integer('store_id') ?: null;
            if (! $storeId) {
                return response()->json([
                    'message' => '管理员账号请指定 store_id',
                ], 422);
            }
        } elseif ($accessibleStoreIds->isEmpty()) {
            return response()->json(['message' => '该账号未关联任何门店，请联系管理员'], 403);
        } elseif ($accessibleStoreIds->count() === 1) {
            // 只属于一个门店，自动选中
            $storeId = $accessibleStoreIds->first();
        } else {
            // 属于多个门店，必须指定
            $storeId = $request->integer('store_id') ?: null;

            if (! $storeId) {
                return response()->json([
                    'message' => '该账号属于多个门店，请指定 store_id',
                    'stores' => $accessibleStoreIds,
                ], 422);
            }

            if (! $accessibleStoreIds->contains($storeId)) {
                return response()->json(['message' => '无权访问该门店'], 403);
            }
        }

        // 将 store_id 编码进 token ability，后续请求从 token 中读取
        $token = $user->createToken('auth_token', ['store:'.$storeId], now()->addDays(30))->plainTextToken;
        $jwtToken = app(JwtService::class)->issueForUser($user, $storeId);

        return response()->json([
            'message' => '登录成功',
            'token' => $token,
            'jwt_token' => $jwtToken,
            'store_id' => $storeId,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $storeId = $user->resolveStoreId();

        // 当前门店下的角色
        $roles = $storeId
            ? $user->storeRoles()
                ->where('store_id', $storeId)
                ->where(fn ($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', now()))
                ->with('role:id,code,name')
                ->get()
                ->pluck('role.code')
                ->filter()
                ->values()
            : collect();

        $store = $storeId ? Store::find($storeId, ['id', 'name', 'address']) : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'store_id' => $storeId,
                'store_name' => $store?->name,
                'store_address' => $store?->address,
                'roles' => $roles,
            ],
        ]);
    }
}
