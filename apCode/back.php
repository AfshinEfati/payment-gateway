<?php
require_once('ipgcfg.php');

$ReturningParams = $_POST['ReturningParams'];
$ReturningParams = decrypt($ReturningParams);

$RetArr = explode(",", $ReturningParams);
$Amount = $RetArr[0];
$SaleOrderId = $RetArr[1];
$RefId = $RetArr[2];
$ResCode = $RetArr[3];
$ResMessage = $RetArr[4];
$PayGateTranID = $RetArr[5];
$RRN = $RetArr[6];
$LastFourDigitOfPAN = $RetArr[7];

if ($ResCode != '0' && $ResCode != '00'){
echo 'تراکنش ناموفق<br>خطای شماره: '.$ResCode;
exit();
}

try {
	$opts = array('ssl' => array('verify_peer'=>false, 'verify_peer_name'=>false));
	$params = array ('stream_context' => stream_context_create($opts));	
	$client = @new soapclient($WebServiceUrl,$params);
	} 
catch (SoapFault $E) 
	{  
    // echo $E->faultstring; 
	echo "خطا در فراخوانی وب‌سرويس.";
	exit();
	}

$encryptedCredintials = encrypt("{$username},{$password}");
$params = array(
	'merchantConfigurationID' => $merchantConfigurationID,
	'encryptedCredentials' => $encryptedCredintials,
	'payGateTranID' => $PayGateTranID
);

//Verify
$result = $client->RequestVerification($params)
	or die("خطای فراخوانی متد وريفای.");
$result = $result->RequestVerificationResult;
	if ($result != '500'){
		echo('خطای شماره: '. $result . ' در هنگام Verify');
		exit();
	}
	else{
		echo('<div style="width:250px; margin:100px auto; direction:rtl; font:bold 14px Tahoma">تراکنش با موفقيت Verify شد.</div>');
	}

//Settlment	
$result = $client->RequestReconciliation($params)
	or die("خطای فراخوانی متد تسويه.");

$result = $result->RequestReconciliationResult;
	if ($result != '600'){
		echo('خطای شماره: '. $result . ' در هنگام Settlement');
		exit();
	}
	else{
		echo('<div style="width:250px; margin:100px auto; direction:rtl; font:bold 14px Tahoma">تراکنش با موفقيت Settlement شد.</div>');
	}	

echo('<div style="width:250px; margin:100px auto; direction:rtl; font:bold 14px Tahoma">تراکنش با موفقيت انجام پذيرفت.</div>');
?>