<?php

namespace Luthfi\ChatProtection;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

class Main extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getLogger()->info("ChatProtection Enabled");
    }

    public function onDisable(): void {
        $this->getLogger()->info("ChatProtection Disabled");
    }
}
