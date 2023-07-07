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

class idpay extends App_Controller
{
	public function idpay_decrypt($merchant, $string)
	{
		global $site;

		$output 		= false;
		$encrypt_method = "AES-256-CBC";
		$secret_key 	= "payment-{$merchant}-encrypt";
		$secret_iv 		= md5($secret_key);
		$key 			= hash('sha256', $secret_key);
		$iv 			= substr(hash('sha256', $secret_iv), 0, 16);

		$output 		= openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);

		return $output;
	}

	public function callback()
    {
		if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) == "POST")
		{
			$params = array(
				'id' 		=> $this->input->post('id'),
				'order_id' 	=> $this->input->post('order_id'),
			);
		} else {
			$params = array(
				'id' 		=> $this->input->get('id'),
				'order_id' 	=> $this->input->get('order_id'),
			);
		}
		
		$inv 		= $this->input->get('inv');
		$hash 		= $this->input->get('hash');
		$amount 	= $this->idpay_decrypt($his->decryptSetting('api_key'), $this->session->userdata('idpay_payment_key'));
		$sandbox 	= ($this->getSetting('test_mode_enabled') == 1) ? "1" : "0";
		
		check_invoice_restrictions($inv, $hash);

		$this->db->where('token', $params['id']);
        $this->db->where('id', $inv);
        $db_token = $this->db->get(db_prefix().'invoices')->row()->token;

		if ($db_token != $params['id'])
		{
            set_alert('danger', 'توکن پرداخت معتبر نیست');
            redirect(site_url("invoice/{$inv}/{$hash}"));
		} else {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment/verify');
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

			if (isset($result['status']) && $result['status'] == 100)
			{
				if (isset($result['amount']) && $result['amount'] == $amount)
				{
					echo "Success {$result['track_id']}";
					
					$success = $this->idpay_gateway->addPayment(
					[
						'amount'        => $amount,
						'invoiceid'     => $inv,
						'transactionid' => $params['id'],
					]);

					set_alert('success', 'پرداخت شما با موفقیت انجام و ثبت شد');

					redirect(site_url("invoice/{$inv}/{$hash}"));
					
				} else {
					set_alert('danger', "مبلغ تلراکنش یکسان نیست");
					log_activity("idpay Payment Error [ مبلغ تلراکنش یکسان نیست ]");
					redirect(site_url("invoice/{$inv}/{$hash}"));
				}
			} else {
				if (isset($result['status']) && $result['status'] == 101)
				{
					set_alert('warning', "این تراکنش قبلاً تایید شده است");
					redirect(site_url("invoice/{$inv}/{$hash}"));
				} else {
					$error_message = (isset($result['error_message']) && !empty($result['error_message'])) ? $result['error_message'] : "خطا در اتصال به وب سرویس";
					
					set_alert('danger', "خطا در اتصال به درگاه پرداخت<br /><div style='text-align:right; direction:rtl;'>{$error_message}</div>");
					log_activity("idpay Payment Error [ {$error_message} ]");
					redirect(site_url("invoice/{$inv}/{$hash}"));
				}
			}
		}
    }
}