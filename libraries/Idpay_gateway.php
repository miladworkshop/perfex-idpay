<?php
/*
 * 	Perfex CRM IDPAY Gateway
 * 	
 * 	Link 	: https://github.com/miladworkshop/perfex-idpay
 * 	
 * 	Author 	: Milad Maldar
 * 	E-mail 	: info@miladworkshop
 * 	Website : https://miladworkshop.ir
*/

defined('BASEPATH') or exit('No direct script access allowed');

class idpay_gateway extends App_gateway
{
    public function __construct()
    {
        /**
         * Call App_gateway __construct function
         */
        parent::__construct();
        /**
         * REQUIRED
         * Gateway unique id
         * The ID must be alpha/alphanumeric
         */
        $this->setId('idpay');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('آیدی پی');

        /**
         * Add gateway settings
        */
        $this->setSettings([
            [
                'name'      	=> 'api_key',
                'encrypted' 	=> true,
                'label'     	=> 'کلید API',
			],
			[
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'شناسه پرداخت {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'IRT,IRR',
			],
            [
                'name'          => 'test_mode_enabled',
                'type'          => 'yes_no',
                'default_value' => 0,
                'label'         => 'پرداخت آزمایشی',
            ],
		]);
    }

	public function idpay_encrypt($merchant, $string)
	{
		global $site;

		$output 		= false;
		$encrypt_method = "AES-256-CBC";
		$secret_key 	= "payment-{$merchant}-encrypt";
		$secret_iv 		= md5($secret_key);
		$key 			= hash('sha256', $secret_key);
		$iv 			= substr(hash('sha256', $secret_iv), 0, 16);

		$output 		= openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
		$output 		= base64_encode($output);

		return $output;
	}

    /**
     * REQUIRED FUNCTION
     * @param  array $data
     * @return mixed
     */
    public function process_payment($data)
    {
		$amount 	= preg_replace('~\.0+$~','', $data['amount']);
		$amount 	= (isset($data['invoice']->currency_name) && strtoupper($data['invoice']->currency_name) == "IRT") ? $amount * 10 : $amount;

		$sandbox 	= ($this->getSetting('test_mode_enabled') == 1) ? "1" : "0";

		$params = array(
			'order_id' 	=> $data['invoiceid'],
			'amount' 	=> $amount,
			'name' 		=> '',
			'phone' 	=> '',
			'mail' 		=> '',
			'desc' 		=> '',
			'callback' 	=> urlencode(site_url("idpay/callback?hash={$data['invoice']->hash}&inv={$data['invoiceid']}")),
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-API-KEY: {$this->decryptSetting('api_key')}",
			"X-SANDBOX: {$sandbox}"
		));

		$result = curl_exec($ch);
		$result = json_decode($result, true);

		curl_close($ch);

		if (isset($result['link']) && !empty($result['link']))
		{
			// Save Session
			$this->ci->session->set_userdata([
				'idpay_payment_key' => $this->idpay_encrypt($this->decryptSetting('api_key'), $amount),
			]);

			// Add the token to database
			$this->ci->db->where('id', $data['invoiceid']);
			$this->ci->db->update(db_prefix().'invoices', [
				'token' => $result['id'],
			]);

			redirect($result['link']);
		} else {
			$error_message = (isset($result['error_message']) && !empty($result['error_message'])) ? $result['error_message'] : "خطا در اتصال به وب سرویس";
			
			set_alert('danger', "خطا در اتصال به درگاه پرداخت<br /><div style='text-align:right; direction:rtl;'>{$error_message}</div>");
			log_activity("idpay Payment Error [ {$error_message} ]");
			redirect(site_url('invoice/' . $data['invoiceid'] . '/' . $data['invoice']->hash));
		}
    }
}