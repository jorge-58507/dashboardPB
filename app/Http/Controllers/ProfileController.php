<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use App\Models\dpb_gasprice;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $roles = Role::select('id', 'name')->orderBy('name')->get();

        return view('profile.edit', [
            'user' => $request->user(),
            'roles' => $roles,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
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

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current-password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->user_status = 0;
        $user->save();
        //$user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
