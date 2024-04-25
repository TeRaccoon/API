<?php

require_once 'cors_config.php';

$request_host = "api.smartpayfuse-test.barclaycard";
$merchant_id = "Hellenicgrocery_UK";
$merchant_key_id = '0c3607e7-1765-488f-ac9b-c448f1aa4440';
$merchant_secret_key = 'bXeC2kwurQtAP5y6Uf8pkaLnOKiSuTUaDj81zu1jQ+U=';

$input_data = file_get_contents("php://input");
$payload = json_encode(json_decode($input_data, true));

// $payload = '{
//     "clientReferenceInformation": {
//       "code": "TC50171_3"
//     },
//     "processingInformation": {
//       "capture": true
//     },
//     "paymentInformation": {
//       "card": {
//         "number": "4622943127013705",
//         "expirationMonth": "01",
//         "expirationYear": "2031",
//         "securityCode": "838"
//       }
//     },
//     "orderInformation": {
//       "amountDetails": {
//         "totalAmount": "102.21",
//         "currency": "GBP"
//       },
//       "billTo": {
//         "firstName": "John",
//         "lastName": "Doe",
//         "address1": "1 The Street",
//         "locality": "City",
//         "administrativeArea": "County",
//         "postalCode": "AB12CD",
//         "country": "GB",
//         "email": "jack@valheru.uk",
//         "phoneNumber": "07000000000"
//       }
//     }
//   }';

ProcessPost();
function httpParseHeaders($raw_headers)
{
    $headers = [];
    $key = '';
    foreach (explode("\n", $raw_headers) as $h) {
        $h = explode(':', $h, 2);
        if (isset($h[1])) {
            if (!isset($headers[$h[0]])) {
                $headers[$h[0]] = trim($h[1]);
            } elseif (is_array($headers[$h[0]])) {
                $headers[$h[0]] = array_merge($headers[$h[0]], [trim($h[1])]);
            } else {
                $headers[$h[0]] = array_merge([$headers[$h[0]]], [trim($h[1])]);
            }
            $key = $h[0];
        } else {
            if (substr($h[0], 0, 1) === "\t") {
                $headers[$key] .= "\r\n\t".trim($h[0]);
            } elseif (!$key) {
                $headers[0] = trim($h[0]);
            }
            trim($h[0]);
        }
    }
    return $headers;
}
function GenerateDigest($requestPayload)
{
    $utf8EncodedString = utf8_encode($requestPayload);
    $digestEncode = hash("sha256", $utf8EncodedString, true);
    return base64_encode($digestEncode);
}
function GetHttpSignature($resourcePath, $httpMethod, $currentDate)
{
    global $payload;
    global $merchant_id;
    global $request_host;
    global $merchant_secret_key;
    global $merchant_key_id;

    $digest = "";

    if($httpMethod == "get")
    {
        $signatureString = "host: " . $request_host . "\ndate: " . $currentDate . "\nrequest-target: " . $httpMethod . " " . $resourcePath . "\nv-c-merchant-id: " . $merchant_id;
        $headerString = "host date request-target v-c-merchant-id";

    }
    else if($httpMethod == "post")
    {
        //Get digest data
        $digest = GenerateDigest($payload);

        $signatureString = "host: " . $request_host . "\ndate: " . $currentDate . "\nrequest-target: " . $httpMethod . " " . $resourcePath . "\ndigest: SHA-256=" . $digest . "\nv-c-merchant-id: " . $merchant_id;
        $headerString = "host date request-target digest v-c-merchant-id";
    }

    $signatureByteString = utf8_encode($signatureString);
    $decodeKey = base64_decode($merchant_secret_key);
    $signature = base64_encode(hash_hmac("sha256", $signatureByteString, $decodeKey, true));
    $signatureHeader = array(
        'keyid="' . $merchant_key_id . '"',
        'algorithm="HmacSHA256"',
        'headers="' . $headerString . '"',
        'signature="' . $signature . '"'
    );

    $signatureToken = "Signature:" . implode(", ", $signatureHeader);

    $host = "Host:" . $request_host;
    $vcMerchant = "v-c-merchant-id:" . $merchant_id;
    $headers = array(
        $vcMerchant,
        $signatureToken,
        $host,
        'Date:' . $currentDate
    );

    if($httpMethod == "post"){
        $digestArray = array("Digest: SHA-256=" . $digest);
        $headers = array_merge($headers, $digestArray);
    }

    return $headers;
}

function ProcessPost()
{
    global $payload;
    global $request_host;
    global $merchant_id;

    $resource = "/pts/v2/payments/";
    $method = "post";
    $statusCode = -1;
    $url = "https://" . $request_host . $resource;

    $resource = utf8_encode($resource);

    $date = date("D, d M Y G:i:s ") . "GMT";

    $signatureString ="";

    $headerParams = [];
    $headers = [];

    $headerParams['Accept'] = 'application/hal+json;charset=utf-8';
    $headerParams['Content-Type'] = 'application/json';

    foreach ($headerParams as $key => $val) {
        $headers[] = "$key: $val";
    }

    $authHeaders = GetHttpSignature($resource, $method, $date);
    $headerParams = array_merge($headers, $authHeaders);

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headerParams);

    curl_setopt($curl, CURLOPT_CAINFO, __DIR__. DIRECTORY_SEPARATOR . '../Resources/cacert.pem');

    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);

    curl_setopt($curl, CURLOPT_URL, $url);

    curl_setopt($curl, CURLOPT_HEADER, 1);

    curl_setopt($curl, CURLOPT_VERBOSE, 0);

    $response = curl_exec($curl);
    $http_header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $http_header = httpParseHeaders(substr($response, 0, $http_header_size));
    $http_body = substr($response, $http_header_size);
    $response_info = curl_getinfo($curl);
    if ($response_info['http_code'] >= 200 && $response_info['http_code'] <= 299)
    {
        $statusCode = 0;
        $data = json_decode($http_body);
        if (json_last_error() > 0)
        {
            $data = $http_body;
        }
    }
    

    echo json_encode(array("status_code" => $response_info['http_code'], "http_headers" => $http_header));
}