<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Tambahkan ini

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Simpan data user yang sudah ada (jika perlu)
        $users = [];
        if (Schema::hasTable('users')) {
            $users = DB::table('users')->get()->toArray();
        }
        
        // Hapus tabel users
        Schema::dropIfExists('users');
        
        // Buat ulang tabel users dengan struktur baru
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('nama_puskesmas');
            $table->rememberToken();
            $table->timestamps();
        });
        
        // Kembalikan data user yang sudah ada (jika perlu)
        // Perlu penyesuaian untuk konversi kolom
        foreach ($users as $user) {
            if (isset($user->name) && isset($user->password)) {
                DB::table('users')->insert([
                    'username' => $user->email ?? $user->name, // Gunakan email atau name sebagai username
                    'password' => $user->password,
                    'nama_puskesmas' => $user->name,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};