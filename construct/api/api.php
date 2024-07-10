<?php
global $wpdb;
$redis = new Redis();
$redis -> connect('localhost', 6379);
$tmp = $redis -> get('ips');
$extra_accessedIPs = isset($tmp) ? json_decode($tmp, true) : [];
$tmp = null;
$tmp = $redis -> get('isDbSet');
$dbCreated = $tmp ?? isset($tmp);
$_debugDataSet = [];
$_debugDataSet['oxx'] = 'xx';
try {
    $_debugDataSet['dbCreated'] = var_export($dbCreated, true);
    if(!(var_export($dbCreated, true) == "'true'")){ // redis: no table then check if table do lost
        $_debugDataSet['beginNoDb'] = 'pinned';
        $__actuallyExist = false;
        $entireListDb = $GLOBALS['wpdb'] -> get_results("show tables", 'ARRAY_A');
        foreach($entireListDb as $k => $v){
            if($v == 'wp_ex_submissions') {
                $__actuallyExist = true;
                break;
            }
        }
        if(!$__actuallyExist){
            $___table = "create table wp_ex_submissions(
                id int auto_increment unique not null, 
                name varchar(64) not null, 
                link varchar(128) not null,
                easy varchar(128) not null,
                type varchar(64) not null,
                stat varchar(10) not null default 'pending',
                dscr text
            )";
            $GLOBALS['wpdb'] -> query($___table);
        }
        // let redis know actually exists
        writeRedis('isDbSet', 'true', true);
        $GLOBALS['dbCreated'] = 'true';
        $_debugDataSet['tables'] = $GLOBALS['wpdb'] -> get_results("show tables", 'ARRAY_A');
        $_debugDataSet['isdbc'] = readRedis('isDbSet', true);

    }
    else{ // redis认为有表，实际上mysql没表，也会进入这里。
        $_debugDataSet['db'] = 'already exists# if not, check if the necessary table "wp_ex_submissions" exists.';
    }
} catch (\Throwable $th) {
    //throw $th;
    dbbp(`$th exception`);
}

$redis -> close();

$MAX_TRY = 2;
$COOL_DOWN = 30;//0; // seconds
// to debug it in small time span, modify it to 30.

function dbbp ($msg){
    $GLOBALS['_debugDataSet'][`PointMsg: $msg`] = 'pinned';
}

function writeRedis($key, $text, $dontClose = false){
    openRedis();
    $GLOBALS["redis"] -> set($key, $text);
    if($dontClose) return;
    closeRedis();
}

function readRedis($key, $dontClose = false){
    openRedis();
    $rtemp_ = $GLOBALS["redis"] -> get($key);
    if($dontClose) return $rtemp_;
    closeRedis();
    return $rtemp_;
}

function closeRedis(){
    $GLOBALS["redis"] -> close();
}

function openRedis($host = 'localhost', $port = 6379){
    $GLOBALS["redis"] -> connect($host, $port);
}

function submitSync(){
    writeRedis('ips', json_encode($GLOBALS["extra_accessedIPs"]), true);
    $GLOBALS["extra_accessedIPs"] = json_decode(($GLOBALS["redis"]) -> get('ips'), true);
    closeRedis();
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
            $ress = $GLOBALS['wpdb'] -> get_results("select slug, name from wp_terms where slug like '%-type' and 1=1", 'ARRAY_A');
            foreach ($ress as $k => $item) {
                $filtered = $filtered . '###' . $item['name'] . ' ('. $item['slug'] . ')';
            } // 如果能解决检查分类变更的话，可以考虑缓存在redis
            return [
                'groups' => $filtered,
                'dbc' => $GLOBALS['_debugDataSet'],
                'now' => time(),
                'isex' => readRedis('isDbSet'),
                'xx' => $GLOBALS['dbCreated']
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
                'msg' => 'overflow'
            ];

            $respBody = file_get_contents('php://input');
            // 
            $fill = json_decode($respBody, true);
            if(($fill['name'] ?? '') == '' || ($fill['link'] ?? '') == '' || ($fill['easy'] ?? '') == '' || ($fill['type'] ?? '') == '' || (strlen($fill['dscr']) / 3) >= 100){
                return [
                    'status' => false,
                    'instance' => ($inst ? $inst : 'unset_yet'),
                    'msg' => 'wrong data',
                    'chk' => $fill
                ];
            }

            $GLOBALS['wpdb'] -> insert('wp_ex_submissions', $fill);
            
            return [
                'status' => $isSuccessed,
                'instance' => ($inst ? $inst : 'unset_yet'),
                'receivedForm' => $respBody
            ];
        }
    ]);
});


?>