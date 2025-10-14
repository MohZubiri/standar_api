<?php
namespace App\Http\Controllers\API;


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
class CheckingController extends Controller
{
	 private $connectionDriver = "";
	 
	 public function ReRequest(Request $req){
		 
	 $id = $this->redirect_db($req->id);
			
			
			 DB::purge('mysql1');
            config(['database.connections.mysql1.database' =>   $this->connectionDriver]);
            DB::reconnect('mysql1');
		return (new FinancialApiController)->getInvoiceFinincialStatus($req->univ,$req->stud,$req->fcul,$id,$req->bnk,$req->doid);
		 
	 }
	     public function get_invoice_prifex()
    {
        try {

            DB::purge('mysql1');
            config(['database.connections.mysql1.database' =>   $this->connectionDriver]);
            DB::reconnect('mysql1');

            //dd(DB::connection('mysql1')->getDatabaseName());

            if (DB::connection('mysql1')->getDatabaseName() == "") {

                return 0;
            }

            try {
                $prifex = GeneralSetting::on('mysql1')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;
            } catch (Excption $e) {
              //  dd($e->message());
            }
            if (!is_null($prifex)) {
                return $prifex;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            return 0;
        }
    }
	  public function get_active_univ()
    {
        try {

            $univs = Universities::where('IS_IT_ENABLE', 1)->get(['UNIV_DB_NAME', 'UNID', 'MINISTRY_ID', 'BRANSH_ID']);
          
            if ($univs->count() > 0) {
                return $univs;
            } else {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }
    }
	  public function getministry($invoice_ident)
    { //33

        return substr("$invoice_ident", 0, 1);
    }

    public function getuniv($invoice_ident)
    {
        return substr($invoice_ident, 1, 4);
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
	public function redirect_db($longInvoice)
    {
        $this->longinvoice = $longInvoice;

        $minstry = $this->getministry($this->longinvoice);
        $univ = $this->getuniv($this->longinvoice);
        $univ_sub = $this->getunivbransh($this->longinvoice);
        $id = $this->getshortinvoice($this->longinvoice);

        $univ_DB = $this->get_active_univ();
        //	dd(str_replace('0','',substr($univ, 0, 2)));
        $univ_id = (int)substr($univ, 0, 2);
        $this->univ = $univ_id;

        
        try {
            foreach ($univ_DB as   $value) {

                if (($univ_id == $value->UNID) && ($minstry == $value->MINISTRY_ID) && ($univ_sub == $value->BRANSH_ID)) {

                    $this->connectionDriver = $value->UNIV_DB_NAME;
                }
            }
        } catch (\Exception $e) {

            return $this->sendError('#504', " ");
        }
        $request_prefix = $minstry . $univ;

        //dd($this->get_invoice_prifex());
        if (!($this->get_invoice_prifex() == $request_prefix)) {

            return $this->sendError('#504', " ");
        }
        return $id;
    }
	
public function getCheckOut(Request $request){
 $input = $request->all();

        $validator = Validator::make($input, [
            'NO' => 'required|integer',
            'UNIV' => 'required|integer',
           
        ], [
            'required' => 'هذا الحقل مطلوب'
        ]);


 if ($validator->fails()) {
            return $this->sendError('#502', " ");
        } else {
			//dd($input['NO']);
			
			$id = $this->redirect_db($input['NO']);
			
			
			
			 DB::purge('mysql1');
            config(['database.connections.mysql1.database' =>   $this->connectionDriver]);
            DB::reconnect('mysql1');
			//dd(invoice::on('mysql1')->where('INVOICE_IDENT', $id )->get());
			//dd(invoice::on('mysql1')->where('INVOICE_IDENT', $id )->first());
			$invoice=optional(invoice::on('mysql1')->where('INVOICE_IDENT', $id )->first());
			$student_faculty = optional(students::on('mysql1')->where('STUDENT_IDENT', $invoice->STUDENT_IDENT)->first())->FACULTY_IDENT;
			
			 $user = Auth::user();
             $bank_id = $user->bank_id;
			
			// dd($invoice);
			if(!is_null($invoice->STUDENT_IDENT) && !is_null($invoice->INVOICE_IDENT) && !is_null($student_faculty) && !is_null($bank_id)){
			$valudationResponce = (new FinancialApiController())->getInvoiceFinincialStatus($input['UNIV'], $invoice->STUDENT_IDENT,$student_faculty ,$invoice->INVOICE_IDENT, $bank_id);
		
			if(is_null($valudationResponce->key))
			{
				//dd('dd');
						return $valudationResponce;
			}
					else{
						return $valudationResponce->key;
					}
			}else{
			return "Some Vairable is Null"	;
			}
			
		}

}	


}