<?php

namespace App\Services;

use App\Models\FinancialBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\PendingPayment;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Universities;
use Illuminate\Support\Facades\Schema;

class ValidationService2
{
       public function handle()
    {
        echo "<br>"; print_r('Starting to process pending payments...');
         $univs = Universities::where('IS_IT_ENABLE', 1)->get(['UNIV_DB_NAME', 'UNID', 'MINISTRY_ID', 'BRANSH_ID']);
        if ($univs->count() > 0) {
            foreach ($univs as $univ) {
                // Cambiar la conexión de la base de datos
                DB::purge('mysql3');
                config(['database.connections.mysql3.database' => $univ->UNIV_DB_NAME]);
                DB::reconnect('mysql3');
                echo "<br>"; print_r("Switched to database: " . $univ->UNIV_DB_NAME);
                $this->processPaymentsForUniversity();
                
            }
        }
      
        
        
        
    }
    public function processPaymentsForUniversity()
    {
        try{
        if (Schema::connection('mysql3')->hasTable('api_pending_payments')) {
    // Table exists

         // Obtener todos los pagos pendientes no procesados
        $pendingPayments = PendingPayment::on('mysql3')->where('status_flag', 1) // 1: Enviado a procesamiento
            ->get();
            
        echo "<br>"; print_r("Found {$pendingPayments->count()} pending payments to process");
        
        $processed = 0;
        $errors = 0;
        
        foreach ($pendingPayments as $pendingPayment) {
            echo "<br>"; print_r("Processing payment ID: {$pendingPayment->api_payment_id}");
            
            try {
              
                
                // Ejecutar la función financiera
                $result = $this->executeFinancialFunction(
                    $pendingPayment->api_payment_id, 
                    $pendingPayment->payment_key
                );
                
                if ($result && strpos(strtolower($result), 'error') === false) {
                    // Éxito - eliminar el registro pendiente
                    $pendingPayment->update(['status_flag' => 2]);
                    echo "<br>"; print_r("Payment processed successfully: {$pendingPayment->api_payment_id}");
                    $processed++;
                } else {
                    // Error en el procesamiento
                    $pendingPayment->status_flag = 99; // 99: Error en procesamiento
                    $pendingPayment->error_message = $result ?: 'Unknown error';
                    $pendingPayment->save();
                    
                    $this->error("Error processing payment {$pendingPayment->api_payment_id}: {$pendingPayment->error_message}");
                    $errors++;
                }
            } catch (\Exception $e) {
                // Error en el procesamiento
                $pendingPayment->status_flag = 99; // 99: Error en procesamiento
                $pendingPayment->error_message = $e->getMessage();
                $pendingPayment->save();
                
                $this->error("Exception processing payment {$pendingPayment->api_payment_id}: {$e->getMessage()}");
                $errors++;
                
                Log::error("Payment processing error in cron job: " . $e->getMessage());
            }
        }
        echo "<br>"; print_r("Finished processing pending payments. Processed: {$processed}, Errors: {$errors}");
    }else{
        echo "<br>"; print_r("No pending payments found.");
    }
     return 0;
}catch (\Exception $e) {    

    echo "<br>"; print_r("Error processing payments for university: " . $e->getMessage());
    return 0;
}
    }

    /**
     * Execute financial function for payment
     *
     * @param int $paymentId
     * @param string $key
     * @return mixed
     */
    private function executeFinancialFunction(int $paymentId, string $key)
    {
        return optional(collect(DB::connection('mysql3')->select(
            'SELECT financial_function_api_send_payment(?,?) as msg',
            [
                $paymentId,
                $key
            ]
        ))->first())->msg;
    }
}
