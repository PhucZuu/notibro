<!-- 

namespace App\Http\Controllers\Api\User\AdminUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{

    public function index()
    {
        $users = User::withTrashed()->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'There are no users.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Get list of users successfully.',
            'data' => $users,
        ], 200);
    }


    public function show($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Get user information successfully.',
            'data' => $user,
        ], 200);
    }




    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['code' => 200, 'message' => 'User deleted successfully']);
    }

    public function ban($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User is locked.',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while locking the user.',
            ], 500);
        }
    }


    public function unlock($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        if ($user->trashed()) {
            $user->restore();

            return response()->json([
                'success' => true,
                'message' => 'Account has been successfully unlocked.',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Account is not locked.',
            ], 400);
        }
    }


    public function changePermission(Request $request, $id)
    {
        $data = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        try {
            $user->roles()->sync($data['role_id']);

            return response()->json([
                'success' => true,
                'message' => 'User rights have been changed successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while changing permissions.',
            ], 500);
        }
    }



    public function forceDelete($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        $user->forceDelete();

        return response()->json(['code' => 200, 'message' => 'User permanently deleted']);
    }
} -->
