<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Service;
use App\Models\OrganizationalUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log; // Import the Log facade
use App\Models\Organization;


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
            'creator_id' => 'nullable|exists:users,id',
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
    // Get the authenticated user (the user who has created other users)
    $user = $request->user();

    // Log the full user details
    Log::info('Authenticated user details:', ['user' => $user]);

    // Fetch all the records from organizational_user where user_id is the creator's ID
    $createdUsers = OrganizationalUser::where('user_id', $user->id)->pluck('organizational_id');

    // If the user has not created any other users
    if ($createdUsers->isEmpty()) {
        Log::warning('User has not created any other users', ['user_id' => $user->id]);

    }

    // Fetch the user details for the users created by this user
    $usersInOrganization = User::whereIn('id', $createdUsers)->get();

    // Get the service IDs from the users
    $serviceIds = $usersInOrganization->pluck('services')->flatten();

    // Fetch service names based on service IDs
    $serviceNames = Service::whereIn('id', $serviceIds)->pluck('name', 'id');

    // Fetch organization names for each user based on org_id
    $orgIds = $usersInOrganization->pluck('org_id');
    $organizationNames = Organization::whereIn('id', $orgIds)->pluck('name', 'id');

    // Map the users and replace the service IDs with service names and include organization names
    $usersWithServiceNames = $usersInOrganization->map(function ($user) use ($serviceNames, $organizationNames) {
        // Get the service names for the user
        $userServiceNames = collect($user->services)->map(function ($serviceId) use ($serviceNames) {
            return $serviceNames->get($serviceId);
        });

        // Return the user data with service names and organization name
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'services' => $userServiceNames,
            'organization_name' => $organizationNames->get($user->org_id),
        ];
    });

    // Log the full list of created users with service names and organization names
    Log::info('Users created by this user:', ['created_users' => $usersWithServiceNames]);

    // Return the created users with service names and organization names
    return response()->json([
        'organization_users' => $usersWithServiceNames,
    ], 200);
}


public function delete_User($id)
{
    // Find the user by ID
    $user = User::find($id);

    // Check if the user exists
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // Delete all records in the OrganizationalUser table where the user is an organizational_id (user created by an organization)
    OrganizationalUser::where('organizational_id', $id)->delete();

    // Optionally, also delete the records where the user is the creator (user_id)
    OrganizationalUser::where('user_id', $id)->delete();

    // Now delete the user from the users table
    $user->delete();

    return response()->json(['message' => 'User deleted successfully'], 200);
}



}