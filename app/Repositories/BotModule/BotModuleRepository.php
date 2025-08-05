<?php

namespace App\Repositories\BotModule;

use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Repositories\BaseRepository;

class BotModuleRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return BotModule::class;
    }

    public function updateVpnInstructions(int $moduleId, array $instructions): bool
    {
        return $this->query()
            ->where('id', $moduleId)
            ->update(['vpn_instructions' => json_encode($instructions)]);
    }

    public function getVpnInstructions(int $moduleId): ?array
    {
        $query = $this->query()
            ->where('id', $moduleId)
            ->value('vpn_instructions');

        return $query ? json_decode($query, true) : null;
    }
}
