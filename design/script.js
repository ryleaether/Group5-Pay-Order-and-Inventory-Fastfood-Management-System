/**
 * ========================================
 * FormValidator Class - Handle Form Validation
 * ========================================
 */
class FormValidator {
    constructor(formSelector = "form") {
        this.form = document.querySelector(formSelector);
        this.init();
    }

    init() {
        if (!this.form) return;
        this.form.addEventListener("submit", (e) => this.validateForm(e));
    }

    validateForm(e) {
        let inputs = this.form.querySelectorAll("input");
        let isValid = true;

        inputs.forEach(input => {
            if (input.type === "checkbox") return;
            
            if (input.value.trim() === "") {
                this.markInvalid(input);
                isValid = false;
            } else {
                this.markValid(input);
            }
        });

        if (!isValid) {
            e.preventDefault();
            ToastNotification.show("Please fill in all required fields!", "error");
        }
    }

    markInvalid(input) {
        input.style.border = "2px solid red";
        input.style.boxShadow = "0 0 5px rgba(255,0,0,0.5)";
    }

    markValid(input) {
        input.style.border = "1px solid #ddd";
        input.style.boxShadow = "none";
    }
}

/**
 * ================================================
 * ToastNotification Class - Handle Toast Messages
 * ================================================
 */
class ToastNotification {
    static show(message, type = "info") {
        const msgBox = document.createElement("div");
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
}

/**
 * ========================================
 * ButtonAnimator Class - Handle Button Click Animations
 * ========================================
 */
class ButtonAnimator {
    constructor() {
        this.init();
    }

    init() {
        const buttons = document.querySelectorAll("button");
        buttons.forEach(btn => {
            btn.addEventListener("click", () => this.animateClick(btn));
        });
    }

    animateClick(btn) {
        btn.style.transform = "scale(0.97)";
        setTimeout(() => {
            btn.style.transform = "scale(1)";
        }, 150);
    }
}

/**
 * ========================================
 * LiveStats Class - Handle Live Statistics (SuperAdmin)
 * ========================================
 */
class LiveStats {
    constructor() {
        this.totalAdminsEl = document.getElementById("totalAdmins");
        this.activeDevicesEl = document.getElementById("activeDevices");
        
        if (this.totalAdminsEl && this.activeDevicesEl) {
            this.startLiveUpdates();
        }
    }

    startLiveUpdates() {
        this.loadStats();
        setInterval(() => this.loadStats(), 3000);
    }

    loadStats() {
        fetch("live_stats.php")
            .then(response => response.json())
            .then(data => {
                if (this.totalAdminsEl) this.totalAdminsEl.innerText = data.admins;
                if (this.activeDevicesEl) this.activeDevicesEl.innerText = data.active_devices;
            })
            .catch(err => console.error("Live update error:", err));
    }
}

/**
 * ================================================
 * ModalManager Class - Handle Modal Open/Close
 * ================================================
 */
class ModalManager {
    constructor() {
        this.modals = {
            add: 'menuModal',
            edit: 'editModal',
            pin: 'pinModal',
            setupPin: 'setupPinModal'
        };
        this.init();
    }

    init() {
        document.addEventListener('click', (e) => this.handleBackdropClick(e));
    }

    open(type) {
        const modalId = this.modals[type] || (type + 'Modal');
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
        }
    }

    close(type) {
        const modalId = this.modals[type] || (type + 'Modal');
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
        }
    }

    handleBackdropClick(e) {
        Object.values(this.modals).forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && e.target === modal) {
                modal.classList.remove('show');
            }
        });
    }
}

// Global ModalManager instance
const modalManager = new ModalManager();

// Public functions for backward compatibility
function openModal(type) {
    modalManager.open(type);
}

function closeModal(type) {
    modalManager.close(type);
}

/**
 * ================================================
 * ImageUploader Class - Handle Image Uploads
 * ================================================
 */
class ImageUploader {
    constructor(prefix = 'add') {
        this.prefix = prefix;
        this.dropZoneId = prefix + 'DropZone';
        this.initDragDrop();
    }

    handleUpload(file) {
        if (!file) return;

        const statusEl = document.getElementById(this.prefix + 'UploadStatus');
        const previewEl = document.getElementById(this.prefix + 'ImgPreview');
        const placeholderEl = document.getElementById(this.prefix + 'ImgPlaceholder');

        if (statusEl) statusEl.textContent = '⏳ Uploading...';
        if (statusEl) statusEl.style.color = '#888';

        const formData = new FormData();
        formData.append('image', file);

        fetch('upload_image.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (statusEl) statusEl.textContent = '✅ Upload successful!';
                    if (statusEl) statusEl.style.color = '#27ae60';
                    
                    if (previewEl) previewEl.src = data.url;
                    if (placeholderEl) placeholderEl.style.display = 'none';
                    
                    const hiddenField = document.getElementById(this.prefix + 'ImageUrl');
                    if (hiddenField) hiddenField.value = data.url;

                    setTimeout(() => {
                        if (statusEl) statusEl.textContent = '';
                    }, 2000);
                } else {
                    if (statusEl) statusEl.textContent = '❌ ' + data.message;
                    if (statusEl) statusEl.style.color = '#e74c3c';
                }
            })
            .catch(err => {
                console.error('Upload error:', err);
                if (statusEl) statusEl.textContent = '❌ Upload failed';
                if (statusEl) statusEl.style.color = '#e74c3c';
            });
    }

    initDragDrop() {
        const zone = document.getElementById(this.dropZoneId);
        if (!zone) return;

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length > 0) {
                this.handleUpload(e.dataTransfer.files[0]);
            }
        });
    }
}

// Global instances for image uploaders
const addUploader = new ImageUploader('add');
const editUploader = new ImageUploader('edit');

// Public function for backward compatibility
function handleImageUpload(input, prefix) {
    if (prefix === 'add') {
        addUploader.handleUpload(input.files[0]);
    } else if (prefix === 'edit') {
        editUploader.handleUpload(input.files[0]);
    }
}

/**
 * ===============================================
 * EditModalHandler Class - Handle Edit Modal Data
 * ===============================================
 */
class EditModalHandler {
    static openEditModal(id) {
        fetch('edit_menu.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = data.item;
                    document.getElementById('editItemId').value = item.menu_item_id;
                    document.getElementById('editItemName').value = item.item_name;
                    document.getElementById('editDescription').value = item.description;
                    document.getElementById('editPrice').value = item.price;
                    document.getElementById('editStockQty').value = item.stock_quantity;
                    document.getElementById('editCategory').value = item.category;
                    document.getElementById('editAvailable').checked = item.is_available === 1;
                    
                    if (item.image_url) {
                        const imgPreview = document.getElementById('editImgPreview');
                        const imgPlaceholder = document.getElementById('editImgPlaceholder');
                        if (imgPreview) imgPreview.src = item.image_url;
                        if (imgPlaceholder) imgPlaceholder.style.display = 'none';
                    }
                    
                    modalManager.open('edit');
                }
            })
            .catch(err => console.error('Error loading item:', err));
    }
}

// Public function for backward compatibility
function openEditModal(id) {
    EditModalHandler.openEditModal(id);
}

/**
 * ================================================
 * PINManager Class - Handle PIN Operations
 * ================================================
 */
class PINManager {
    constructor() {
        this.pinValue = '';
        this.setupPinStep = 1;
        this.setupPinFirst = '';
        this.setupPinCurrent = '';
        this.checkPINStatus();
    }

    checkPINStatus() {
        fetch('check_pin.php')
            .then(r => r.json())
            .then(data => {
                if (data.has_pin) {
                    // PIN is already set
                } else {
                    // Prompt to set up PIN
                }
            })
            .catch(err => console.error('PIN check error:', err));
    }

    openPinModal() {
        fetch('check_pin.php')
            .then(r => r.json())
            .then(data => {
                if (data.has_pin) {
                    this.pinValue = '';
                    this.updatePinDots('pinDots', 0);
                    modalManager.open('pin');
                } else {
                    this.setupPinStep = 1;
                    this.setupPinFirst = '';
                    this.setupPinCurrent = '';
                    this.updatePinDots('setupPinDots', 0);
                    modalManager.open('setupPin');
                }
            })
            .catch(err => console.error('Error:', err));
    }

    pinPress(num) {
        if (this.pinValue.length >= 4) return;
        this.pinValue += num;
        this.updatePinDots('pinDots', this.pinValue.length);
        if (this.pinValue.length === 4) {
            this.verifyPin();
        }
    }

    pinBackspace() {
        this.pinValue = this.pinValue.slice(0, -1);
        this.updatePinDots('pinDots', this.pinValue.length);
    }

    verifyPin() {
        fetch('check_pin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pin=' + encodeURIComponent(this.pinValue)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ToastNotification.show('PIN verified! Redirecting...', 'info');
                setTimeout(() => {
                    window.location.href = '../dashboard/userdashboard.php';
                }, 1500);
            } else {
                ToastNotification.show('Invalid PIN. Try again.', 'error');
                this.pinValue = '';
                this.updatePinDots('pinDots', 0);
            }
        })
        .catch(err => console.error('PIN verification error:', err));
    }

    setupPinPress(num) {
        if (this.setupPinCurrent.length >= 4) return;
        this.setupPinCurrent += num;
        this.updatePinDots('setupPinDots', this.setupPinCurrent.length);
        
        if (this.setupPinCurrent.length === 4) {
            if (this.setupPinStep === 1) {
                this.setupPinFirst = this.setupPinCurrent;
                this.setupPinStep = 2;
                this.setupPinCurrent = '';
                this.updatePinDots('setupPinDots', 0);
                ToastNotification.show('PIN saved. Enter again to confirm.', 'info');
            } else {
                if (this.setupPinCurrent === this.setupPinFirst) {
                    this.savePin(this.setupPinCurrent);
                } else {
                    ToastNotification.show('PINs do not match. Try again.', 'error');
                    this.setupPinStep = 1;
                    this.setupPinFirst = '';
                    this.setupPinCurrent = '';
                    this.updatePinDots('setupPinDots', 0);
                }
            }
        }
    }

    setupPinBackspace() {
        this.setupPinCurrent = this.setupPinCurrent.slice(0, -1);
        this.updatePinDots('setupPinDots', this.setupPinCurrent.length);
    }

    savePin(pin) {
        fetch('save_pin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pin=' + encodeURIComponent(pin)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ToastNotification.show('PIN saved successfully!', 'info');
                modalManager.close('setupPin');
                this.setupPinStep = 1;
                this.setupPinFirst = '';
                this.setupPinCurrent = '';
            } else {
                ToastNotification.show(data.message || 'Failed to save PIN.', 'error');
            }
        })
        .catch(err => console.error('PIN save error:', err));
    }

    updatePinDots(containerId, count) {
        const dots = document.querySelectorAll('#' + containerId + ' .pin-dot');
        dots.forEach((dot, i) => {
            dot.classList.toggle('filled', i < count);
        });
    }
}

// Global PIN manager instance
const pinManager = new PINManager();

// Public functions for backward compatibility
function openPinModal() {
    pinManager.openPinModal();
}

function pinPress(num) {
    pinManager.pinPress(num);
}

function pinBackspace() {
    pinManager.pinBackspace();
}

function setupPinPress(num) {
    pinManager.setupPinPress(num);
}

function setupPinBackspace() {
    pinManager.setupPinBackspace();
}

function updatePinDots(containerId, count) {
    pinManager.updatePinDots(containerId, count);
}

// Backward compatibility: showMessage is now ToastNotification.show
function showMessage(message, type = "info") {
    ToastNotification.show(message, type);
}

/**
 * ====================================
 * Application Initialization
 * ====================================
 */
document.addEventListener("DOMContentLoaded", function () {
    // Initialize form validation
    new FormValidator("form");

    // Initialize button animations
    new ButtonAnimator();

    // Initialize live stats (if superadmin)
    new LiveStats();

    // Initialize modal manager (already done globally)
    // Already initialized globally: modalManager

    // Initialize image uploaders (already done globally)
    // Already initialized globally: addUploader, editUploader

    // Initialize PIN manager (already done globally)
    // Already initialized globally: pinManager
});