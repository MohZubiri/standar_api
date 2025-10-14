<?php

namespace App\Services;

use App\Models\API_PYMENT;
use App\Models\FinancialAccountFees;
use App\Models\FinancialBank;
use App\Models\GeneralSetting;
use App\Models\invoice;
use App\Models\invoicedetails;
use App\Models\PaymentLogeFile;
use App\Models\RequestLogeFile;
use App\Models\studentbill;
use App\Models\students;
use App\Models\Universities;
use App\Models\CenterUniversities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RedirectConnectionService
{

 public function redirect_db($longInvoice)
    {
         try {
          $result=[];
          $result['longInvoice'] = $longInvoice;
          $result['paymentType']=0;
        
        // Check if this is a graduate studies portal invoice
        if (substr((string)$longInvoice, 0, 5) === '10001') {
           $result['paymentType'] = 2;
           $result['id'] = substr($longInvoice, 5, strlen($longInvoice));
            return $result;
        }
     
        $minstry = $this->getministry($longInvoice);
        $univ = $this->getuniv($longInvoice);
        $univ_sub = $this->getunivbransh($longInvoice);
        $id = $this->getshortinvoice($longInvoice);
     
        $result['id']= $id;
        $result['minstry']=$minstry;
        $result['univ']=$univ;
        $result['univ_sub']=$univ_sub;
        // Determine if this is a center payment
        $type = 0;
        if ((int)$univ_sub > 50 && (int)$univ_sub < 60) {
            Log::error("type" . $type);
            $type = 1;
        }
        
     
        $univ_DB = $this->get_active_univ($type);
      
        $univ_id = (int)substr($univ, 0, 2);
         $result['univ_id']= $univ_id;

       
            foreach ($univ_DB as $value) {
             
                if ($type == 0) {
                  
                    // Regular university payment
                    if (($univ_id == $value->UNID) && ($minstry == $value->MINISTRY_ID) && ($univ_sub == $value->BRANSH_ID)) {
                        $result['connectionDriver']  = $value->UNIV_DB_NAME;
                    }
                } else {
               
                    if ($univ_id == $value->id) {
                        $result['connectionDriver']  = $value->database;
                        $result['paymentType'] = 1;
                    }
                }
            }
       
      
      
     
        $request_prefix = $minstry . $univ;
      
        Log::error(" this->connectionDriver" .  $result['connectionDriver']);
        Log::error(" this->paymentType" .  $result['paymentType']);
       
        if ($type == 0) {
            
            // Regular university payment validation
            Log::error("---type0");
            Log::error("============" . $request_prefix);
            Log::error("============" . $this->get_invoice_prifex($result['connectionDriver']));
            
            if (!($this->get_invoice_prifex($result['connectionDriver']) == $request_prefix)) {
                return ['success' => false, 'code' => '#504', 'message' => ' '];
            }
            return $result;
        } else {
            // Center payment validation
            Log::error("---type1");
            Log::error($result);
            if (!($this->get_center_invoice_prifex($result['connectionDriver']) == $request_prefix)) {
                return ['success' => false, 'code' => '#504', 'message' => ' '];
            }
             
            return $result;
        }
          } catch (\Exception $e) {
            Log::error("type" . $e->getMessage());
            return ['success' => false, 'code' => '#504', 'message' => ' '];
           
        }
    }

  public function get_invoice_prifex($connectionDriver)
    {
        try {

            DB::purge('mysql1');
            config(['database.connections.mysql1.database' =>   $connectionDriver]);
            DB::reconnect('mysql1');

            if (DB::connection('mysql1')->getDatabaseName() == "") {

                return 0;
            }

            try {
                $prifex = GeneralSetting::on('mysql1')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;
            } catch (\Exception $e) {
                Log::error("Error getting invoice prefix: " . $e->getMessage());
            }
            if (!is_null($prifex)) {
                return $prifex;
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    public function get_center_invoice_prifex($connectionDriver)
    {
        try {
            DB::purge('center');
            config(['database.connections.center.database' => $connectionDriver]);
            DB::reconnect('center');
          
            if (DB::connection('center')->getDatabaseName() == "") {
                return 0;
            }

            try {
                $prifex = GeneralSetting::on('center')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;
               
            } catch (\Exception $e) {
                Log::error("Error getting center invoice prefix: " . $e->getMessage());
            }
            
            if (!is_null($prifex ?? null)) {
                return $prifex;
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            Log::error("Center invoice prefix exception: " . $e->getMessage());
            return 0;
        }
    }
     public function get_active_univ($type)
    {
        try {

            if($type==0)
            $univs = Universities::where('IS_IT_ENABLE', 1)->get(['UNIV_DB_NAME', 'UNID', 'MINISTRY_ID', 'BRANSH_ID']);
            else{
                log::error(json_encode(DB::connection('center')->getDatabaseName())  );
              log::error( json_encode(CenterUniversities::where('IS_IT_ENABLE', 1)->toSql())  );
            $univs = CenterUniversities::where('IS_IT_ENABLE', 1)->get(['database', 'id']);
            }

            
log::error('get_active_univ::'.$univs );
            //	if(sizeof($univs)>0) { return $univs;}else{return null; }
            if ($univs->count() > 0) {
                return $univs;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            log::error('get_active_univ'.$e->getMessage());
        }
    }

 public function setupDatabaseConnection($connectionDriver)
    {
        DB::purge('mysql1');
        config(['database.connections.mysql1.database' => $connectionDriver]);
        DB::reconnect('mysql1');
    }
      public function getministry($invoice_ident)
    { //33

        return substr("$invoice_ident", 0, 1);
    }

    public function getuniv($invoice_ident)
    {
        return substr($invoice_ident, 1, 4);
    }
    public function getunivName($invoice_ident)
    {
        $minstryID = $this->getministry($invoice_ident);
        $branshID = $this->getunivbransh($invoice_ident);
        $univID = substr($invoice_ident, 1, 4);
        $id = substr($univID, 0, 2);


       $name = Universities::where('UNID', $id)->where('MINISTRY_ID', $minstryID)->where('BRANSH_ID', $branshID)->first()->U_NAME;

        return $name;
    }
    public function getunivbransh($invoice_ident)
    {
        $univ = substr($invoice_ident, 1, 4);
        return substr($univ, 2, 3);
    }
    public function getshortinvoice($invoice_ident)
    {


        return   substr($invoice_ident, 5, strlen($invoice_ident));
    }
}