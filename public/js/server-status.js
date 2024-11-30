class ServerStatusChecker {
    constructor(serverId) {
        this.serverId = serverId;
        this.checkInterval = null;
        this.maxAttempts = 30; // Максимальное количество попыток
        this.currentAttempt = 0;
        this.checkIntervalTime = 10000; // 10 секунд между проверками
    }

    startChecking() {
        this.checkInterval = setInterval(() => {
            this.checkStatus();
        }, this.checkIntervalTime);
    }

    async checkStatus() {
        try {
            const response = await fetch(`/api/servers/check-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    server_id: this.serverId
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Ошибка при проверке статуса');
            }

            // Если сервер готов или произошла ошибка
            if (data.status === 'ready' || data.status === 'error') {
                this.stopChecking();
                this.updateUI(data.status, data.message);
            }

            this.currentAttempt++;
            if (this.currentAttempt >= this.maxAttempts) {
                this.stopChecking();
                this.updateUI('timeout', 'Превышено время ожидания создания сервера');
            }

        } catch (error) {
            console.error('Ошибка при проверке статуса:', error);
            this.updateUI('error', error.message);
            this.stopChecking();
        }
    }

    stopChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    updateUI(status, message) {
        // Обновление UI в зависимости от статуса
        const statusElement = document.getElementById('server-status');
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.className = `status-${status}`; // Для стилизации разных статусов
        }

        // Событие для возможной обработки в основном коде
        const event = new CustomEvent('serverStatusUpdate', {
            detail: { status, message }
        });
        document.dispatchEvent(event);
    }
}
