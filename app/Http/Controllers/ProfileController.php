<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;

use Spatie\Permission\Models\Role;
use App\Models\dpb_gasprice;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $rs_user = User::all();
        $headers = [
            'name' => 'Usuario',
            'email' => 'Correo',
            'user_status' => 'Estado',
        ];

        return view('profile.edit', [
            'user' => $request->user(),
            'roles' => $roles,
            'user_list' => [$headers, $rs_user]
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse|JsonResponse
    {
        if (!empty($request->input('id'))) {
            $user = User::find($request->input('id'));
            if (!$user) {
                return response()->json(['message' => 'Usuario no encontrado.'], 404);
            }

            $user->name = $request->name;
            $user->email = $request->email;
            $user->user_status = $request->user_status;

            if ($request->filled('password')) { // <-- Verifica si el campo 'password' existe y no está vacío
                $user->password = Hash::make($request->input('password'));
            }
            $user->save();

            return response()->json(['message' => 'Informacion Actualizada.', 'data' => $user], 200);
        }else{
            $user = $request->user();
            $request->user()->fill($request->validated());

            if ($request->user()->isDirty('email')) {
                $request->user()->email_verified_at = null;
            }

            if ($request->filled('gasPrice')) {
                $request->validate([
                    'gasPrice' => 'required|numeric|max:999999999|min:0',
                ]);
                
                dpb_gasprice::where('gasprice_status', 1)->update(['gasprice_status' => 0]);

                $m_gasprice = new dpb_gasprice;
                $m_gasprice->gasprice_userid = $user->id;
                $m_gasprice->gasprice_price = $request->input('gasPrice');
                $m_gasprice->gasprice_status = 1;
                $m_gasprice->save();
            }
            $request->user()->save();

            return Redirect::route('profile.edit')->with('status', 'profile-updated');
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $user = null;
        if (!empty($request->input('userId'))) {
            $user = User::find($request->input('userId'));
        }else{
            $user =  $request->user();
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current-password'],
            ]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        if (!$user) {
            // Manejo de error si el userId no existe o no se encontró
             if ($request->wantsJson()) {
                 return response()->json(['message' => 'User not found.'], 404);
             }
            return Redirect::to('/');
        }

        $user->user_status = 0;
        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Usuario desactivado correctamente.'], 200);
        }
        
        return Redirect::to('/');
    }

    public function filter_user(Request $request){
        if ($request->has('filter_user') && $request->input('filter_user')) {
            $rs_user = User::where('name', 'LIKE', '%' . $request->input('filter_user') . '%')
                ->orWhere('email', 'LIKE', '%' . $request->input('filter_user') . '%')
                ->limit(1)
                ->get();
        } else {
            $rs_user = User::limit(3)->get();
        }
        return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
    }
}
