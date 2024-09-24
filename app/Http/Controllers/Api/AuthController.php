<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Organization;

use App\Models\Service;
use App\Models\{User,Translation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\OrganizationalUser;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'services' => 'required|array',
            'is_user_organizational' => 'nullable|boolean',
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

        // Store is_user_organizational value
        $user->is_user_organizational = $request->is_user_organizational;

        $user->save();

        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            "message" => "You are registered successfully. Please verify your email to continue",
            "user" => $user,
            "token" => $token,
        ], 200);
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $email = $request->email;
        $password = $request->password;
        $translations = Translation::all();
        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            // if (Auth::user()->hasVerifiedEmail()) {
            $user = Auth::user();
            $token = $user->createToken('user_token')->plainTextToken;
            return response()->json([
                "message" => "Logged in successfully",
                "user" => $user,
                "token" => $token,
                "translationData" => $translations,
            ], 200);
            // } else {
            //     return response()->json(["message" => "Your email is not verified."], 422);
            // }
        } else {
            return response()->json(["message" => "invalid_email_or_password"], 422);
        }
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);
        $user = Auth::user();

        if (Hash::check($request->input('old_password'), $user->password)) {
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'message' => 'Password changed successfully',
                'user' => $user,
            ]);
        } else {
            return response()->json([
                'message' => 'Invalid Old password.'], 400);
        }
    }

    public function getuser($id)
    {
        $user = User::with('organization')->findOrFail($id);
        $services_ids = Service::all()->keyBy('id');
        $services = Service::all();
        $orgs = Organization::all();
        if ($user->services) {
            $user->service_names = collect($user->services)->map(function ($serviceId) use ($services_ids) {
                return $services_ids->get($serviceId)->name ?? '';
            })->toArray();
        }

        return response()->json(['user' => $user, 'services' => $services, 'orgs' => $orgs], 200);
    }

    public function updateUser(Request $request, $id)
    {
        $request->validate([
            'services' => 'sometimes|array',
        ]);

        $user = User::findOrFail($id);

        // Update parent user fields
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = bcrypt($request->password);
        }
        if ($request->has('services')) {
            $user->services = $request->services;
        }
        if ($request->has('org_id')) {
            $user->org_id = $request->org_id;
        }

        $user->save();

        // Update child organizational users
        // Fetch the IDs of the child organizational users
        $childUserIds = OrganizationalUser::where('user_id', $user->id)->pluck('organizational_id');

        // Fetch the child users
        $childUsers = User::whereIn('id', $childUserIds)->get();

        // Update each child user
        foreach ($childUsers as $childUser) {
            // You can choose which fields to update for the child users
            // For example, update services and org_id to match the parent user
            if ($request->has('services')) {
                $childUser->services = $request->services;
            }
            if ($request->has('org_id')) {
                $childUser->org_id = $request->org_id;
            }
            // Optionally, update other fields as needed
            $childUser->save();
        }

        return response()->json(['message' => 'User and child organizational users updated successfully', 'user' => $user]);
    }


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function delete($id)
    {
        // Find the user by ID
        $user = User::find($id);

        // Check if the user exists
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Delete corresponding records in the organizational_user table for both as creator and as created user
        OrganizationalUser::where('user_id', $id)->orWhere('organizational_id', $id)->delete();

        // Now delete the user from the users table
        $user->delete();

        return response()->json(['message' => 'User and related records deleted successfully'], 200);
    }


    public function getUserData()
    {
        // Get the currently authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Return the user's data with the send_email field
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'send_email' => $user->send_email,


        ]);
    }

    public function getAllOrganizationalUsers()
    {
        // Fetch all users who are organizational users (i.e., 'is_user_organizational' is true)
        $usersInOrganization = User::where('is_user_organizational', true)->get();

        // Get the service IDs from the users (assuming 'services' field contains service IDs)
        $serviceIds = $usersInOrganization->pluck('services')->flatten()->unique();

        // Fetch services based on service IDs, retrieving both id and name
        $services = Service::whereIn('id', $serviceIds)->pluck('name', 'id');

        // Fetch organization names and ids for each user based on their 'org_id'
        $orgIds = $usersInOrganization->pluck('org_id')->unique();
        $organizations = Organization::whereIn('id', $orgIds)->pluck('name', 'id');

        // Map the users and replace the service IDs with service names and include organization names
        $usersWithServiceNames = $usersInOrganization->map(function ($user) use ($services, $organizations) {
            // Get the service names and ids for the user
            $userServices = collect($user->services)->map(function ($serviceId) use ($services) {
                return [
                    'id' => $serviceId,
                    'name' => $services->get($serviceId),
                ];
            });

            // Return the user data with service names and organization name and id
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'services' => $userServices,
                'organization' => [
                    'id' => $user->org_id,
                    'name' => $organizations->get($user->org_id),
                ],
            ];
        });

        // Return the organizational users with service names and organization names
        return response()->json([
            'organization_users' => $usersWithServiceNames,
        ], 200);
    }

}
