var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})

async function copyToClipboard(string)
{
    if (string.substring(0, 1) === '#') {
        string = $(string).text();
    }

    try {
        await navigator.clipboard.writeText(string);
        showToastMessage('Copied text to clipboard');
    }
    catch (err) {
        showToastMessage('Failed to copy: ' + err);
    }
}

function formatCurrency(x) {
    const parts = x.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return parts.join('.');
}

function currencyToFloat(x) {
    // Remove all a-z, A-Z and spaces
    x = x.replace(/[a-zA-Z\s]/g, '');

    return parseFloat(x);
}

function showToastMessage(message) {
    // Create toast element
    const toast = document.createElement('div');
    toast.classList.add('toast');
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    // Set autohide and delay for the toast
    toast.dataset.bsAutohide = 'true';
    toast.dataset.bsDelay = '3000'; // Display for 3 seconds

    // Toast content
    toast.innerHTML = `
    <div class="toast-header">
      <strong class="me-auto">Notification</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      ${message}
    </div>
  `;

    // Append toast to the container and show
    const container = document.getElementById('toastContainer');
    container.appendChild(toast);

    // Initialize toast with Bootstrap Toast plugin
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}
