<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OrganizationalUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log; // Import the Log facade


class UserController extends Controller
{
    public function register_user(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'services' => 'required|array',
            'is_user_organizational' => 'nullable|boolean',
            'creator_id' => 'required|exists:users,id',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);

        if ($request->services) {
            $user->services = $request->services;
        }

        if ($request->org_id) {
            $user->org_id = $request->org_id;
        }

        // Set the is_user_organizational flag
        $user->is_user_organizational = $request->is_user_organizational;

        // Save the user
        $user->save();

        // Save the creator and new user in OrganizationalUser
        OrganizationalUser::create([
            'user_id' => $request->creator_id,
            'organizational_id' => $user->id,
        ]);

        // Create a token for the new user
        $token = $user->createToken('user_token')->plainTextToken;

        // Return the response
        return response()->json([
            "message" => "User registered successfully. Please verify your email to continue.",
            "user" => $user,
            "token" => $token,
        ], 200);
    }




    public function getOrganizationUsers(Request $request)
{
    // Get the authenticated user
    $user = $request->user();

    // Log the full user details
    Log::info('Authenticated user details:', ['user' => $user]);

    // Check if the user is part of any organization
    $organizationalUser = OrganizationalUser::where('user_id', $user->id)->with('user')->first();

    if (!$organizationalUser) {
        Log::warning('User is not part of any organization', ['user' => $user]);

        return response()->json([
            'message' => 'This user does not belong to any organization',
        ], 403);
    }

    // Log full organizational user details
    Log::info('Organizational user details', ['organizational_user' => $organizationalUser]);

    // Retrieve all users associated with this organization (based on organizational_id)
    $usersInOrganization = OrganizationalUser::where('organizational_id', $organizationalUser->organizational_id)
        ->with('user') // Fetch associated user details
        ->get()
        ->pluck('user'); // Get only the user data

    // Log the full list of users in the organization
    Log::info('All users in the organization:', ['organization_users' => $usersInOrganization]);

    // Return the users in the organization
    return response()->json([
        'organization_users' => $usersInOrganization,
    ], 200);
}

}
