<?php

namespace App\Console\Commands;

use App\Models\PendingPayment;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Universities;
use App\Models\API_PYMENT;
use Exception;
use Carbon\Carbon;

class ProcessPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:process-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending payments that were registered but not yet processed';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to process pending payments...');
         $univs = Universities::where('IS_IT_ENABLE', 1)->get(['UNIV_DB_NAME', 'UNID', 'MINISTRY_ID', 'BRANSH_ID']);
        if ($univs->count() > 0) {
            foreach ($univs as $univ) {
                // Cambiar la conexión de la base de datos
                DB::purge('mysql3');
                config(['database.connections.mysql3.database' => $univ->UNIV_DB_NAME]);
                DB::reconnect('mysql3');
                $this->info("Switched to database: " . $univ->UNIV_DB_NAME);
              //  \Log::error("Switched to database: " . $univ->UNIV_DB_NAME);
                $this->processPaymentsForUniversity();
                // Reprocess failed payments (status 99) for the fixed date 2025-09-22: 01:00 to 09:40
                $start = '2025-09-22 00:00:00';
                $end = '2025-09-22 09:40:00';
                $this->processFailedPaymentsForUniversityWindow($start, $end);

            }
        }




    }
    public function processPaymentsForUniversity()
    {
        try{
         // Obtener todos los pagos pendientes no procesados
        $pendingPayments = PendingPayment::on('mysql3')->where('status_flag', 1) // 1: Enviado a procesamiento
            ->get();

      //  $this->info("Found {$pendingPayments->count()} pending payments to process");

        $processed = 0;
        $errors = 0;
        $Api_payment=[];

        foreach ($pendingPayments as $pendingPayment) {
          //  $this->info("Processing payment ID: {$pendingPayment->api_payment_id}");
            //\Log::info("Processing payment ID: {$pendingPayment->api_payment_id}");
            try {



                // Ejecutar la función financiera
                        $result = $this->executeFinancialFunction(
                            $pendingPayment->api_payment_id,
                            $pendingPayment->payment_key
                        );
              //    \Log::info("Payment processed result:{$result}");
                if ($result==1 && strpos(strtolower($result), 'error') === false) {
                        // Success - set status to processed
                        $pendingPayment->update(['status_flag' => 2, 'error_message' => "The result from financial_function_api_send_payment :"]);
                      
                    // Éxito - eliminar el registro pendiente
                    $pendingPayment->update(['status_flag' => 2]);
                //    $this->info("Payment processed successfully: {$pendingPayment->api_payment_id}");
                //     \Log::info("Payment processed successfully:{$pendingPayment->api_payment_id}");
                    $processed++;
                    array_push($Api_payment,$pendingPayment->api_payment_id);
                } else {
                    // Error en el procesamiento
                    $pendingPayment->status_flag = 99; // 99: Error en procesamiento
                    $pendingPayment->error_message = $result ?: 'Unknown error';
                    $pendingPayment->save();

                //    $this->error("Error processing payment {$pendingPayment->api_payment_id}: {$pendingPayment->error_message}");
                  //  \Log::info("Error processing payment:{$pendingPayment->api_payment_id}");
                    $errors++;
                }
            } catch (\Throwable $e) {
                // Error en el procesamiento
                $pendingPayment->status_flag = 99; // 99: Error en procesamiento
                $pendingPayment->error_message = $e->getMessage();
                $pendingPayment->save();

            //    $this->error("Exception processing payment {$pendingPayment->api_payment_id}: {$e->getMessage()}");
               //  \Log::info("Exception processing paymen {$pendingPayment->api_payment_id}: {$e->getMessage()}");
                $errors++;

               // Log::error("Payment processing error in cron job: " . $e->getMessage());

            }
        }
        API_PYMENT::on('mysql3')->whereIn('API_PAYMENT_ID', $Api_payment)->update(['PAYMENT_FLAG'=>1]);
      //  $this->info("Finished processing pending payments. Processed: {$processed}, Errors: {$errors}");
         // Log::error("Finished processing pending payments. Processed: {$processed}, Errors: {$errors}");

        return 0;
    }catch(Exception $e){
        Log::error("Payment processing error in cron job: " . $e->getMessage());
         return 0;
    }
    }

    /**
     * Process payments that previously failed (status_flag = 99) within a given time window.
     *
     * Example usage for the 22nd day from 00:00 to 09:40 of that day:
     *   $this->processFailedPaymentsForUniversityWindow('2025-09-22 00:00:00', '2025-09-22 09:40:00');
     *
     * @param string $startDateTime  Start of the window in 'Y-m-d H:i:s' format
     * @param string $endDateTime    End of the window in 'Y-m-d H:i:s' format
     * @return int
     */
    public function processFailedPaymentsForUniversityWindow(string $startDateTime, string $endDateTime)
    {
        try {
        //    \Log::error("in processFailedPaymentsForUniversityWindow" . $startDateTime . '' . $endDateTime);
            $start = Carbon::parse($startDateTime);
            $end = Carbon::parse($endDateTime);

            $this->info("Processing failed payments between {$start->toDateTimeString()} and {$end->toDateTimeString()}");

            // Get failed payments (status_flag = 99) within the time window
           $pendingPayments = PendingPayment::on('mysql3')
                ->whereNotNull('error_message')
                ->whereBetween('created_at', [$start, $end])
                ->get();
          
          //  \Log::info("Found {$pendingPayments->count()} failed payments to reprocess in the given window");
            $this->info("Found {$pendingPayments->count()} failed payments to reprocess in the given window");

            $processed = 0;
            $errors = 0;
            $Api_payment = [];

            foreach ($pendingPayments as $pendingPayment) {
               // $this->info("Reprocessing failed payment ID: {$pendingPayment->api_payment_id}");
                try {
                      if(   DB::connection('mysql3')
                            ->table('financial_invoices')
                            ->where('INVOICE_IDENT', $pendingPayment->invoice_id)
                            ->where('PAYMENT_FLAG', 1)
                            ->exists()) {
                                $pendingPayment->status_flag = 2; // 99: Error en procesamiento

                                $pendingPayment->save();
                            } else {
                    // Execute the financial function again
                    $result = $this->executeFinancialFunction(
                        $pendingPayment->api_payment_id,
                        $pendingPayment->payment_key
                    );
                  //  \Log::info("Reprocess result:{$result}");

                    if ($result==1 && strpos(strtolower($result), 'error') === false) {
                        // Success - set status to processed
                        $pendingPayment->update(['status_flag' => 2, 'error_message' => "The result from financial_function_api_send_payment :"]);
                      
                        $pendingPayment->update(['status_flag' => 2]);
                        $this->info("Payment reprocessed successfully: {$pendingPayment->api_payment_id}");
                    //    \Log::info("Payment reprocessed successfully:{$pendingPayment->api_payment_id}");
                        $processed++;
                        $Api_payment[] = $pendingPayment->api_payment_id;
                    } else {
                        // Still error - keep status as 99 and record message
                        $pendingPayment->status_flag = 99; // 99: Error en procesamiento
                        $pendingPayment->error_message = $result ?: 'Unknown error';
                        $pendingPayment->save();

                        $this->error("Error reprocessing payment {$pendingPayment->api_payment_id}: {$pendingPayment->error_message}");
                        $errors++;
                    }
                }
                } catch (\Throwable $e) {
                    $pendingPayment->status_flag = 99; // 99: Error en procesamiento
                    $pendingPayment->error_message = $e->getMessage();
                    $pendingPayment->save();

                    $this->error("Exception reprocessing payment {$pendingPayment->api_payment_id}: {$e->getMessage()}");
                    $errors++;
                }
            }

            if (!empty($Api_payment)) {
                API_PYMENT::on('mysql3')
                    ->whereIn('API_PAYMENT_ID', $Api_payment)
                    ->update(['PAYMENT_FLAG' => 1]);
            }

            $this->info("Finished reprocessing failed payments in window. Processed: {$processed}, Errors: {$errors}");

            return 0;
        } catch (Exception $e) {
            Log::error("Failed payments reprocessing error in cron job: " . $e->getMessage());
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
       //   $this->info("executeFinancialFunction : {$paymentId}");
        return optional(collect(DB::connection('mysql3')->select(
            'SELECT financial_function_api_send_payment(?,?) as msg',
            [
                $paymentId,
                $key
            ]
        ))->first())->msg;
    }
}
