<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use GuzzleHttp\Client;

class GraduateStudiesPortalController extends Controller
{
    /**
     * Retrieve invoice data based on the provided parameters.
     *
     * @param int $id The invoice ID.
     * @param int $bank_id The bank ID.
     * @param int $user_id The user ID.
     * @param string $connectionDriver The database connection driver.
     * @return mixed
     */
      protected $url;
    protected $username;
    protected $password;
    protected $prifex;

    public function initCurlRequest($url, $postFields, $headers)
    {
       
      \Log::error('in initCurlRequest::'.$postFields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     
        $body = curl_exec($ch);

        

        return $body;
    }
    public function __construct()
    {
        // Example values, replace with your actual credentials or use env() for security
       $this->url = 'https://pg.oasyemen.net/api';

        $this->username = 'finincialUser@gmail.com';
        $this->password = 'finincial#9865#321';

          $this->prifex='10001';
        
    }

    public  function payInvoice($id, $bank_id, $input)
    {
        $token = $this->getToken();
        if (!$token) {
            \Log::error('Failed to retrieve token for GraduateStudiesPortalController');
            return ['error' => '#203', 'message' => 'غير مخول بسداد لهذه الجامعة'];
        }
        $url = $this->url . '/financial-invoices/process-payment';
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $postFields = http_build_query([
            'id' => $id,
            'bonds_id' => $input['BONDS_ID'],
            'bonds_date' =>date('Y-m-d', strtotime($input['BONDS_DATE'])),// $input['BONDS_DATE'],
            'payment_by' => $input['PAYMENT_BY'],
            'fee_amount' => $input['PAYMENT'],
            'bank_id' => $bank_id,
        ]);
      \Log::error('in GraduateStudiesPortalController'. json_encode($postFields));
        $response = $this->initCurlRequest($url, $postFields, $headers);
     
        $data = json_decode($response, true);
        \Log::error('in GraduateStudiesPortalController'.$response);
        return $data;
    }
    public  function getInvoiceData($id, $bank_id,$type = 0)
    
    {
           try {
        $token = $this->getToken(); 
       \Log::info('getInvoiceData'.$token);
        if (!$token) {
            \Log::error('Failed to retrieve token for GraduateStudiesPortalController');
             return $this->sendError('#203', " غير مخول بسداد لهذه الجامعة");
         
        }
      
     
            if($type == 0) {
                $requestUrl = $this->url . '/financial-invoices/getInvoice';
            } else {
                $requestUrl = $this->url . '/financial-invoices/getInvoiceDetail';
            }
             $url = $requestUrl;
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $postFields = http_build_query([
            'id' => (int)$id,
            'bank_id' => (int)$bank_id,
        ]);
             $response = $this->initCurlRequest($url, $postFields, $headers);
            
           
               $data = json_decode($response, true);
          

             if(!$data['success'])
                return $data;
                           if($type==1){
                foreach ($data["data"] as &$item) {
                        $item["invoice_id"] = "10001" . $item["invoice_id"];
                    }

                    unset($item); // break reference
               }else{
                    $data['data']['invoice_id']=$this->prifex.$data['data']['invoice_id'];
               }
            return $data;
        } catch (\Exception $e) {
            \Log::error('Error getInvoiceData in GraduateStudiesPortalController'.$e->getMessage());
            return response()->json(['error' => 'Request failed'], 500);
        }
    }

    /**
     * Perform login and store token in cache
     * @return string|null
     */
    public  function login()
    {
       
        try {
           
            $url = $this->url . '/login';
            $headers = [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ];
        $postFields = http_build_query([
             'email' => $this->username,
                    'password' => $this->password,
        ]);
         \Log::error( $url);
           $response = $this->initCurlRequest($url, $postFields, $headers);

            $data = json_decode($response, true);
           \Log::error('Graduate Student Portal Login to system '.  json_encode($data));
            if (isset($data['access_token'])) {
                // Store token in cache for 55 minutes
                Cache::put('api_token', $data['access_token'], Carbon::now()->addMinutes(55));
                return $data['access_token'];
            }
        } catch (\Exception $e) {
            // Handle exception
        }
        return null;
    }

    /**
     * Get token from cache or login if expired
     * @return string|null
     */
    public  function getToken()
    {
        try{
       \Log::info('--------------');
        $token = Cache::get('api_token');
        \Log::error('GraduateStudiesPortalController token'. $token );
        if (!$token) {
            $token = $this->login();
        }
        return $token;
    }catch(\Exception $e){
        \Log::error('Exception in getToken function '.$e->getMessage());
    }
    }
}