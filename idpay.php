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

/*
Module Name: درگاه پرداخت آیدی پی
Description: امکان پرداخت آنلاین در کلیه بخش‌های اسکریپت از طریق درگاه پرداخت آیدی پی
Author: میلاد مالدار
Author URI: https://miladworkshop.ir
Version: 1.0.0
Requires at least: 2.3.*
*/

register_payment_gateway('idpay_gateway', 'idpay');