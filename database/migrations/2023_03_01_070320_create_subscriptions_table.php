<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->text('booking_id');
            $table->integer('user_id');
            $table->string('customer_id');
            $table->string('subscription_id');
            $table->string('plan_id');
            $table->string('plan_interval');
            $table->string('currency');
            $table->string('plan_period_start');
            $table->string('plan_period_end');
            $table->float('amount_paid');
            $table->string('invoice_url');
            $table->string('payment_status');
            $table->string('subscription_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
};
