<?php
namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\DataProcess;
use App\Models\ContractSolutions; // Assuming the model name is ContractSolution
use Illuminate\Http\Request;

class UsageController extends Controller
{
    /**
     * Get the document count and contract solution count for a specific user.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserDocumentCount($id)
    {
        // Fetch the user by ID
        $user = User::find($id);

        // If user doesn't exist, return an error response
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Count the documents associated with the user
        $documentCount = $user->documents()->count();

        // Count the contract solutions associated with the user
        $contractSolutionCount = $user->contractSolutions()->count();

        $dataProcessCount = $user->dataprocesses()->count();

        // Return the count along with user ID
        return response()->json([
            'user_id' => $user->id,
            'document_count' => $documentCount,
            'contract_solution_count' => $contractSolutionCount,
            'data_process_count' => $dataProcessCount,
        ]);
    }
}
