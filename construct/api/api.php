<?php
global $wpdb;
$redis = new Redis();
$redis -> connect('localhost', 6379);
$tmp = $redis -> get('ips');
$extra_accessedIPs = isset($tmp) ? json_decode($tmp, true) : [];
$redis -> close();

$MAX_TRY = 2;
$COOL_DOWN = 300; // seconds
// to debug it in small time span, modify it to 30.

function submitSync(){
    $GLOBALS["redis"] -> connect('localhost', 6379);
    $GLOBALS["redis"] -> set('ips', json_encode($GLOBALS["extra_accessedIPs"]));
    $GLOBALS["extra_accessedIPs"] = json_decode(($GLOBALS["redis"]) -> get('ips'), true);
    $GLOBALS["redis"] -> close();
}

function pushIpAccessedStatus($ipaddr){
    $now = time();
    $maxTries = $GLOBALS['MAX_TRY'];
    $interval = $GLOBALS['COOL_DOWN']; 
    if(!isset($GLOBALS['extra_accessedIPs'][$ipaddr])){
        $GLOBALS['extra_accessedIPs'][$ipaddr] = [
            "ip" => $ipaddr,
            "stamp" => $now,
            "trys" => 1
        ];
        submitSync();
        return 0;
    }
    else {
        if (((int)$GLOBALS['extra_accessedIPs'][$ipaddr]["trys"] >= $maxTries) && (($now - ((int)$GLOBALS['extra_accessedIPs'][$ipaddr]["stamp"])) > $interval)){
            $GLOBALS['extra_accessedIPs'][$ipaddr]["trys"] = 1;
            (int)$GLOBALS['extra_accessedIPs'][$ipaddr]["stamp"] = $now;
            submitSync();
            return 0;
        }
        elseif (((int)$GLOBALS['extra_accessedIPs'][$ipaddr]["trys"] >= $maxTries) && (($now - ((int)$GLOBALS['extra_accessedIPs'][$ipaddr]["stamp"])) <= $interval)) return 1;
        else {
            $GLOBALS['extra_accessedIPs'][$ipaddr]["trys"] = (int)$GLOBALS['extra_accessedIPs'][$ipaddr]["trys"] + 1;
            (int)$GLOBALS['extra_accessedIPs'][$ipaddr]["stamp"] = $now;
            submitSync();
            return 0;
        }
    }

}

add_action('rest_api_init', function() {
    register_rest_route('apis', 'get-classes', [
        'methods' => 'GET',
        'callback' => function (){
            $filtered = '';
            $ress = $GLOBALS['wpdb'] -> get_results("select slug from wp_terms where slug like '%-type' and 1=1", 'ARRAY_A');
            foreach ($ress as $k => $item) {
                $filtered = $filtered . '###' . $item['slug'];
            }
            return [
                'groups' => $filtered,
            ];
        }
    ]);
});

add_action('rest_api_init', function() {
    register_rest_route('apis', 'submit', [
        'methods' => 'POST',
        'callback' => function ($request){
            $thisIP = $_SERVER['REMOTE_ADDR'];
            $isSuccessed = pushIpAccessedStatus($thisIP) == 0;
            $inst = ($GLOBALS["extra_accessedIPs"][$thisIP] ?? false);
            if(!$isSuccessed) return [
                'status' => false,
                'instance' => ($inst ? $inst : 'unset_yet'),
            ];

            $respBody = file_get_contents('php://input');
            // 
            
            return [
                'status' => $isSuccessed,
                'instance' => ($inst ? $inst : 'unset_yet'),
                'receivedForm' => $respBody
            ];
        }
    ]);
});

?>