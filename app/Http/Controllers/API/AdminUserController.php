<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Puskesmas;
use App\Models\TahunProgram;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with('roles');
        
        // Filter by role if provided
        if ($request->has('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }
        
        $users = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6',
            'nama_puskesmas' => 'required|string|max:255',
            'role' => 'required|string|in:admin,dinas,puskesmas'
        ]);
        
        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'nama_puskesmas' => $validated['nama_puskesmas'],
        ]);
        
        // Assign role
        $user->assignRole($validated['role']);
        
        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan',
            'data' => $user->load('roles')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('roles')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'nama_puskesmas' => 'required|string|max:255',
        ]);
        
        $user->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting yourself
        $currentUser = request()->user();
        if ($user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun sendiri'
            ], 422);
        }
        
        // Check if user has related data
        if ($user->pemeriksaan()->count() > 0 || $user->laporanBulanan()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak dapat dihapus karena memiliki data terkait'
            ], 422);
        }
        
        $user->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ]);
    }
    
    /**
     * Reset user password
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);
        
        $user->update([
            'password' => Hash::make($validated['password'])
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset'
        ]);
    }
    
    /**
     * Change user role
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'role' => 'required|string|in:admin,dinas,puskesmas',
        ]);
        
        // Remove existing roles
        $user->roles()->detach();
        
        // Assign new role
        $user->assignRole($validated['role']);
        
        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diubah',
            'data' => $user->load('roles')
        ]);
    }
    
    /**
     * Generate reports manually
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReports(Request $request)
    {
        try {
            // Validate required parameters
            $validated = $request->validate([
                'tahun_program_id' => 'required|exists:tahun_program,id',
                'bulan' => 'required|integer|min:1|max:12',
            ]);
            
            // Call artisan command to generate reports
            $exitCode = Artisan::call('laporan:generate', [
                'tahun' => $validated['tahun_program_id'],
                'bulan' => $validated['bulan'],
            ]);
            
            if ($exitCode !== 0) {
                throw new \Exception('Gagal generate laporan: ' . Artisan::output());
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil di-generate'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in generateReports: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate laporan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Archive data manually
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function archiveData(Request $request)
    {
        try {
            // Validate required parameters
            $validated = $request->validate([
                'tahun_program_id' => 'required|exists:tahun_program,id',
            ]);
            
            // Call artisan command to archive data
            $exitCode = Artisan::call('data:archive', [
                'tahun' => $validated['tahun_program_id'],
            ]);
            
            if ($exitCode !== 0) {
                throw new \Exception('Gagal mengarsipkan data: ' . Artisan::output());
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diarsipkan'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in archiveData: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengarsipkan data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get system logs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function systemLogs(Request $request)
    {
        try {
            $limit = $request->get('limit', 100);
            
            // Read the Laravel log file
            $logPath = storage_path('logs/laravel.log');
            
            if (!file_exists($logPath)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            // Get last n lines of the log file
            $logs = [];
            $file = new \SplFileObject($logPath, 'r');
            $file->seek(PHP_INT_MAX); // Seek to the end of file
            $lastLine = $file->key(); // Get the last line number
            
            $lines = min($limit, $lastLine);
            $start = max(0, $lastLine - $lines);
            
            $file->seek($start);
            
            while (!$file->eof()) {
                $line = $file->fgets();
                if (trim($line)) {
                    $logs[] = $line;
                }
                
                if (count($logs) >= $limit) {
                    break;
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in systemLogs: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan log sistem: ' . $e->getMessage()
            ], 500);
        }
    }
}