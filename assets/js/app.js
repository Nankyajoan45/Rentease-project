// RentEase - Main JavaScript

// Dropdown toggle
function toggleDropdown(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('hidden');
    // Close when clicking outside
    const close = (e) => {
        if (!el.contains(e.target) && !e.target.closest('[onclick]')) {
            el.classList.add('hidden');
            document.removeEventListener('click', close);
        }
    };
    if (!el.classList.contains('hidden')) {
        setTimeout(() => document.addEventListener('click', close), 50);
    }
}

// Mobile menu
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu) menu.classList.toggle('hidden');
}

// Toast notifications
function showToast(message, type = 'success', duration = 3500) {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle', info: 'fa-info-circle' };
    const colors = { success: 'bg-emerald-500', error: 'bg-red-500', warning: 'bg-amber-500', info: 'bg-blue-500' };

    const toast = document.createElement('div');
    toast.className = `fixed bottom-20 md:bottom-6 right-4 left-4 md:left-auto md:max-w-sm z-[9999] flex items-center gap-3 px-4 py-3 rounded-2xl text-white shadow-xl ${colors[type]} transform translate-y-2 opacity-0 transition-all duration-300`;
    toast.innerHTML = `<i class="fas ${icons[type]} text-lg"></i><span class="font-medium text-sm">${message}</span>`;
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Confirm dialog
function confirmAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            </div>
            <p class="text-center text-slate-700 font-medium mb-6">${message}</p>
            <div class="flex gap-3">
                <button id="confirmCancel" class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50">Cancel</button>
                <button id="confirmOk" class="flex-1 py-2.5 rounded-xl bg-red-500 text-white font-semibold text-sm hover:bg-red-600">Confirm</button>
            </div>
        </div>`;
    document.body.appendChild(modal);
    document.getElementById('confirmCancel').onclick = () => modal.remove();
    document.getElementById('confirmOk').onclick = () => { modal.remove(); callback(); };
}

// AJAX form helper
async function postForm(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
        body: JSON.stringify(data)
    });
    return res.json();
}

// Image preview
function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;

    input.addEventListener('change', function() {
        preview.innerHTML = '';
        Array.from(this.files).slice(0, 5).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'relative';
                div.innerHTML = `<img src="${e.target.result}" class="w-full h-24 object-cover rounded-xl border border-slate-200">`;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });
}

// Drag and drop upload
function setupDragDrop(zoneId, inputId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragging'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragging'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragging');
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
    });
}

// Search filter with auto-submit
function setupAutoFilter() {
    const form = document.getElementById('searchForm');
    if (!form) return;
    form.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', () => form.submit());
    });
}

// Price range formatter
function formatPrice(amount) {
    return 'UGX ' + parseInt(amount).toLocaleString();
}

// Character counter
function setupCharCounter(inputId, counterId, max) {
    const input = document.getElementById(inputId);
    const counter = document.getElementById(counterId);
    if (!input || !counter) return;
    const update = () => { counter.textContent = `${input.value.length}/${max}`; };
    input.addEventListener('input', update);
    update();
}

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});

// Init on page load
document.addEventListener('DOMContentLoaded', () => {
    setupAutoFilter();

    // Animate cards on load
    const cards = document.querySelectorAll('.property-card, .stat-card');
    const observer = new IntersectionObserver(entries => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, i * 60);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(16px)';
        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        observer.observe(card);
    });
});
