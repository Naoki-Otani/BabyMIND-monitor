#!/usr/bin/env python3
import os, json, time, urllib.parse

STATE_FILE = "/mnt/hep_web/hep_web/member/otani/BMmom/state/alarm_state.json"

def respond(code=200, body_dict=None):
    if body_dict is None:
        body_dict = {"ok": True}
    body = json.dumps(body_dict)
    print(f"Status: {code} OK")
    print("Content-Type: application/json; charset=UTF-8")
    print("Cache-Control: no-store")
    print()
    print(body)

def parse_params():
    # Support both GET (?a=b) and POST (stdin)
    qs = os.environ.get("QUERY_STRING", "")
    params = urllib.parse.parse_qs(qs)

    length = int(os.environ.get("CONTENT_LENGTH", "0") or "0")
    if length > 0:
        data = os.read(0, length).decode("utf-8", errors="replace")
        post_params = urllib.parse.parse_qs(data)
        # POST overrides GET if both exist
        for k, v in post_params.items():
            params[k] = v
    return params

try:
    params = parse_params()

    enabled = params.get("enabled", [None])[0]  # "1" or "0"
    who = params.get("by", ["unknown"])[0]

    if enabled not in ("0", "1"):
        respond(400, {"ok": False, "error": "enabled must be 0 or 1"})
        raise SystemExit

    state = {
        "enabled": (enabled == "1"),
        "updated_at": int(time.time()),
        "updated_by": who[:64],
    }

    tmp = STATE_FILE + ".tmp"
    with open(tmp, "w") as f:
        json.dump(state, f)
    os.replace(tmp, STATE_FILE)

    respond(200, state)

except Exception as e:
    respond(500, {"ok": False, "error": str(e)})