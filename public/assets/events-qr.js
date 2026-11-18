document.querySelectorAll('[data-event-qr]').forEach(container => {
    if (typeof QRCode === 'function') {
        new QRCode(container, {text: container.dataset.eventQr, width: 160, height: 160});
    }
});
const checkin = document.querySelector('[data-event-checkin]');
const ticket = location.hash.slice(1);
if (checkin && /^[a-f0-9]{48}$/.test(ticket)) {
    checkin.value = ticket;
    history.replaceState(null, '', location.pathname + location.search);
    checkin.focus();
}
