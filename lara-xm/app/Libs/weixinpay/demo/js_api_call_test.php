<?php
/**
 * JS_API支付demo
 * ====================================================
 * 在微信浏览器里面打开H5网页中执行JS调起支付。接口输入输出数据格式为JSON。
 * 成功调起支付需要三个步骤：
 * 步骤1：网页授权获取用户openid
 * 步骤2：使用统一支付接口，获取prepay_id
 * 步骤3：使用jsapi调起支付
*/
	include_once("../WxPayPubHelper/WxPayPubHelper.php");

	// include_once("./db_mysql.php");
	
	//使用jsapi接口
	$jsApi = new JsApi_pub();

	// $db = new Database();


	// return;

	//=========步骤1：网页授权获取用户openid============
	//通过code获得openid
	if (!isset($_GET['code']))
	{
		//触发微信返回code码

		if(!isset($_GET['order_no'])){
			exit();
		}
		if(!isset($_GET['money'])){
			exit();
		}

		// 触发微信返回code码
		$url_str = 'http://naiba.icooder.com/weixinpay/demo/js_api_call_test.php?order_no='.$_GET['order_no'].'&money='.$_GET['money'];
		// $url_str = 'http://qunmengdev.icooder.com/weixinpay/demo/js_api_call.php?order_no='.$_GET['order_no'].'&type='.$_GET['type'];

		$url = $jsApi->createOauthUrlForCode(urlencode($url_str));
		// return print_r($url);
		Header("Location: $url"); 
		// return;

	}else
	{
		//获取code码，以获取openid
		$order_no = $_GET['order_no'];
	  	$money = $_GET['money'];

	  
	 //  	if($type == '1'){
	 //  		$sql  = "select money from order_rechange where order_sn='$order_no'";	//充值
	 //  		$money = $db->findvar($sql);
	 //  	}elseif($type == '2'){
	 //  		$sql  = "select money,freight from order_customer where order_sn='$order_no'";		//消费
	 //  		$con = $db->findrow($sql);
	 //  		$money = $con['money'];
	 //  		$money = $con['freight']+$money;
	 //  	}elseif($type == '3'){
	 //  		$sql  = "select money  from carsafe_order where order_sn='$order_no'";	
	 //  		$money = $db->findvar($sql);
	 //  		// $carsafe = $db->findrow($sql);
	 //  		// $money = $carsafe['business_insurer']+$carsafe['compulsory_insurance']+$carsafe['travel_insurance'];
	  		 
	 //  	}
	  	// print_r($sql);
	  	
		// $money = intval($money)*100;
		$money = bcmul($money, 100);
	    $code = $_GET['code'];

		$jsApi->setCode($code);
		$openid = $jsApi->getOpenId();

		$jsApi->setCode($code);
		
		// return;
	}	
	// return;
	//=========步骤2：使用统一支付接口，获取prepay_id============
	//使用统一支付接口
	$unifiedOrder = new UnifiedOrder_pub();
	
	//设置统一支付接口参数
	//设置必填参数
	//appid已填,商户无需重复填写
	//mch_id已填,商户无需重复填写
	//noncestr已填,商户无需重复填写
	//spbill_create_ip已填,商户无需重复填写
	//sign已填,商户无需重复填写
	$unifiedOrder->setParameter("openid",$openid);//商品描述
	$unifiedOrder->setParameter("body","17");//商品描述
	//自定义订单号，此处仅作举例
	// $timeStamp = time();
	// $out_trade_no = WxPayConf_pub::APPID."$timeStamp";
	$unifiedOrder->setParameter("out_trade_no",$order_no);//商户订单号 
	$unifiedOrder->setParameter("total_fee",$money);//总金额
	$unifiedOrder->setParameter("notify_url",WxPayConf_pub::NOTIFY_URL);//通知地址 
	// $unifiedOrder->setParameter("notify_url","http://naiba.icooder.com/weixinpay/demo/notify_url.php");//通知地址 

	
	$unifiedOrder->setParameter("trade_type","JSAPI");//交易类型
	//非必填参数，商户可根据实际情况选填
	//$unifiedOrder->setParameter("sub_mch_id","XXXX");//子商户号  
	//$unifiedOrder->setParameter("device_info","XXXX");//设备号 
	// $unifiedOrder->setParameter("attach",$money);//附加数据 
	//$unifiedOrder->setParameter("time_start","XXXX");//交易起始时间
	//$unifiedOrder->setParameter("time_expire","XXXX");//交易结束时间 
	//$unifiedOrder->setParameter("goods_tag","XXXX");//商品标记 
	//$unifiedOrder->setParameter("openid","XXXX");//用户标识
	//$unifiedOrder->setParameter("product_id","XXXX");//商品ID

	$prepay_id = $unifiedOrder->getPrepayId();
	// var_dump($prepay_id);
	// return ;
	

	//=========步骤3：使用jsapi调起支付============
	$jsApi->setPrepayId($prepay_id);

	$jsApiParameters = $jsApi->getParameters();

	// echo $jsApiParameters;
	
	
?>

<html>
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
    <title>微信安全支付</title>

	<script type="text/javascript">

		//调用微信JS api 支付
		function jsApiCall()
		{
			WeixinJSBridge.invoke(
				'getBrandWCPayRequest',
				<?php echo $jsApiParameters; ?>,
				function(res){
					msg_res = res.err_msg;
		           	// alert(msg);
		           	if(msg_res!=""){
						state = msg_res.split(":");
						// alert(rr[1]);
						if(state[1]=='ok'){
							// return;
							
							var r_sn = '<?php echo $order_no?>'; 
							var r_money = '<?php echo $money ?>';

							$.ajax({
						    	'url':'http://naiba.icooder.com/web/person/do_charge',
						    	'type':'post',
						    	'data':{
						    		'r_sn' : r_sn,
						    		'r_money' : r_money
						    	},
						    	'success':function(msg){
						    		//支付成功
						    		alert('支付成功');
						    		window.location.href="http://naiba.icooder.com/web/person/charge";
						    	}
									    	
						    	
						    });
							
						}
					}
		   //          if(msg.substr(-2) == "ok"){
		   //          	// location.href= "http://naiba.icooder.com/web/person/do_charge?r_sn="++'&r_money=';
		   //          }else{
		   //          	alert('支付失败!');
		   //          }
					// WeixinJSBridge.log(res.err_msg);
					// alert(res.err_code+res.err_desc+res.err_msg);
				}
			);
		}

		function callpay()
		{
			if (typeof WeixinJSBridge == "undefined"){
			    if( document.addEventListener ){
			        document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
			    }else if (document.attachEvent){
			        document.attachEvent('WeixinJSBridgeReady', jsApiCall); 
			        document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
			    }
			}else{
			    jsApiCall();
			}
		}

		callpay();
	</script>
</head>
<body>

</body>
</html>