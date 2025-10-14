<?php
namespace App\Http\Controllers\Helper;


use App\Models\invoice;
use App\Models\students;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Auth;
use Illuminate\Support\Facades\DB;
use App\Models\GeneralSetting;
use App\Models\Universities;
use App\Http\Controllers\API\FinancialApiController as FinancialApiController;
use App\Models\FinancialBank;
use App\Models\CenterUniversities;
use App\Models\invoicedetails;
use App\Models\RequestLogeFile;



class ConnectionDriverController extends Controller
{

    public $longinvoice;
    public $connectionDriver;
    public $univ;
    public function getministry($invoice_ident)
    {

        return substr("$invoice_ident", 0, 1);
    }
    public function getunivbransh($invoice_ident)
    {
        $univ = substr($invoice_ident, 1, 4);
        return substr($univ, 2, 3);
    }
    public function getuniv($invoice_ident)
    {
        return substr($invoice_ident, 1, 4);
    }
    public function getshortinvoice($invoice_ident)
    {


        return   substr($invoice_ident, 5, strlen($invoice_ident));
    }

    public function get_active_univ($type)
    {
        try {
           

            if($type==0)
            {
            $univs = Universities::where('IS_IT_ENABLE', 1)->get(['UNIV_DB_NAME', 'UNID', 'MINISTRY_ID', 'BRANSH_ID']);
            }
            else{
            $this->reconnectDriver('language_centers_landlord',$type);
            $univs = CenterUniversities::where('IS_IT_ENABLE', 1)->get(['DATABASE_NAME', 'id']);
            }
            //  $univs=Universities::where('IS_IT_ENABLE', 1)->pluck('UNIV_DB_NAME','UNID')->toArray();
           
            //	if(sizeof($univs)>0) { return $univs;}else{return null; }
            if ($univs->count() > 0) {
              
                return $univs;
            } else {
               
                return null;
            }
        } catch (\Exception $e) {
            \Log::error("get_active_univ Exception: " . $e->getMessage());
            return null;
        }
    }
    public function redirect_db($longInvoice)
    {
      
         $connectionDriver='';
     
        
         $ConnData=array();

        $this->longinvoice = $longInvoice;

        $minstry = $this->getministry($this->longinvoice);
        $univ = $this->getuniv($this->longinvoice);
        $univ_sub = $this->getunivbransh($this->longinvoice);
        $id = $this->getshortinvoice($this->longinvoice);
        $type=0;
        
       
        if((int)$univ_sub >50 && (int)$univ_sub <60)
        {
            \Log::error("type".$type);
            $type=1;
        }

      
        $univ_DB = $this->get_active_univ($type);
        //	dd(str_replace('0','',substr($univ, 0, 2)));
        $univ_id = (int)substr($univ, 0, 2);
        $this->univ = $univ_id;
        
        try {
            foreach ($univ_DB as   $value) {

                if($type==0)
                {
                    if (($univ_id == $value->UNID) && ($minstry == $value->MINISTRY_ID) && ($univ_sub == $value->BRANSH_ID)) {

                        $connectionDriver = $value->UNIV_DB_NAME;
                    }
                 }else{
                   
                    if (($univ_id == $value->id)) {

                        $connectionDriver = $value->DATABASE_NAME;
                      
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("type".$e->getMessage());
            return $this->sendError('#504', " ");
        }
        $request_prefix = $minstry . $univ;
       // \Log::error(" this->connectionDriver".$connectionDriver);
      
           if($type==0){
           // \Log::error("---type0");
                            $request_prefix = $minstry . $univ;


                            if (!($this->get_invoice_prifex($connectionDriver) == $request_prefix)) {
                              
                                return $this->sendError('#504', " ");
                            }
                          
            }else{
              // \Log::error("---type1");
                            $request_prefix = $minstry . $univ;

                            if (!($this->get_center_invoice_prifex($connectionDriver) == $request_prefix)) {
                              
                                return $this->sendError('#504', " ");
                            }
                          

            }
            $ConnData['type']=$type;
		$ConnData['id']=$id;
     
		$ConnData['connectionDriver']=$connectionDriver;
        $ConnData['minstry']=$minstry;
        $ConnData['univ']=$univ;
        $ConnData['branch']=$univ_sub;
       // $ConnData['univ_DB']= $univ_DB;
        $ConnData['univ_id']= $univ_id;
     
        return $ConnData;
		}

        public function get_center_invoice_prifex($connectionDriver)
        {
            try {
    
                DB::purge('center');
                config(['database.connections.center.database' =>  $connectionDriver]);
                DB::reconnect('center');
    
    
    
                if (DB::connection('center')->getDatabaseName() == "") {
    
                    return 0;
                }
    
                try {
                    $prifex = GeneralSetting::on('center')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;
    
                } catch (Excption $e) {
                  //  dd($e->message());
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
        public function get_invoice_prifex($connectionDriver)
    {
        try {

            $this->reconnectDriver($connectionDriver,0);
           /* DB::purge('mysql1');
            config(['database.connections.mysql1.database' =>   $this->connectionDriver]);
            DB::reconnect('mysql1');*/

            //dd(DB::connection('mysql1')->getDatabaseName());

            if (DB::connection('mysql1')->getDatabaseName() == "") {

                return 0;
            }

            try {

                $prifex = GeneralSetting::on('mysql1')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;

            } catch (\Exception $e) {

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
public function chooseUniv($univ_id) {

   $univ_sub=substr($univ_id,3,6);
 
   $ConnData=[];

       
        $type=0;
        if((int)$univ_sub >50 && (int)$univ_sub <60)
        {
           \Log::error("type".$type);
            $type=1;
        }
        $univ_DB = $this->get_active_univ($type);
        //	dd(str_replace('0','',substr($univ, 0, 2)));

       $univ_id=(int)substr($univ_id, 1,2);
  
 

        try {
            foreach ($univ_DB as   $value) {

                if($type==0)
                {
                    if (($univ_id == $value->UNID) ) {

                        $ConnData['connectionDriver']=$value->UNIV_DB_NAME;
                    }
                 }else{

                    if (($univ_id == $value->id)) {

                        $ConnData['connectionDriver']=$value->DATABASE_NAME;
                      
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("type".$e->getMessage());
            return $this->sendError('#504', " ");
        }
     
       \Log::error(" this->connectionDriver".$this->connectionDriver);
      
      
     $ConnData['type']=$type;

	  return $ConnData;
}
    public function reconnectDriver($UNIV_DB_NAME,$type){
      
        if($type==1){
        DB::purge('center');
        config(['database.connections.center.database' => $UNIV_DB_NAME]);
        DB::reconnect('center');
        }
        else{
        DB::purge('mysql1');
        config(['database.connections.mysql1.database' =>   $UNIV_DB_NAME]);
        DB::reconnect('mysql1');
        }
    }
}
