<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts that sign in without an email (students by NIS, teachers
     * by NIP): `username` is unique per tenant like the email, the email
     * becomes optional, and `must_change_password` marks an account whose
     * password was set by someone else.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->after('name');
            $table->unique(['tenant_id', 'username']);
            $table->string('email')->nullable()->change();
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'username']);
            $table->dropColumn(['username', 'must_change_password']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
