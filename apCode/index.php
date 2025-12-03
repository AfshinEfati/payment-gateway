<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php
/*
نمونه کد اتصال به درگاه اينترنتی آپ
زبان: PHP
نسخه برنامه 3.0
تاريخ آخرين بروزرسانی : 19 اسفند 1394
*/
require_once('ipgcfg.php');
?>
<html>
<head>
<meta http-equiv="Content-Language" content="fa">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>.: آسان پرداخت پرشين(آپ) | صفحه تست درگاه اينترنتی :.</title>
<style type="text/css">
fieldset {
float:right;
width: 280px;
border: 1px solid #ddd;
font: bold 14px Tahoma;
background:#fff;
padding: 20px;
margin: 50px 10px 0 0;
text-align:center;
}
legend {
	border: 1px solid #ccc;
	background: #f5f5f5;
	color: #444;
	direction:rtl;
	font: bold 14px Tahoma;
	padding: 4px 8px 5px;
}
.message {
	clear:both;
	text-align:center;
	direction:rtl;
	margin-top:30px;
	font:bold 14px tahoma;
	color:#090;
}
.error {
	clear:both;
	text-align:center;
	direction:rtl;
	margin-top:30px;
	font:bold 14px tahoma;
	color:#c00;
}
</style>
<script language="javascript" type="text/javascript">    
		function RedirctToIPG(RefId) {
			var form = document.createElement("form");
			form.setAttribute("method", "POST");
			form.setAttribute("action", "https://asan.shaparak.ir/");         
			form.setAttribute("target", "_blank");
			var hiddenField = document.createElement("input");              
			hiddenField.setAttribute("name", "RefId");
			hiddenField.setAttribute("value", RefId);
			form.appendChild(hiddenField);
			document.body.appendChild(form);         
			form.submit();
			document.body.removeChild(form);
		}
</script>
</head>
<body>
<div style="width:1000px; margin:10px auto; background:#ff0">
<?php
	echo '<fieldset>
		<legend>1. کنترل ماژولهای مورد نياز</legend>';
	echo "<div style=\"margin-top:10px; direction:rtl\">آيا ماژولهای زير در PHP شما نصب هستند؟</div>";
	echo "<div style=\"margin-top:10px; direction:ltr\"> soap : ".((extension_loaded('soap'))? '<span style="color:#090"> نصب است</span>':'<span style="color:#c00"> نصب نيست</span>')."</div>";
	echo "<div style=\"margin-top:10px; direction:ltr\"> mcrypt : ".((extension_loaded('mcrypt'))? '<span style="color:#090"> نصب است</span>':'<span style="color:#c00"> نصب نيست</span>')."</div>";
	echo "<div style=\"margin-top:10px; direction:ltr\"> openssl : ".((extension_loaded('openssl'))? '<span style="color:#090"> نصب است</span>':'<span style="color:#c00"> نصب نيست</span>')."</div>";	
	echo '</fieldset>';

	echo '<fieldset>
		<legend>2. کنترل IP</legend>';
	echo "<div style=\"margin:10px 0; direction:rtl\">آيا IP خود را درست اعلام کرده‌ايد؟</div>";
	echo '
		<form name="form2" method="POST">
				<div style="text-align:center; direction:ltr; font:bold 14px/28px tahoma; direction:rtl">
				<input type="submit" name="PSCheckIP" value="Test IP" style="padding:8px 30px; font:bold 14px Tahoma; color:#fff; background:#aaa; border:1px solid #aaa"/>
		</form>	
	';
	echo '</fieldset>';
?>
<fieldset>
		<legend>3. انجام تراکنش</legend>
		<form name="form1" method="POST">
			<div style="text-align:center; direction:ltr; font:bold 14px/28px tahoma; direction:rtl">
			مبلغ تراکنش (به ريال):
			<input type="text" value="2000" name="price" style="width:200px; padding:8px 0; margin:5px 0 10px; font:bold 14px Tahoma; direction:ltr; border:1px solid #aaa">
			<input type="submit" name="PSPayRequestSubmit" value="پرداخت" style="padding:8px 30px; font:bold 14px Tahoma; color:#fff; background:#aaa; border:1px solid #aaa"/>
		</form>		
</fieldset>
</div>
</body>
<?php
//●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●● انجام تراکنش ●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●
if (isset($_POST['PSPayRequestSubmit'])){
$orderId = rand();
$price = $_POST['price'];
$localDate = date("Ymd His");
$additionalData = "";
$callBackUrl = "http://youraddress";
$req = "1,{$username},{$password},{$orderId},{$price},{$localDate},{$additionalData},{$callBackUrl},0";
		//اگر قصد واريز پول به چند شبا را داريد، خط زير را به رشته بالايی اضافه کنيد
		// ,Shaba1,Mablagh1,Shaba2,Mablagh2,Shaba3,Mablagh3
		//حداکثر تا 7 شبا می‌توانيد به رشته خود اضافه کنيد
$encryptedRequest = encrypt($req);

try {
	$opts = array('ssl' => array('verify_peer'=>false, 'verify_peer_name'=>false));
	$params = array ('stream_context' => stream_context_create($opts));	
	$client = @new soapclient($WebServiceUrl,$params);
	} 
catch (SoapFault $E) 
	{  
    // echo "<div class=\"error\">{$E->faultstring}</div>"; 
	echo "<div class=\"error\">خطا در فراخوانی وب‌سرويس.</div>";
	exit();
	}	
$params = array(
	'merchantConfigurationID' => $merchantConfigurationID,
	'encryptedRequest' => $encryptedRequest
);
$result = $client->RequestOperation($params)
	or die("<div class=\"error\">خطای فراخوانی متد درخواست تراکنش.</div>");

$result = $result->RequestOperationResult;
	if ($result{0} == '0'){
		echo "<script language='javascript' type='text/javascript'>RedirctToIPG('" . substr($result,2) . "');</script>";
	}
	else{
		echo "<div class=\"error\">خطای شماره: {$result}</div>";
	}
}

//●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●● Test IP ●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●
if (isset($_POST['PSCheckIP'])) 
{ 
try {
	$client = @new soapclient("https://services.asanpardakht.net/utils/hostinfo.asmx?WSDL");
	} 
catch (SoapFault $E) 
	{  
    // echo "<div class=\"error\">{$E->faultstring}</div>"; 
	echo "<div class=\"error\">خطا در فراخوانی وب‌سرويس.</div>";
	exit();
	}
$result = $client->GetHostInfo()
	or die("<div class=\"message\">خطای فراخوانی متد درخواست تراکنش.</div>");

$result = $result->GetHostInfoResult;
echo "<div class=\"message\">{$result}</div>";
}
?>			
</html>