<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->renameColumn('key', 'license_key');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->string('instance_id')->nullable()->after('license_key');
            $table->string('instance_name')->nullable()->after('instance_id');
            $table->unsignedBigInteger('license_key_id')->nullable()->after('instance_name');
            $table->string('status')->default('active')->after('license_key_id');
            $table->string('customer_name')->nullable()->after('status');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('product_name')->nullable()->after('customer_email');
            $table->unsignedInteger('activation_limit')->nullable()->after('product_name');
            $table->unsignedInteger('activation_usage')->default(0)->after('activation_limit');
            $table->timestamp('expires_at')->nullable()->after('activation_usage');
            $table->timestamp('last_validated_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn([
                'instance_id',
                'instance_name',
                'license_key_id',
                'status',
                'customer_name',
                'customer_email',
                'product_name',
                'activation_limit',
                'activation_usage',
                'expires_at',
                'last_validated_at',
            ]);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->renameColumn('license_key', 'key');
        });
    }
};
