<?php
// enable extension=php_mcrypt.dll AND extension=php_soap.dll AND extension=php_openssl.dll on php.ini

date_default_timezone_set('Asia/Tehran');

$KEY = "Your KEY";
$IV = "Your IV";
$username = "Your username";
$password = "Your password";
$WebServiceUrl = "https://services.asanpardakht.net/paygate/merchantservices.asmx?WSDL";
$merchantConfigurationID = "YourConfigurationID";

function addpadding($string, $blocksize = 32)
{
    $len = strlen($string);
    $pad = $blocksize - ($len % $blocksize);
    $string .= str_repeat(chr($pad), $pad);
    return $string;
}

function strippadding($string)
{
    $slast = ord(substr($string, -1));
    $slastc = chr($slast);
    $pcheck = substr($string, -$slast);
    if(preg_match("/$slastc{".$slast."}/", $string)){
        $string = substr($string, 0, strlen($string)-$slast);
        return $string;
    } else {
        return false;
    }
}

function encrypt($string = "")
{
global $KEY,$IV;
	if (PHP_MAJOR_VERSION <= 5){
		$key = base64_decode($KEY);
		$iv = base64_decode($IV);
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, addpadding($string), MCRYPT_MODE_CBC, $iv));
	}
	else
		return EncryptWS($string);
}

function decrypt($string = "")
{
global $KEY,$IV;
	if (PHP_MAJOR_VERSION <= 5){
		$key = base64_decode($KEY);
		$iv = base64_decode($IV);
		$string = base64_decode($string);
		return strippadding(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, $iv));
	}
	else
		return DecryptWS($string);		
}

function EncryptWS($string = "")
{
global $KEY,$IV;
try {
	$opts = array(
		'ssl' => array('verify_peer'=>false, 'verify_peer_name'=>false)
	);
	$params = array ('stream_context' => stream_context_create($opts) );	
	$client = @new soapclient("https://services.asanpardakht.net/paygate/internalutils.asmx?WSDL", $params);
	} 
catch (SoapFault $E) 
	{
    echo "<div class=\"error\">{$E->faultstring}</div>"; 
	echo "<div class=\"error\">خطا در فراخوانی وب‌سرويس رمزنگاری.</div>";
	exit();
	}
$params = array(
	'aesKey' => $KEY,
	'aesVector' => $IV,
	'toBeEncrypted' => $string
);
$result = $client->EncryptInAES($params)
	or die("<div class=\"error\">خطای فراخوانی متد رمزنگاری.</div>");

return $result->EncryptInAESResult;	
}

function DecryptWS($string = "")
{
global $KEY,$IV;
try {
	$opts = array(
		'ssl' => array('verify_peer'=>false, 'verify_peer_name'=>false)
	);
	$params = array ('stream_context' => stream_context_create($opts) );	
	$client = @new soapclient("https://services.asanpardakht.net/paygate/internalutils.asmx?WSDL", $params);
	} 
catch (SoapFault $E) 
	{
    echo "<div class=\"error\">{$E->faultstring}</div>"; 
	echo "<div class=\"error\">خطا در فراخوانی وب‌سرويس رمزنگاری.</div>";
	exit();
	}
$params = array(
	'aesKey' => $KEY,
	'aesVector' => $IV,
	'toBeDecrypted' => $string
);
$result = $client->DecryptInAES($params)
	or die("<div class=\"error\">خطای فراخوانی متد رمزنگاری.</div>");

return $result->DecryptInAESResult;	
}
?>