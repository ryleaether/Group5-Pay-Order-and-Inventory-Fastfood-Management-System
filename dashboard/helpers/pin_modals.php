<!-- ================= PIN MODAL ================= -->
<div id="pinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center; border-radius:20px; padding:32px 28px;">
        <span class="close" onclick="document.getElementById('pinModal').classList.remove('show')">&times;</span>
        <div style="font-size:36px; margin-bottom:10px;">🧾</div>
        <h2 style="margin-bottom:4px; font-size:18px; font-weight:700; color:var(--text-primary);">Switch to Cashier Dashboard</h2>
        <p style="color:#888; font-size:13px; margin-bottom:20px;">Enter your 4-digit PIN to continue</p>
        <div id="pinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad" style="max-width:220px; margin:0 auto;">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k === '⌫' ? 'pinBackspace()' : ($k === '' ? '' : "pinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="pinError" style="color:#dc2626; font-size:13px; margin-top:12px; display:none;">
            Incorrect PIN. Try again.
        </p>
        <button onclick="document.getElementById('pinModal').classList.remove('show'); pinValue=''; updatePinDots('pinDots',0);"
                style="margin-top:14px; background:none; border:1.5px solid var(--border-color); padding:8px 24px; border-radius:8px; cursor:pointer; font-size:13px; color:var(--text-secondary); font-family:inherit;">
            Cancel
        </button>
    </div>
</div>

<!-- ================= SETUP PIN MODAL ================= -->
<div id="setupPinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center; border-radius:20px; padding:32px 28px;">
        <span class="close" onclick="document.getElementById('setupPinModal').classList.remove('show')">&times;</span>
        <div style="font-size:36px; margin-bottom:10px;">🔑</div>
        <h2 style="margin-bottom:4px; font-size:18px; font-weight:700; color:var(--text-primary);">Set Up Cashier PIN</h2>
        <p style="color:#888; font-size:13px; margin-bottom:4px;" id="setupPinLabel">
            Enter a 4-digit PIN to protect the Cashier Dashboard
        </p>
        <div id="setupPinDots" style="display:flex; justify-content:center; gap:12px; margin:16px 0 20px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad" style="max-width:220px; margin:0 auto;">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k === '⌫' ? 'setupPinBackspace()' : ($k === '' ? '' : "setupPinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="setupPinError" style="color:#dc2626; font-size:13px; margin-top:12px; display:none;"></p>
        <button onclick="document.getElementById('setupPinModal').classList.remove('show'); setupPinStep=1; setupPinFirst=''; setupPinCurrent=''; updatePinDots('setupPinDots',0);"
                style="margin-top:14px; background:none; border:1.5px solid var(--border-color); padding:8px 24px; border-radius:8px; cursor:pointer; font-size:13px; color:var(--text-secondary); font-family:inherit;">
            Cancel
        </button>
    </div>
</div>

<script>
let pinValue = '';
window.openPinModal = function() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin')
        .then(r => r.json())
        .then(data => {
            if (data.has_pin) {
                pinValue = '';
                updatePinDots('pinDots', 0);
                document.getElementById('pinError').style.display = 'none';
                document.getElementById('pinModal').classList.add('show');
            } else {
                setupPinStep = 1; setupPinFirst = ''; setupPinCurrent = '';
                updatePinDots('setupPinDots', 0);
                document.getElementById('setupPinLabel').textContent = 'Enter a 4-digit PIN to protect the Cashier Dashboard';
                document.getElementById('setupPinError').style.display = 'none';
                document.getElementById('setupPinModal').classList.add('show');
            }
        });
};

document.addEventListener('click', function(e) {
    ['pinModal','setupPinModal'].forEach(id => {
        const m = document.getElementById(id);
        if (m && e.target === m) m.classList.remove('show');
    });
});

function pinPress(num) {
    if (pinValue.length >= 4) return;
    pinValue += String(num);
    updatePinDots('pinDots', pinValue.length);
    if (pinValue.length === 4) verifyPin();
}
function pinBackspace() { pinValue = pinValue.slice(0,-1); updatePinDots('pinDots', pinValue.length); }
function verifyPin() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'pin=' + encodeURIComponent(pinValue)
    }).then(r=>r.json()).then(data => {
        if (data.success) { window.location.href = 'userdashboard.php'; }
        else {
            document.getElementById('pinError').style.display = 'block';
            pinValue = ''; updatePinDots('pinDots', 0);
            const d = document.getElementById('pinDots');
            d.classList.add('pin-shake'); setTimeout(()=>d.classList.remove('pin-shake'),500);
        }
    });
}

let setupPinStep=1, setupPinFirst='', setupPinCurrent='';
function setupPinPress(num) {
    if (setupPinCurrent.length >= 4) return;
    setupPinCurrent += String(num);
    updatePinDots('setupPinDots', setupPinCurrent.length);
    if (setupPinCurrent.length === 4) {
        setTimeout(() => {
            if (setupPinStep === 1) {
                setupPinFirst = setupPinCurrent; setupPinCurrent = ''; setupPinStep = 2;
                document.getElementById('setupPinLabel').textContent = 'Confirm your PIN';
                updatePinDots('setupPinDots', 0);
            } else {
                if (setupPinCurrent === setupPinFirst) {
                    fetch('helpers/admindashboard_helpers.php?action=save_pin', {
                        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'pin='+encodeURIComponent(setupPinCurrent)
                    }).then(r=>r.json()).then(data => {
                        if (data.success) {
                            document.getElementById('setupPinModal').classList.remove('show');
                            window.location.href = 'userdashboard.php';
                        } else {
                            document.getElementById('setupPinError').textContent = 'Failed to save PIN.';
                            document.getElementById('setupPinError').style.display = 'block';
                        }
                    });
                } else {
                    document.getElementById('setupPinError').textContent = "PINs don't match. Try again.";
                    document.getElementById('setupPinError').style.display = 'block';
                    setupPinCurrent = ''; setupPinFirst = ''; setupPinStep = 1;
                    document.getElementById('setupPinLabel').textContent = 'Enter a 4-digit PIN to protect the Cashier Dashboard';
                    updatePinDots('setupPinDots', 0);
                }
            }
        }, 150);
    }
}
function setupPinBackspace() { setupPinCurrent = setupPinCurrent.slice(0,-1); updatePinDots('setupPinDots', setupPinCurrent.length); }
function updatePinDots(id, count) { document.querySelectorAll('#'+id+' .pin-dot').forEach((d,i)=>d.classList.toggle('filled',i<count)); }
</script>