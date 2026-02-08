# Baby MIND DAQ Monitor Page (MCR Data-Size Watchdog)

This repository contains a single HTML page that monitors Baby MIND DAQ activity by checking whether the **data size is changing** for each MCR (MCR0–MCR7). If **at least one** MCR shows **no change** in data size for more than **30 minutes**, the page switches to a warning state and can play an audible alarm.

The page is intended to be kept open on a control-room monitoring PC so that DAQ issues can be noticed quickly.

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

### Status banner + lamp
- **Green (OK):** all MCRs changed within the last 30 minutes
- **Red (WARN):** at least one MCR has not changed for > 30 minutes

### Sound controls
Browsers typically block autoplay audio unless the user has interacted with the page. This page uses an explicit button to unlock audio.

- **Enable sound (click once)**  
  Performs a short, low-volume playback to unlock browser audio permission, then enables automatic alarms.

- **Test sound (same button after enabling)**  
  After enabling, the button changes to **Test sound** and toggles a continuous test alarm ON/OFF.

- **Disable sound**  
  Stops any currently playing sound immediately and disables alarms until re-enabled.

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

---

## Deployment notes

- The HTML page must be served via HTTP(S) from the same site where the text files are accessible, or CORS must allow access.
- The monitor uses `fetch(..., { cache: "no-store" })` to reduce stale reads.
- The page previously used `<meta http-equiv="refresh">`, but it is commented out to avoid losing audio permission on reload.

---

## Recommended operation (example)

- Keep the page open on the control-room monitoring PC for WAGASCI/BM.
- Click **Enable sound** once after opening the page.
- If the alarm sounds (WARN state), the shift person can contact the DAQ expert promptly.

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
