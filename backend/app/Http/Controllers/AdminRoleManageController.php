<?php

namespace App\Http\Controllers;
use App\Helpers\FlashMsg;
use App\Http\Controllers\Controller;

use App\Admin;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRoleManageController extends Controller
{
    const BASE_PATH ='backend.admin-role-manage.';
    public function __construct()
    {

        $this->middleware(['auth:admin','role:Super Admin']);

    }

    public function apiDirectory(): JsonResponse
    {
        $admins = Admin::with('roles.permissions')->orderByDesc('id')->get()->map(function (Admin $admin) {
            return $this->formatAdminPayload($admin);
        })->values();

        $roles = Role::with('permissions')
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return $this->formatRolePayload($role);
            })->values();

        return response()->json([
            'status' => 'success',
            'admins' => $admins,
            'roles' => $roles,
        ]);
    }

    private function formatAdminPayload(Admin $admin): array
    {
        $primaryRole = optional($admin->roles->first())->name ?? ($admin->role ?? 'Admin');
        $permissionNames = $admin->roles->flatMap(function ($role) {
            return $role->permissions->pluck('name');
        })->unique()->values()->all();

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $primaryRole,
            'role_key' => $this->normalizeRoleKey($primaryRole),
            'status' => 'active',
            'description' => $admin->description,
            'designation' => $admin->designation,
            'last_login' => optional($admin->updated_at)->toDateTimeString(),
            'permissions' => $permissionNames,
        ];
    }

    private function formatRolePayload(Role $role): array
    {
        $permissionNames = $role->permissions->pluck('name')->values()->all();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'key' => $this->normalizeRoleKey($role->name),
            'guard_name' => $role->guard_name,
            'permissions' => $permissionNames,
            'ui_permissions' => $this->buildUiPermissions($permissionNames),
        ];
    }

    private function buildUiPermissions(array $permissionNames): array
    {
        $names = array_map('strtolower', $permissionNames);

        return [
            'manage_users' => $this->permissionGroupEnabled($names, ['user-', 'admin', 'role']),
            'manage_services' => $this->permissionGroupEnabled($names, ['service-']),
            'manage_orders' => $this->permissionGroupEnabled($names, ['order-']),
            'manage_payments' => $this->permissionGroupEnabled($names, ['payment-', 'payout', 'refund', 'wallet']),
            'manage_support' => $this->permissionGroupEnabled($names, ['support-', 'ticket', 'report']),
            'manage_settings' => $this->permissionGroupEnabled($names, ['general', 'setting', 'appearance', 'menu', 'widget']),
            'manage_cms' => $this->permissionGroupEnabled($names, ['blog', 'page', 'content', 'banner', 'form']),
            'view_analytics' => $this->permissionGroupEnabled($names, ['dashboard', 'analytics', 'chart', 'report']),
        ];
    }

    private function permissionGroupEnabled(array $permissionNames, array $needles): bool
    {
        foreach ($permissionNames as $permissionName) {
            foreach ($needles as $needle) {
                if (strpos($permissionName, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeRoleKey(string $roleName): string
    {
        $key = strtolower(trim($roleName));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        return trim($key, '_');
    }

    public function new_user(){
        $roles = Role::pluck('name','name')->all();
        return view(self::BASE_PATH.'add-new-user',compact('roles'));
    }
    public function new_user_add(Request $request){
        $this->validate($request,[
            'name' => 'required|string|max:191',
            'username' => 'required|string|max:191|unique:admins',
            'email' => 'required|email|max:191',
            'role' => 'required|string|max:191',
            'image' => 'nullable|string',
            'password' => 'required|min:8|confirmed',
            'description' => 'nullable|string',
            'designation' => 'nullable|string',
        ]);
        $admin = Admin::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'image' => $request->image,
            'password' => Hash::make($request->password),
            'description' => $request->description,
            'designation' => $request->designation,
        ]);
        $admin->assignRole($request->role);
        return redirect()->back()->with(['msg' => __('New Admin Added'),'type' =>'success' ]);
    }

    public function all_user(){
        $all_user = Admin::all()->except(Auth::id());
        return view(self::BASE_PATH.'all-user')->with(['all_user' => $all_user]);
    }
    public function user_edit($id){
        $admin = Admin::findOrFail($id);
        $roles = Role::pluck('name','name')->all();
        $adminRole = $admin->roles->pluck('name','name')->all();
        return view(self::BASE_PATH.'edit-user',compact('roles','adminRole','admin'));
    }
    public function user_update(Request $request){
        $this->validate($request,[
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'role' => 'required|string|max:191',
            'image' => 'nullable|string',
             'description' =>'nullable',
            'designation' => 'nullable',
        ]);
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'image' => $request->image,
             'description' => $request->description,
            'designation' => $request->designation,
        ];

        $admin = Admin::findOrFail($request->user_id);
        $admin->update($data);
        DB::table('model_has_roles')->where('model_id',$admin->id)->delete();
        $admin->assignRole($request->role);

        return redirect()->back()->with(['msg' => __('Admin Details Updated'),'type' =>'success' ]);
    }
    public function new_user_delete(Request $request,$id){
        Admin::findOrFail($id)->delete();
        return redirect()->back()->with(['msg' => __('Admin Deleted'),'type' =>'danger' ]);
    }
    public function user_password_change(Request $request){
        $this->validate($request, [
            'password' => 'required|string|min:8|confirmed'
        ]);
        $user = Admin::findOrFail($request->ch_user_id);
        $user->password = Hash::make($request->password);
        $user->save();
        return redirect()->back()->with(['msg'=> __('Password Change Success..'),'type'=> 'success']);

    }

    // ============================================== Admin Role Codes ==========================================

    public function all_admin_role(){
        $roles = Role::all();
        return view(self::BASE_PATH.'role.index',compact('roles'));
    }

    public function new_admin_role_index(){
        $permissions = Permission::all();
        return view(self::BASE_PATH.'role.create',compact('permissions'));
    }

    public function store_new_admin_role(Request $request){
        $this->validate($request,[
            'name' => 'required|string|max:191|unique:roles,name'
        ]);
        $role = Role::create(['name' => $request->name,'guard_name' => 'admin']);
        $role->syncPermissions($request->permission);
        return back()->with(FlashMsg::settings_update('New Role Created'));
    }

    public function edit_admin_role($id){
        $role = Role::find($id);
        $permissions = Permission::get();
        $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id",$id)
            ->pluck('role_has_permissions.permission_id','role_has_permissions.permission_id')
            ->all();
        return view(self::BASE_PATH.'role.edit',compact('role','permissions','rolePermissions'));
    }

    public function update_admin_role(Request $request){
        $this->validate($request,[
            'name' => 'required|string|max:191',
            'permission' => 'required|array',
        ]);
        $role = Role::find($request->id);
        $role->name = $request->input('name');
        $role->save();
        $role->syncPermissions($request->permission);

        return back()->with(FlashMsg::settings_update('Role Updated'));
    }

    public function delete_admin_role($id){
        Role::findOrfail($id)->delete();
        return back()->with(FlashMsg::settings_delete('Role Deleted'));
    }
}
