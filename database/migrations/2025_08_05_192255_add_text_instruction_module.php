<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTextInstructionModule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bot_module', function (Blueprint $table) {
            $table->text('vpn_instructions')->after('tariff_cost')->nullable();
        });

        // Добавляем стандартные инструкции в текстовом формате
        $defaultInstructions = <<<TEXT
<blockquote><b>🔐 Инструкция по настройке VPN</b></blockquote>

1️⃣ Нажмите кнопку <strong>«Купить»</strong> и приобретите VPN-ключ
2️⃣ Скопируйте конфигурацию полученного 🔑 ключа
3️⃣ Вставьте конфигурацию в приложение <a href="https://play.google.com/store/apps/details?id=app.hiddify.com&hl=ru">Hiddify</a> или <a href="https://apps.apple.com/ru/app/streisand/id6450534064">Streisand</a>

<blockquote><b>📁 Пошаговые инструкции по установке:</b></blockquote>

- <a href="https://teletype.in/@bott_manager/UPSEXs-nn66">Инструкция для Android</a> 📱
- <a href="https://teletype.in/@bott_manager/nau_zbkFsdo">Инструкция для iOS</a> 🍏
- <a href="https://teletype.in/@bott_manager/HhKafGko3sO">Инструкция для Windows</a> 🖥️

<blockquote><b>❓ Что делать, если VPN не подключается?</b></blockquote>

✅ Убедитесь, что используете <strong>актуальный конфиг</strong> (ключ не просрочен)
🔁 Попробуйте <strong>другой протокол</strong>: VLESS / VMess / Shadowsocks / Trojan
📲 Смените приложение на <strong>Hiddify</strong> или <strong>Streisand</strong> (другие не рекомендуются)
🔄 Перезагрузите устройство
💬 Обратитесь в поддержку бота
TEXT;

        DB::statement("UPDATE bot_module SET vpn_instructions = ?", [$defaultInstructions]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
//
    }
}
