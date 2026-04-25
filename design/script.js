document.addEventListener("DOMContentLoaded", function () {

    /* =========================
       FORM VALIDATION
    ========================= */
    const form = document.querySelector("form");

    if (form) {
        form.addEventListener("submit", function (e) {

            let inputs = form.querySelectorAll("input");
            let isValid = true;

            inputs.forEach(input => {
                if (input.type === "checkbox") return; // skip checkboxes
                if (input.value.trim() === "") {
                    input.style.border = "2px solid red";
                    input.style.boxShadow = "0 0 5px rgba(255,0,0,0.5)";
                    isValid = false;
                } else {
                    input.style.border = "1px solid #ddd";
                    input.style.boxShadow = "none";
                }
            });

            if (!isValid) {
                e.preventDefault();
                showMessage("Please fill in all required fields!", "error");
            }

        });
    }

    /* =========================
       BUTTON CLICK ANIMATION
    ========================= */
    const buttons = document.querySelectorAll("button");

    buttons.forEach(btn => {
        btn.addEventListener("click", function () {
            this.style.transform = "scale(0.97)";
            setTimeout(() => {
                this.style.transform = "scale(1)";
            }, 150);
        });
    });

    /* =========================
       LIVE STATS (SUPERADMIN ONLY)
    ========================= */
    if (document.getElementById("totalAdmins") &&
        document.getElementById("activeDevices")) {

        loadLiveStats();
        setInterval(loadLiveStats, 3000);
    }

});

/* =========================
   TOAST MESSAGE
========================= */
function showMessage(message, type = "info") {

    let msgBox = document.createElement("div");

    msgBox.innerText = message;
    msgBox.style.position = "fixed";
    msgBox.style.top = "20px";
    msgBox.style.right = "20px";
    msgBox.style.padding = "12px 20px";
    msgBox.style.borderRadius = "8px";
    msgBox.style.color = "white";
    msgBox.style.zIndex = "9999";
    msgBox.style.boxShadow = "0 5px 15px rgba(0,0,0,0.3)";
    msgBox.style.fontFamily = "Poppins, sans-serif";
    msgBox.style.fontSize = "14px";

    msgBox.style.background = type === "error" ? "#e74c3c" : "#3498db";

    document.body.appendChild(msgBox);

    setTimeout(() => {
        msgBox.style.opacity = "0";
        msgBox.style.transition = "0.5s";
    }, 2000);

    setTimeout(() => {
        msgBox.remove();
    }, 2500);
}

/* =========================
   LIVE STATS (SUPERADMIN)
========================= */
function loadLiveStats() {

    fetch("live_stats.php")
        .then(response => response.json())
        .then(data => {

            const admins = document.getElementById("totalAdmins");
            const devices = document.getElementById("activeDevices");

            if (admins)  admins.innerText  = data.admins;
            if (devices) devices.innerText = data.active_devices;

        })
        .catch(err => console.error("Live update error:", err));
}

/* =========================
   MENU MODAL
========================= */
function openModal() {
    const modal = document.getElementById("menuModal");
    if (modal) modal.classList.add("show");
}

function closeModal() {
    const modal = document.getElementById("menuModal");
    if (modal) modal.classList.remove("show");
}

document.addEventListener("click", function (e) {
    const modal = document.getElementById("menuModal");
    if (!modal) return;
    if (e.target === modal) modal.classList.remove("show");
});