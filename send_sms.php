
<?php
$data = [
  "enable_unicode" => true,
  "messages" => [
    [
      "to" => "0438823698",
      "message" => "Hello, this is a test message",
      "sender" => "61438823698",
      "custom_ref" => "tracking001"
    ]
  ]
];

$ch = curl_init('https://api.mobilemessage.com.au/v1/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "VEsQVd:n6JYNNCYl5ogLXZcCPE3oo6XJzuM5VLsZPzYUVLcOba");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>


<!-- VEsQVd
n6JYNNCYl5ogLXZcCPE3oo6XJzuM5VLsZPzYUVLcOba -->

<!-- 
https://api.mobilemessage.com.au/simple/send-sms?api_username=VEsQVd&api_password=n6JYNNCYl5ogLXZcCPE3oo6XJzuM5VLsZPzYUVLcOba&sender=61438823698&to=61438823698&message=Hello+there&custom_ref=OptionalRef123 
-->
