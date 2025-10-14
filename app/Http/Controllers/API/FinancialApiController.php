<?php

namespace App\Http\Controllers\API;

use App\Models\FinancialApi;
use App\Models\ApiFininsalToken;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FinancialApiController extends Controller
{
	protected $univ = '';

	protected $user_name = 'api_fin_pay';
	protected $user_password = 'Qfu6TGBk1zNe.rqKb';
	protected $hash_key = '';
	protected $token = '';
	protected $route = 'fa_login';
	protected $message = '';
    protected $url = 'http://sar.localhost/financial/api/route.php';
    //protected $url = 'https://sar.ycithe.net/financial-v3/api/route.php';


	private function getTokenFromJson($univ_id)
	{
		$filePath = storage_path('app/token.json');
		if (file_exists($filePath)) {
			$jsonContent = file_get_contents($filePath);

			$data = json_decode($jsonContent, true);
			return $data['token_' . $univ_id] ?? null;
		}
		return null;
	}
	private function getHashFromJson($univ_id)
	{
		$filePath = storage_path('app/hash.json');
		if (file_exists($filePath)) {
			$jsonContent = file_get_contents($filePath);

			$data = json_decode($jsonContent, true);
			return $data['hash_' . $univ_id] ?? null;
		}
		return null;
	}

	private function storeTokenInJson($token, $univ_id)
	{
		$filePath = storage_path('app/token.json');

		// Check if the file exists
		if (file_exists($filePath)) {
			$jsonContent = file_get_contents($filePath);
			$data = json_decode($jsonContent, true);
		} else {
			$data = [];
		}

		// Update the token value
		$data['token_' . $univ_id] = $token;

		// Write the updated data to the file
		file_put_contents($filePath, json_encode($data));
	}
	private function storeHashInJson($hash, $univ_id)
	{
		$filePath = storage_path('app/hash.json');

		// Check if the file exists
		if (file_exists($filePath)) {
			$jsonContent = file_get_contents($filePath);
			$data = json_decode($jsonContent, true);
		} else {
			$data = [];
		}


		// Update the token value
		$data['hash_' . $univ_id] = $hash;
		log::info('------------>>'.json_encode($data));
		// Write the updated data to the file
		file_put_contents($filePath, json_encode($data));
	}


	public function getInvoiceFinincialStatus($univ_id, $student_ident, $faculty_id, $_invoice_id, $_bank_id, $do_payment)
	{

		$this->univ = $univ_id;
	
		$hash_key = $this->getHashFromJson($univ_id);
		
		$token = $this->getTokenFromJson($univ_id); // Read token from JSON file
		Log::info("Inside getInvoiceFinincialStatus - toke ({$token})");
		Log::info("Inside getInvoiceFinincialStatus - invoiceid ({$_invoice_id})");
		
		if (!$token) {
			$this->login();
			//$hash_key =$this->getHashFromJson($univ_id);
			$token = $this->getTokenFromJson($univ_id); // Retry reading token after login
		
			if (!$token) {
				return $this->sendError('#401', 'Unable to authenticate');
			}
		}

		$route = 'check_333'; // Route to check the status
		$sending_parameter = [
			'_token'  => $token,
			'student' => $student_ident,
			'faculty' => $faculty_id,
			'invoice' => $_invoice_id,
			'bank'    => $_bank_id,
		];

		$sending_value = $this->api_encrypt_values(http_build_query($sending_parameter), $hash_key);
		$params = [
			'_univ'      => $this->univ,
			'_logname'   => $this->user_name,
			'_route'     => $route,
			'_code'      => $sending_value,
			'_do_payment' => $do_payment
		];
		$count = 0;
		while (true) {
			if ($count==10) {
				Log::info("Max retries reached");
				break;
			}
			Log::info("Token in while number:({$count})=> ({$token})");
			$response = json_decode($this->initCurlRequest($this->url, $params));
			Log::info("responce from api ({" . json_encode($response) . "})");
			Log::info(" -- url:" . json_encode($this->url) . 'parameters :' . json_encode($params));
			Log::info(" -- parameter befor encrept:" . json_encode($sending_parameter) . 'hASH :' . json_encode($hash_key));

			if ($response) {
				$httpcode = $response->code;
				if ($httpcode == 440 || $httpcode == 400 || $httpcode == 401) {
					$this->login();
					//$hash_key =$this->getHashFromJson($univ_id);
					$token = $this->getTokenFromJson($univ_id); // Retry reading token after login
					if (!$token) {
						return $this->sendError('#401', 'Unable to authenticate');
					}
					$sending_parameter['_token'] = $token;
					$sending_value = $this->api_encrypt_values(http_build_query($sending_parameter), $hash_key);
					$params['_code'] = $sending_value;
					$count++;
					continue; // Retry after re-login
				}
				Log::info("getInvoiceFinincialStatus - response received - invoiceid ({$_invoice_id})");
				return $response->message;
			}

			$this->login();
			//$hash_key =$this->getHashFromJson($univ_id);
			$token = $this->getTokenFromJson($univ_id); // Retry reading token after login
			if (!$token) {
				return $this->sendError('#401', 'Unable to authenticate');
			}
			Log::info("hello - invoiceid ({$_invoice_id})");
			$sending_parameter['_token'] = $token;
			$sending_value = $this->api_encrypt_values(http_build_query($sending_parameter), $hash_key);
			$params['_code'] = $sending_value;
		}
	}



	public function login()
	{
		$api = FinancialApi::on('mysql1')->where('login_name', $this->user_name)->first();

		if (!$api) {
			Log::error("API credentials not found for user: {$this->user_name}");
			return false;
		}

		$this->hash_key=$api->hash_key;
		$this->storeHashInJson($api->hash_key, $this->univ);
		$encoded_value = ['_logpass' => base64_encode($this->user_password)];
		$sending_value = $this->api_encrypt_values(http_build_query($encoded_value), $this->hash_key);

		$params = [
			'_univ'    => $this->univ,
			'_logname' => $this->user_name,
			'_route'   => $this->route,
			'_code'    => $sending_value
		];

		$response = json_decode($this->initCurlRequest($this->url, $params));
	
		if (!$response) {
			Log::error("No response from API during login for user: {$this->user_name}");
			return false;
		}

		$response_code = $response->code;
		if ($response_code == 200 && isset($response->token)) {
			$this->token = $response->token;
			$this->storeTokenInJson($this->token, $this->univ); // Store token in JSON file
			return true;
		} elseif ($response_code == 440) {
			Log::warning("Token expired, retrying login for user: {$this->user_name}-{$this->univ}");
			return $this->login(); // Retry login on token expiration
		} else {
			$this->message = $response->message;
			Log::error("Login failed for user: {$this->user_name}-{$this->univ} with message: {$this->message}");
			return false;
		}
	}



	function saveToApiFininsialToken($bank_id, $token)
	{

		$finincialApi = new ApiFininsalToken();
		$finincialApi->setConnection('mysql1');
		$finincialApi->bank_id = $bank_id;
		$finincialApi->token = $token;
		$finincialApi->save();
	}
	function initCurlRequest($reqURL, $headers = array())
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $reqURL);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $headers);
		curl_setopt($ch, CURLOPT_HEADER, true);

		$body = curl_exec($ch);

		//$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// extract header
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		// extract body
		$body = substr($body, $headerSize);

		curl_close($ch);

		return $body;
	}





	function api_encrypt_values($string, $secretSign)
	{
		$encrypt_method = "AES-256-CBC";
		$secret_key = substr($secretSign, -3) . $secretSign;
		$secret_iv = strrev($secretSign . substr($secretSign, -3));

		// hash
		$key = hash('sha256', $secret_key);

		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		$value = strrev($secretSign . $string);
		$output = openssl_encrypt($value, $encrypt_method, $key, 0, $iv);
		$output = base64_encode($output);
		$output = str_replace('=', '', $output);

		return $output;
	}
}
