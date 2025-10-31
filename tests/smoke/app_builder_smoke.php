<?php
// Lightweight smoke tests for create/update/build flows without real DB/worker

// ----------------- Mocks -----------------
namespace Workz\Platform\Core {
    class Database {
        public static function getInstance($db) { return new class {
            public function query($sql){ return new class { public function fetchAll(){ return []; } }; }
            public function prepare($sql){ return new class {
                public function execute($params=[]){ }
                public function fetch(){ return false; }
            }; }
            public function exec($sql){ }
        }; }
    }
    class StorageManager {
        public function initializeFilesystemStorage($appId, $input){ return ['success'=>true,'repository_path'=>"/repo/$appId"]; }
        public function saveAppCode($appId, $data){ return ['success'=>true,'code_size_bytes'=>strlen(($data['js_code']??'').($data['dart_code']??''))]; }
        public function migrateApp($appId,$target){ return ['success'=>true]; }
    }
    class BuildPipeline {
        public function triggerBuild(int $appId,$platforms=null,$options=[]){ return ['success'=>true,'build_id'=>"job-$appId",'platforms'=>['web']]; }
        public function getBuildStatus(int $appId){ return ['status'=>'building','platforms'=>['web']]; }
        public function getArtifacts(int $appId){ return []; }
        public function getArtifactPath(int $appId,string $platform){ return null; }
    }
    class UniversalRuntime {
        public static function getBuildInfo($appId,$type){ return ['is_compiled'=>false,'url'=>"/apps/$appId/","script_exists"=>false,'html_exists'=>false,'last_modified'=>date('Y-m-d H:i:s')]; }
        public static function deployApp($appId,$app){ return ['success'=>true,'app_type'=>$app['app_type']??'javascript','url'=>"/apps/$appId/",'compiled_at'=>date('Y-m-d H:i:s')]; }
        public static function cleanApp($appId,$type){ }
    }
}

namespace Workz\Platform\Models {
    class General {
        private static array $apps = [];
        private static array $builds = [];
        public function search($db,$table,$cols=['*'],$where=[],$fetchAll=false,$limit=0,$offset=0,$order=null){
            if ($table==='apps'){
                if (isset($where['id'])){
                    $id=(int)$where['id'];
                    if (!isset(self::$apps[$id])) return $fetchAll?[]:false;
                    return $fetchAll?[self::$apps[$id]]:self::$apps[$id];
                }
                if (isset($where['slug'])){
                    foreach(self::$apps as $a){ if(($a['slug']??null)===$where['slug']) return $fetchAll?[$a]:$a; }
                    return $fetchAll?[]:false;
                }
                return $fetchAll?array_values(self::$apps):false;
            }
            if ($table==='flutter_builds') return $fetchAll?[]:false;
            return $fetchAll?[]:false;
        }
        public function insert($db,$table,$data){
            if ($table==='apps'){
                $id = $data['id'] ?? (count(self::$apps)+101);
                if (!isset($data['exclusive_to_entity_id'])) $data['exclusive_to_entity_id'] = 1;
                if (!isset($data['build_status'])) $data['build_status'] = 'pending';
                if (!isset($data['storage_type'])) $data['storage_type'] = 'filesystem';
                if (!isset($data['app_type'])) $data['app_type'] = 'flutter';
                $data['id']=$id; self::$apps[$id]=$data; return $id;
            }
            return 1;
        }
        public function update($db,$table,$data,$where){ return 1; }
        public function delete($db,$table,$where){ return 1; }
        public function count($db,$table,$where){
            if ($table==='apps' && isset($where['slug'])){
                $slug=$where['slug']; $neq=$where['id']['value']??null;
                $cnt=0; foreach(self::$apps as $id=>$a){ if(($a['slug']??null)===$slug && (is_null($neq) || $id!=$neq)) $cnt++; }
                return $cnt;
            }
            if ($table==='gapp') return 1; // grant access
            return 0;
        }
    }
}

namespace Workz\Platform\Policies { class BusinessPolicy { public static function canManage($u,$e){ return true; } } }

// Stub cURL + php://input within Controllers namespace
namespace Workz\Platform\Controllers {
    // Feed input payloads via this global
    $___SMOKE_INPUT = '';
    function file_get_contents($path){ global $___SMOKE_INPUT; if ($path==='php://input'){ return $___SMOKE_INPUT; } return \file_get_contents($path); }
    if (!\defined('CURLINFO_HTTP_CODE')) { \define('CURLINFO_HTTP_CODE', 2097154); }
    class __Curl { public $httpCode=202; public $resp='OK'; public $err=''; }
    function curl_init($url){ return new __Curl(); }
    function curl_setopt_array($ch,$arr){ return true; }
    function curl_exec($ch){ return $ch->resp; }
    function curl_getinfo($ch,$opt){ return ($opt===CURLINFO_HTTP_CODE)?202:0; }
    function curl_error($ch){ return $ch->err; }
    function curl_close($ch){ }
}

// ----------------- Load Controllers -----------------
namespace {
    require __DIR__ . '/../../src/Controllers/AppManagementController.php';
    require __DIR__ . '/../../src/Controllers/UniversalAppController.php';

    function run_case(string $name, callable $fn){
        $ok=false; $note='';
        try { $ok=$fn(); } catch (\Throwable $e){ $note = "EXCEPTION: ".$e->getMessage(); $ok=false; }
        echo "\n== $name ==\n";
        if ($note) echo $note."\n";
        echo $ok?"PASS\n":"FAIL\n"; return $ok;
    }

    function decode_last_json(string $out): ?array {
        $pos = strrpos($out, '{');
        if ($pos === false) return null;
        $json = substr($out, $pos);
        $j = json_decode($json, true);
        if (is_array($j)) return $j;
        $json = preg_replace('/\}[^\}]*$/', '}', $json);
        $j = json_decode($json, true);
        return is_array($j) ? $j : null;
    }

}

// ----------------- Tests -----------------
namespace {
    $auth = (object)['sub'=>1,'name'=>'Tester'];

    // 1) Create Flutter app -> should enqueue build and return id + build_status pending
    run_case('Create Flutter app enqueues build', function() use ($auth) {
        global $___SMOKE_INPUT; $___SMOKE_INPUT = json_encode([
            'title'=>'Calc','slug'=>'calc-app','app_type'=>'flutter','dart_code'=>'void main(){}'
        ]);
        ob_start(); (new \\Workz\\Platform\\Controllers\\AppManagementController())->createApp($auth); $out = ob_get_clean();
        $j = decode_last_json($out); if (!$j) { echo $out."\\n"; return false; }
        $id = $j['data']['id'] ?? null; $status = $j['data']['build_status'] ?? null;
        echo "id=$id status=$status\n";
        return ($id !== null) && ($status==='pending');
    });

    // 2) Update app with duplicate slug -> should 409
    run_case('Update blocks duplicate slug', function() use ($auth) {
        // Seed another app with same slug to trigger conflict
        $g = new \Workz\Platform\Models\General();
        $g->insert('workz_apps','apps',['id'=>202,'slug'=>'dup-slug','app_type'=>'flutter','exclusive_to_entity_id'=>1]);
        global $___SMOKE_INPUT; $___SMOKE_INPUT = json_encode(['slug'=>'dup-slug']);
        ob_start(); (new \Workz\Platform\Controllers\UniversalAppController())->updateApp($auth, 101); $out = ob_get_clean();
        $j = json_decode($out,true); echo ($j['message']??$out)."\n"; return ($j && $j['success']===false);
    });

    // 3) Update flutter code with files -> should set building
    run_case('Update Flutter code triggers worker', function() use ($auth) {
        // Seed existing app 101
        $g = new \Workz\Platform\Models\General();
        $g->insert('workz_apps','apps',['id'=>101,'slug'=>'calc-app','app_type'=>'flutter','exclusive_to_entity_id'=>1]);
        global $___SMOKE_INPUT; $___SMOKE_INPUT = json_encode(['files'=>['lib/main.dart'=>'void main(){}']]);
        ob_start(); (new \Workz\Platform\Controllers\UniversalAppController())->updateApp($auth, 101); $out = ob_get_clean();
        $j = json_decode($out,true); echo json_encode($j)."\n"; return ($j && $j['success']===true && ($j['build_status']??'')==='building');
    });
}

