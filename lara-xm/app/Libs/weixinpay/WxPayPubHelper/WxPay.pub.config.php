<?php
/**
* 	配置账号信息
*/

class WxPayConf_pub
{
	//=======【基本信息设置】=====================================

	//微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
	const APPID = 'wxd0adda88618bd5f5';

	//受理商ID，身份标识
	const MCHID = '1537803441';

	//商户支付密钥Key。审核通过后，在微信发送的邮件中查看
	const KEY = 'CHUANFENGWULIAN12345huodanhuodan';

	//JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
	const APPSECRET = 'cbcfa0e1c529d826e5dcc3bf3fafbe1a';

	// const APPSECRET = '';
	
	//=======【JSAPI路径设置】===================================
	//获取access_token过程中的跳转uri，通过跳转将code传入jsapi支付页面
//	const JS_API_CALL_URL = 'https://www.lezhunet.com/weixinpay/demo/js_api_call.php';

	//=======【证书路径设置】=====================================
	//证书路径,注意应该填写绝对路径
	//证书下载地址
	// const SSLCERT_PATH = 'http://weygoo.icooder.appcom/weixinpay/WxPayPubHelper/cacert/apiclient_cert.pem';
	// const SSLKEY_PATH = '/weixinpay/WxPayPubHelper/cacert/apiclient_key.pem';
	// const CAINFO_PATH = '/weixinpay/WxPayPubHelper/cacert/rootca.pem';
	const SSLCERT_PATH = '/var/www/projects/ayl2/weixinpay/WxPayPubHelper/cacert/apiclient_cert.pem';
	const SSLKEY_PATH = '/var/www/projects/ayl2/WxPayPubHelper/cacert/apiclient_key.pem';
	const CAINFO_PATH = '/var/www/projects/ayl2/WxPayPubHelper/cacert/rootca.pem';
	
	//=======【异步通知url设置】===================================
	//异步通知url，商户根据实际开发过程设定
//	const NOTIFY_URL = url('/callback/wxpayNotify');
//	'http://tool.aiyaole.cn/weixinpay/demo/app_notify_url.php';


	//=======【curl超时设置】===================================
	//本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
	const CURL_TIMEOUT = 30;
}
	
?>