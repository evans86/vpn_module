function fallbackCopyTextToClipboard(text, protocol) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        document.execCommand('copy');
        showNotification(`Конфигурация ${protocol} скопирована в буфер обмена`, 'success');
    } catch (err) {
        showNotification(`Ошибка при копировании конфигурации ${protocol}`, 'error');
    }

    document.body.removeChild(textArea);
}

function copyToClipboard(text, protocol) {
    if (!navigator.clipboard) {
        fallbackCopyTextToClipboard(text, protocol);
        return;
    }
    
    navigator.clipboard.writeText(text).then(() => {
        showNotification(`Конфигурация ${protocol} скопирована в буфер обмена`, 'success');
    }).catch(() => {
        fallbackCopyTextToClipboard(text, protocol);
    });
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg transition-opacity duration-500 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 500);
    }, 2000);
}

function showQR(text) {
    // Очищаем предыдущий QR код
    const container = document.getElementById('qrcode');
    container.innerHTML = '';
    
    // Создаем новый QR код
    new QRCode(container, {
        text: text,
        width: 256,
        height: 256,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    
    // Показываем модальное окно
    document.getElementById('qrModal').classList.remove('hidden');
    document.getElementById('qrModal').classList.add('flex');
}

function closeQR() {
    document.getElementById('qrModal').classList.add('hidden');
    document.getElementById('qrModal').classList.remove('flex');
}

// Закрытие модального окна при клике вне его
document.getElementById('qrModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQR();
    }
});

// Закрытие модального окна при нажатии Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('qrModal').classList.contains('hidden')) {
        closeQR();
    }
});
