# Baby MIND DAQ Monitor Page (MCR Data-Size Watchdog)

This repository contains a single HTML page that monitors Baby MIND DAQ activity by checking whether the **data size is changing** for each MCR (MCR0–MCR7). If **at least one** MCR shows **no change** in data size for more than **30 minutes**, the page switches to a warning state and can play an audible alarm.

The page is intended to be kept open on a control-room monitoring PC so that DAQ issues can be noticed quickly.

In addition, the page supports a **global alarm enable/disable switch shared across all PCs** by storing a shared state file on the server. Local **audio permission** and **test sound** remain per-PC.
The shared (global) alarm state is also shown in the UI as a button-like status chip (**alarm on/off**), so all viewers can see when someone disabled/enabled the real alarm.

---

## What it monitors

### Input files
The monitor reads text files in:

- `DATASIZE_DIR_URL/mcr_0_12h.txt`
- `DATASIZE_DIR_URL/mcr_1_12h.txt`
- ...
- `DATASIZE_DIR_URL/mcr_7_12h.txt`

Each file is a time series with two columns per line:

```
<unixtime> <data_size>
```

- Column 1: Unix time (seconds)
- Column 2: Data size (shown as kB in the table)

The files are expected to be updated roughly every ~3 minutes, but the logic does **not** assume a fixed interval.

### “Last update time” definition (per MCR)
For each MCR, the page computes the **last time the data size changed** (increase or decrease) by scanning the file backward until it finds a line where:

- `size[j] != size[j-1]`

That timestamp is shown as **“Last update time (JST)”** for the MCR.

> Note: A **decrease** in data size (e.g., run change / file reset) is treated as **normal** and counts as a “change”.

### 30 minutes ago value
The **“Data size 30 min ago”** column is computed by selecting the newest point whose Unix time is **≤ (now − 30 minutes)**.
If the file is too short and all points are newer than the target time, the oldest point in the file is used as a fallback.

### Alarm condition
For each MCR, the status is **OK** if the data size has changed within the last 30 minutes:

- `now - lastChangeTime <= 30 minutes`

If **any** MCR is not OK, the overall page status becomes **WARN** and the alarm can be triggered (if sound is enabled).

---

## UI behavior

### Operator warning + permission gate for alarm switches
Only the **ND280 shift leader** and the **Baby MIND expert** are allowed to operate the alarm switches (**Enable ound** / **Disable sound**).

The page implements this in two ways:

1. **Always-visible operator warning (top of page)**  
   A red warning banner is shown at the top:
   - “Only the ND280 shift leader and the Baby MIND expert are allowed to operate the alarm switch.”
   - “Anyone other than the ND280 shift leader and the Baby MIND expert must NOT touch the alarm switch.”

2. **Confirmation dialog before button actions**  
   When a user clicks **Enable sound** or **Disable sound**, the browser shows a confirmation dialog:
   - `Are you ND280 shift leader or Baby MIND expert?`
   - **OK** = treated as “Yes” → proceed with the action
   - **Cancel** = treated as “No” → block the action

   If the user answers **No**, a warning box is shown under the alarm controls (and blinks on repeated attempts).

> Note: This is a UI safety guard. It is not a security mechanism (anyone could still bypass it by editing the HTML/JS).

### Status banner + lamp
- **Green (OK):** all MCRs changed within the last 30 minutes
- **Red (WARN):** at least one MCR has not changed for > 30 minutes

### Global alarm status indicator (shared state visibility)
The page displays a non-clickable, button-like status chip:
- **alarm on** / **alarm off** (shared across all PCs, updated by periodic polling)

### Sound controls
Browsers typically block autoplay audio unless the user has interacted with the page. This page uses an explicit button to unlock audio.

- **Enable sound (click once)**  
  Performs a short, low-volume playback to unlock browser audio permission, then enables automatic alarms.

- **Test sound (same button after enabling)**  
  After enabling, the button changes to **Test sound** and toggles a continuous test alarm ON/OFF.

- **Disable sound**  
  Stops any currently playing sound immediately and disables alarms until re-enabled.

### Global enable/disable (shared across PCs)
The **real alarm** (alarm during WARN/NG state) can be enabled/disabled **globally** across all PCs:

- A shared JSON state file stores whether the alarm is globally enabled.
- When one user clicks **Disable sound**, the page updates the shared state to globally disable the real alarm.
- Other PCs periodically fetch the shared state and will stop the real alarm if it becomes disabled.

Important notes:
- **Browser audio permission cannot be shared.** Each PC must click **Enable sound** once to unlock audio in its own browser.
- **Test sound is per-PC only** and is not affected by the global state.

### Shared state polling interval (faster than the main watchdog)
To reflect changes from other PCs quickly, the page polls the shared state more frequently than the main loop:
- `fetchGlobalEnabled()` is called every **3 seconds** (3000 ms) to refresh the status chip and stop the real alarm if needed.

### Real alarm stop behavior when globally disabled
If the shared state becomes disabled while the real alarm is playing on a PC, the page stops the real alarm immediately on that PC. Test sound (manual) is not stopped.

### Test sound vs. real alarm priority
- While **Test sound** is playing, the monitor will **not** stop the sound when the system is OK.
- If the system becomes **WARN** during a test, the page **cancels the test** and starts the **real alarm**.

---

## “Last update is … (JST)” at the top of the page
The top timestamp is updated using `mcr_0_12h.txt`:

1. Prefer the HTTP `Last-Modified` header of `mcr_0_12h.txt` (server-side file modification time).
2. Fallback: use the latest Unix time inside the file (data timestamp).

---

## Configuration

Edit these constants in the `<script>` section:

- `DATASIZE_DIR_URL`  
  Path (URL) to the directory containing `mcr_*.txt`. Example:

  ```js
  const DATASIZE_DIR_URL = "../../wagasci/bmom/datasize";
  ```

- `THRESHOLD_MINUTES`  
  Time window for detecting “stopped” status (default: 30).

- `POLL_MS`  
  Update interval (default: 30000 ms = 30 s).

### UI configuration (global alarm status chip)
The HTML includes a non-clickable, button-like element that shows the shared global state:
- `Alarm status: alarm on/off`

Its visual style is controlled by CSS classes on `#mcr-global-state`:
- `alarm-on`, `alarm-off`, `alarm-loading` (toggled by JavaScript)

### Shared state configuration (global enable/disable)
To share the enable/disable flag across PCs, configure these URLs in the HTML:

- `STATE_URL`  
  A JSON file that is readable by the browser:
  ```js
  const STATE_URL = "state/alarm_state.json";
  ```

- `SET_STATE_URL`  
  A server-side endpoint that updates `alarm_state.json`:
  ```js
  const SET_STATE_URL = "state/set_alarm_state.php";
  ```

The page:
- Calls `fetch(STATE_URL)` periodically to update `globalEnabled` (and refresh the status chip).
- Calls `fetch(SET_STATE_URL + "?enabled=0|1&by=...")` when the user clicks Disable/Enable.

The expected JSON structure is:
```json
{
  "enabled": true,
  "updated_at": 0,
  "updated_by": "init"
}
```

---

## Deployment notes

- The HTML page must be served via HTTP(S) from the same site where the text files are accessible, or CORS must allow access.
- The monitor uses `fetch(..., { cache: "no-store" })` to reduce stale reads.
- The page previously used `<meta http-equiv="refresh">`, but it is commented out to avoid losing audio permission on reload.

### Sticky UI elements (scroll behavior)
If you do **not** want the warning/alarm UI to “stick” to the top during scrolling, ensure the following elements are **not** `position: sticky` in CSS:

- `#mcr-operator-warning`
- `#mcr-status-banner`
- `#mcr-enable-bar`

In the current version, these are set to `position: static`.

### Server-side requirement for global enable/disable
To make the global switch work, the server must provide a writable state file and an update endpoint:

- `state/alarm_state.json` must be writable by the web server process (or by the mechanism used by `set_alarm_state.php`).
- `state/set_alarm_state.php` must:
  - read `enabled` (0/1) from the query string (and optionally `by`)
  - write `alarm_state.json` atomically
  - return JSON (and set `Cache-Control: no-store`)

If the endpoint is missing or the path is wrong, the browser console will show errors such as `404 (Not Found)` for `SET_STATE_URL`.

---

## Recommended operation (example)

- Keep the page open on the control-room monitoring PC for WAGASCI/BM.
- Click **Enable sound** once after opening the page.
- If the alarm sounds (WARN state), the shift person can contact the DAQ expert promptly.

If someone disables the real alarm globally:
- The real alarm should stop on all PCs within the polling interval (default: ~3 seconds for shared-state polling).
- When the alarm should be re-enabled globally, click **Enable sound** (or provide a dedicated global enable action) to set the shared state back to enabled.

---

## Files

- `index.html` (or your HTML filename): the full monitor page
- `fanfare2_alarm.wav`: alarm sound file used by the page (must be in the same directory as the HTML, or update the `<audio>` source)

---

## Troubleshooting

### “Autoplay blocked” alert appears
- Make sure you clicked **Enable sound** once.
- Ensure the `<audio>` element ID in JavaScript matches the HTML:
  - HTML: `id="mcr-beep-sound"`
  - JS: `document.getElementById("mcr-beep-sound")`
- Check browser site settings: allow sound/autoplay for the monitoring URL.

### Images/plots do not load
- Confirm the image paths are correct relative to the HTML location.
- Check file permissions and web server access rights.

### Global disable does not propagate to other PCs
- Confirm `STATE_URL` points to the correct JSON file and is readable from the browser.
- Confirm `SET_STATE_URL` points to the correct PHP endpoint path (a wrong path typically causes `404 (Not Found)` in the console).
- Verify the state file is actually updated on the server (e.g., `cat state/alarm_state.json` on the server).
- Ensure other PCs are polling the shared state periodically and that the code stops the real alarm when `enabled=false`.

### Disable updates the file on the server, but the page still behaves incorrectly
- Ensure the page fetches `STATE_URL` every cycle (or at least periodically) and updates `globalEnabled`.
- Ensure the real alarm can be stopped immediately when `globalEnabled` becomes false (track whether the real alarm is playing separately from test sound).
- Ensure the UI indicator is refreshed when the shared state is fetched (e.g., call `updateGlobalStateUI()` inside `fetchGlobalEnabled()`).
