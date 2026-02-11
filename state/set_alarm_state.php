<?php
   // state/set_alarm_state.php
   // Updates shared alarm enabled/disabled state for all viewers.

   // ---- CONFIG ----
$STATE_FILE = __DIR__ . "/alarm_state.json"; // same directory

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

function respond($code, $obj) {
  http_response_code($code);
  echo json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

// GET or POST both supported
$enabled = $_REQUEST["enabled"] ?? null; // "1" or "0"
$by      = $_REQUEST["by"] ?? "unknown";

if ($enabled !== "0" && $enabled !== "1") {
  respond(400, ["ok"=>false, "error"=>"enabled must be 0 or 1"]);
}

$state = [
	  "enabled"    => ($enabled === "1"),
	  "updated_at" => time(),
	  "updated_by" => substr($by, 0, 64),
	  ];

// Write atomically with file lock
$tmp = $STATE_FILE . ".tmp";
$fp = fopen($tmp, "w");
if (!$fp) respond(500, ["ok"=>false, "error"=>"cannot open tmp file"]);

if (!flock($fp, LOCK_EX)) {
  fclose($fp);
  respond(500, ["ok"=>false, "error"=>"cannot lock tmp file"]);
}

fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

if (!rename($tmp, $STATE_FILE)) {
  respond(500, ["ok"=>false, "error"=>"cannot replace state file"]);
}

respond(200, array_merge(["ok"=>true], $state));